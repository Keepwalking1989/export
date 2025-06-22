<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// --- Initialize variables ---
$pi_id_from_get = null;
$source_pi_header = null;
$source_pi_items = [];
$po_header_data = []; // To store data for PO form fields
$po_items_data = [];  // To store data for PO item form fields (pre-filled from PI)

$errors = [];
$item_errors = [];

// --- Fetch data for dropdowns ---
$exporters = []; // Will be filtered/pre-selected based on PI
$all_exporters = [];
$sql_all_exporters = "SELECT id, company_name FROM exporters ORDER BY company_name ASC";
$result_all_exporters = $conn->query($sql_all_exporters);
if ($result_all_exporters && $result_all_exporters->num_rows > 0) {
    while ($row = $result_all_exporters->fetch_assoc()) $all_exporters[] = $row;
}

$manufacturers = [];
$sql_manufacturers = "SELECT id, name FROM manufacturers ORDER BY name ASC";
$result_manufacturers = $conn->query($sql_manufacturers);
if ($result_manufacturers && $result_manufacturers->num_rows > 0) {
    while ($row = $result_manufacturers->fetch_assoc()) $manufacturers[] = $row;
}

$sizes_for_po_items = []; // Will be distinct sizes from the source PI for existing items
$all_sizes_for_new_items = []; // All sizes for newly added PO lines
$sql_all_sizes = "SELECT id, size_text, size_prefix, sqm_per_box FROM sizes ORDER BY size_prefix, size_text ASC";
$result_all_sizes = $conn->query($sql_all_sizes);
if ($result_all_sizes && $result_all_sizes->num_rows > 0) {
    while ($row = $result_all_sizes->fetch_assoc()) $all_sizes_for_new_items[] = $row;
}


// --- Default values for PO ---
$po_header_data['po_number'] = generate_po_number($conn);
$po_header_data['po_date'] = get_current_date_for_input();
$default_item_description = "As Per Sample / Master";
$default_item_thickness = "8.7 MM to 9.0 MM";


// --- GET Request: Load data from Performa Invoice ---
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['pi_id']) && !empty(trim($_GET['pi_id']))) {
    $pi_id_from_get = (int)trim($_GET['pi_id']);

    // Check if a PO already exists for this PI
    $sql_check_existing_po = "SELECT id, po_number FROM purchase_orders WHERE performa_invoice_id = ? LIMIT 1";
    if ($stmt_check_po = $conn->prepare($sql_check_existing_po)) {
        $stmt_check_po->bind_param("i", $pi_id_from_get);
        $stmt_check_po->execute();
        $result_existing_po = $stmt_check_po->get_result();
        if ($result_existing_po->num_rows > 0) {
            $existing_po = $result_existing_po->fetch_assoc();
            $errors['load_error'] = "A Purchase Order (".$existing_po['po_number'].") already exists for this Performa Invoice. You can <a href='purchase_order_edit.php?id=".$existing_po['id']."'>edit it here</a> or create a PI for a new PO.";
        }
        $stmt_check_po->close();
    }


    if (empty($errors['load_error'])) {
        // Fetch Source PI Header
        $sql_pi_h = "SELECT * FROM performa_invoices WHERE id = ?";
        if ($stmt_pi_h = $conn->prepare($sql_pi_h)) {
            $stmt_pi_h->bind_param("i", $pi_id_from_get);
            $stmt_pi_h->execute();
            $res_pi_h = $stmt_pi_h->get_result();
            if ($res_pi_h->num_rows == 1) {
                $source_pi_header = $res_pi_h->fetch_assoc();
                $po_header_data['performa_invoice_id'] = $pi_id_from_get;
                $po_header_data['exporter_id'] = $source_pi_header['exporter_id'];
                // Other fields like number_of_containers can be pre-filled if needed
                $po_header_data['number_of_containers'] = $source_pi_header['total_container'];
            } else {
                $errors['load_error'] = "Source Performa Invoice not found.";
            }
            $stmt_pi_h->close();
        } else {
            $errors['load_error'] = "Error preparing to fetch source PI: " . $conn->error;
        }

        // Fetch Source PI Items and distinct sizes from these items
        if (empty($errors['load_error'])) {
            $distinct_size_ids_from_pi = [];
            $sql_pi_items = "SELECT pii.*, s.size_text, s.size_prefix, s.sqm_per_box,
                                    p.design_name,
                                    COALESCE(p.box_weight_override, s.box_weight) as effective_box_weight
                             FROM performa_invoice_items pii
                             JOIN sizes s ON pii.size_id = s.id
                             JOIN products p ON pii.product_id = p.id
                             WHERE pii.performa_invoice_id = ?";
            if ($stmt_pi_items = $conn->prepare($sql_pi_items)) {
                $stmt_pi_items->bind_param("i", $pi_id_from_get);
                $stmt_pi_items->execute();
                $res_pi_items = $stmt_pi_items->get_result();
                while ($item = $res_pi_items->fetch_assoc()) {
                    $po_items_data[] = [
                        'size_id' => $item['size_id'],
                        'product_id' => $item['product_id'],
                        'description' => $default_item_description,
                        'weight_per_box' => $item['effective_box_weight'], // Pre-fill with effective weight
                        'boxes' => $item['boxes'],
                        'thickness' => $default_item_thickness,
                        // Store for display/JS
                        'size_text_display' => htmlspecialchars($item['size_prefix'] . " [" . $item['size_text'] . "]"),
                        'product_design_name_display' => htmlspecialchars($item['design_name'])
                    ];
                    if (!in_array($item['size_id'], $distinct_size_ids_from_pi)) {
                        $distinct_size_ids_from_pi[] = $item['size_id'];
                        // Add to sizes_for_po_items if not already added by full list
                        $found = false;
                        foreach($all_sizes_for_new_items as $s_all){
                            if($s_all['id'] == $item['size_id']){
                                if(!in_array($s_all, $sizes_for_po_items, true)) $sizes_for_po_items[] = $s_all;
                                $found = true;
                                break;
                            }
                        }
                    }
                }
                if(empty($po_items_data)){
                     $errors['load_warning'] = "Source Performa Invoice has no items to pre-fill. You can add items manually.";
                }
                // If sizes_for_po_items is empty after loop (e.g. PI had items with sizes not in main sizes table anymore)
                // then it will use all_sizes_for_new_items.
                if(empty($sizes_for_po_items)) $sizes_for_po_items = $all_sizes_for_new_items;

                $stmt_pi_items->close();
            } else {
                 $errors['load_error'] = "Error preparing to fetch source PI Items: " . $conn->error;
            }
        }
    }
}
// --- POST Request Processing (Save PO) ---
elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();

    // Assign Header POST values
    $po_header_data['performa_invoice_id'] = trim($_POST['performa_invoice_id']);
    $po_header_data['exporter_id'] = trim($_POST['exporter_id']);
    $po_header_data['manufacturer_id'] = trim($_POST['manufacturer_id']);
    $po_header_data['po_number'] = trim($_POST['po_number']);
    $po_header_data['po_date'] = trim($_POST['po_date']);
    $po_header_data['number_of_containers'] = trim($_POST['number_of_containers']);

    // --- Header Validations ---
    if (empty($po_header_data['performa_invoice_id'])) $errors['performa_invoice_id'] = "Source Performa Invoice ID is missing.";
    if (empty($po_header_data['exporter_id'])) $errors['exporter_id'] = "Exporter is required.";
    if (empty($po_header_data['manufacturer_id'])) $errors['manufacturer_id'] = "Manufacturer is required.";
    if (empty($po_header_data['po_number'])) $errors['po_number'] = "PO number is required.";
    else {
        $sql_check_po_num = "SELECT id FROM purchase_orders WHERE po_number = ? LIMIT 1";
        if($stmt_check_po_num = $conn->prepare($sql_check_po_num)){
            $stmt_check_po_num->bind_param("s", $po_header_data['po_number']);
            $stmt_check_po_num->execute();
            $stmt_check_po_num->store_result();
            if($stmt_check_po_num->num_rows > 0) $errors['po_number'] = "This PO number already exists.";
            $stmt_check_po_num->close();
        }
    }
    if (empty($po_header_data['po_date'])) $errors['po_date'] = "PO date is required.";
    // --- End Header Validations ---

    if (empty($errors)) {
        $sql_po_header_insert = "INSERT INTO purchase_orders
                                (performa_invoice_id, exporter_id, manufacturer_id, po_number, po_date, number_of_containers)
                                VALUES (?, ?, ?, ?, ?, ?)";
        if ($stmt_po_header = $conn->prepare($sql_po_header_insert)) {
            $stmt_po_header->bind_param("iiisss",
                $po_header_data['performa_invoice_id'], $po_header_data['exporter_id'], $po_header_data['manufacturer_id'],
                $po_header_data['po_number'], $po_header_data['po_date'], $po_header_data['number_of_containers']
            );
            if ($stmt_po_header->execute()) {
                $new_po_id = $stmt_po_header->insert_id;
                $stmt_po_header->close();

                // --- Process PO Items ---
                $item_size_ids = $_POST['item_size_id'] ?? [];
                $item_product_ids = $_POST['item_product_id'] ?? [];
                $item_descriptions = $_POST['item_description'] ?? [];
                $item_weights_per_box = $_POST['item_weight_per_box'] ?? [];
                $item_boxes_arr = $_POST['item_boxes'] ?? [];
                $item_thickness_arr = $_POST['item_thickness'] ?? [];

                if (!empty($item_size_ids)) {
                    $sql_po_item_insert = "INSERT INTO purchase_order_items
                                        (purchase_order_id, size_id, product_id, description, weight_per_box, boxes, thickness)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_po_item = $conn->prepare($sql_po_item_insert);

                    if ($stmt_po_item) {
                        for ($i = 0; $i < count($item_size_ids); $i++) {
                            if (empty($item_size_ids[$i]) || empty($item_product_ids[$i]) || !isset($item_boxes_arr[$i]) || $item_boxes_arr[$i] === '') {
                                $item_errors[] = "Error in item #".($i+1).": Size, Product, and Boxes are required.";
                                continue;
                            }
                            $param_weight_pb = !empty($item_weights_per_box[$i]) ? (float)$item_weights_per_box[$i] : null;
                            $param_boxes = (float)$item_boxes_arr[$i];
                            $param_desc = !empty($item_descriptions[$i]) ? $item_descriptions[$i] : null;
                            $param_thick = !empty($item_thickness_arr[$i]) ? $item_thickness_arr[$i] : null;

                            $stmt_po_item->bind_param("iiisdds",
                                $new_po_id, $item_size_ids[$i], $item_product_ids[$i],
                                $param_desc, $param_weight_pb, $param_boxes, $param_thick
                            );
                            if (!$stmt_po_item->execute()) {
                                $item_errors[] = "Error saving PO item #".($i+1).": " . $stmt_po_item->error;
                            }
                        }
                        $stmt_po_item->close();
                    } else {
                        $errors['db_error'] = "Error preparing PO item insert statement: " . $conn->error;
                    }
                } // End if items submitted

                if (empty($item_errors) && empty($errors['db_error'])) {
                    $conn->commit();
                    header("location: purchase_order_list.php?status=success_add&id=" . $new_po_id); // Redirect to PO list
                    exit();
                } else {
                    $conn->rollback();
                    if(!empty($item_errors)) $errors['db_error'] = ($errors['db_error'] ?? "") . "<br>Item Errors:<br>" . implode("<br>", $item_errors);
                }
            } else { // PO Header insert failed
                $conn->rollback();
                if ($conn->errno == 1062 && strpos($stmt_po_header->error, 'unique_po_number') !== false) {
                     $errors['db_error'] = "DB Error: This PO Number already exists.";
                } elseif ($conn->errno == 1062 && strpos($stmt_po_header->error, 'unique_performa_invoice_id_for_po') !== false) {
                     $errors['db_error'] = "DB Error: A Purchase Order already exists for this Performa Invoice.";
                } else {
                    $errors['db_error'] = "DB error creating PO header: " . $stmt_po_header->error;
                }
            }
        } else { // PO Header prepare failed
            $errors['db_error'] = "Error preparing PO header statement: " . $conn->error;
        }
    } else { // Header validation errors
        $conn->rollback(); // Rollback if validation fails after starting transaction (though it's early)
        // Repopulate items if POST failed due to header errors
        $posted_item_size_ids = $_POST['item_size_id'] ?? [];
        if (!empty($posted_item_size_ids)) {
            for ($i=0; $i < count($posted_item_size_ids); $i++) {
                $po_items_data[] = [
                    'size_id' => $_POST['item_size_id'][$i] ?? '',
                    'product_id' => $_POST['item_product_id'][$i] ?? '',
                    'description' => $_POST['item_description'][$i] ?? $default_item_description,
                    'weight_per_box' => $_POST['item_weight_per_box'][$i] ?? '',
                    'boxes' => $_POST['item_boxes'][$i] ?? '',
                    'thickness' => $_POST['item_thickness'][$i] ?? $default_item_thickness
                ];
            }
        }
    }
} else { // For GET request, if no pi_id or error loading PI
    if(empty($pi_id_from_get) && !isset($_POST['performa_invoice_id'])) $errors['load_error'] = "No Performa Invoice ID specified to create Purchase Order from.";
    // Default values for PO header fields if not pre-filled from PI
    $po_header_data['po_number'] = $po_header_data['po_number'] ?: $invoice_number_default; // Use default if not set by PI load
    $po_header_data['po_date'] = $po_header_data['po_date'] ?: $invoice_date_default;
}

// --- End of Processing ---
require_once '../../includes/header.php';

if (!empty($errors['load_error'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>" . $errors['load_error'] . " <a href='../performa_invoices/performa_invoice_list.php' class='alert-link'>Back to Performa Invoice List</a>.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
if (!empty($errors['load_warning'])) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>" . $errors['load_warning'] . "</div></div>";
}

?>
<div class="container">
    <h2>Create Purchase Order <?php if($pi_id_from_get) echo "from PI #" . htmlspecialchars($source_pi_header['invoice_number'] ?? $pi_id_from_get); ?></h2>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo $errors['db_error']; /* Allow HTML if item_errors are included */?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="purchaseOrderForm">
        <input type="hidden" name="performa_invoice_id" value="<?php echo htmlspecialchars($po_header_data['performa_invoice_id'] ?? $pi_id_from_get); ?>">

        <h4>Purchase Order Header</h4>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Exporter <span class="text-danger">*</span></label>
                    <select name="exporter_id" class="form-control <?php echo isset($errors['exporter_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">-- Select Exporter --</option>
                        <?php foreach($all_exporters as $exp): ?>
                        <option value="<?php echo $exp['id']; ?>" <?php if(($po_header_data['exporter_id'] ?? '') == $exp['id']) echo 'selected'; ?>><?php echo htmlspecialchars($exp['company_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"><?php echo $errors['exporter_id'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Manufacturer <span class="text-danger">*</span></label>
                    <select name="manufacturer_id" class="form-control <?php echo isset($errors['manufacturer_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">-- Select Manufacturer --</option>
                        <?php foreach($manufacturers as $manuf): ?>
                        <option value="<?php echo $manuf['id']; ?>" <?php if(($po_header_data['manufacturer_id'] ?? '') == $manuf['id']) echo 'selected'; ?>><?php echo htmlspecialchars($manuf['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"><?php echo $errors['manufacturer_id'] ?? '';?></span>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>PO Number <span class="text-danger">*</span></label>
                    <input type="text" name="po_number" class="form-control <?php echo isset($errors['po_number']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($po_header_data['po_number']); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['po_number'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>PO Date <span class="text-danger">*</span></label>
                    <input type="date" name="po_date" class="form-control <?php echo isset($errors['po_date']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($po_header_data['po_date']); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['po_date'] ?? '';?></span>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Number of Container(s)</label>
                    <input type="text" name="number_of_containers" class="form-control" value="<?php echo htmlspecialchars($po_header_data['number_of_containers'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <h4 class="mt-4">Purchase Order Items</h4>
        <hr>
        <table class="table table-bordered" id="po_items_table">
            <thead>
                <tr>
                    <th style="width: 18%;">Size <span class="text-danger">*</span></th>
                    <th style="width: 18%;">Product <span class="text-danger">*</span></th>
                    <th style="width: 20%;">Description</th>
                    <th style="width: 12%;">Wt./Box (KG)</th>
                    <th style="width: 12%;">Boxes <span class="text-danger">*</span></th>
                    <th style="width: 15%;">Thickness</th>
                    <th style="width: 5%;">Action</th>
                </tr>
            </thead>
            <tbody id="po_items_table_body">
                <?php foreach ($po_items_data as $idx => $item_data): ?>
                <tr class="po-item-row">
                    <td>
                        <select name="item_size_id[]" class="form-control item-size" required>
                            <option value="">-- Select Size --</option>
                            <?php
                            // For pre-filled rows, use sizes from PI. For new rows, use all sizes.
                            $current_item_sizes = $sizes_for_po_items; // By default, use PI sizes for pre-filled
                            foreach($current_item_sizes as $s): ?>
                            <option value="<?php echo $s['id']; ?>" data-sqm_per_box="<?php echo htmlspecialchars($s['sqm_per_box']); ?>" <?php if($item_data['size_id'] == $s['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['size_prefix'] . " [" . $s['size_text'] . "]"); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="item_product_id[]" class="form-control item-product" required>
                            <option value="">-- Select Product --</option>
                            <?php if(isset($item_data['product_id']) && !empty($item_data['product_id'])): ?>
                                <option value="<?php echo htmlspecialchars($item_data['product_id']); ?>" selected><?php echo $item_data['product_design_name_display'] ?? '(Product ID: '.htmlspecialchars($item_data['product_id']).')'; ?></option>
                            <?php endif; ?>
                        </select>
                    </td>
                    <td><input type="text" name="item_description[]" class="form-control item-description" value="<?php echo htmlspecialchars($item_data['description']); ?>"></td>
                    <td><input type="number" name="item_weight_per_box[]" class="form-control item-weight" step="0.01" value="<?php echo htmlspecialchars($item_data['weight_per_box']); ?>"></td>
                    <td><input type="number" name="item_boxes[]" class="form-control item-boxes" step="0.01" value="<?php echo htmlspecialchars($item_data['boxes']); ?>" required></td>
                    <td><input type="text" name="item_thickness[]" class="form-control item-thickness" value="<?php echo htmlspecialchars($item_data['thickness']); ?>"></td>
                    <td><button type="button" class="btn btn-danger btn-sm remove_po_item_row">Del</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="btn btn-success mt-2" id="add_po_item_row">Add New Item</button>

        <div class="row mt-3">
            <div class="col-md-12 text-right">
                <h4>Total Boxes: <span id="total_boxes_display">0.00</span></h4>
            </div>
        </div>

        <!-- Hidden template for PO item rows -->
        <table style="display:none;">
            <tr id="po_item_template_row" class="po-item-row">
                <td>
                    <select name="item_size_id[]" class="form-control item-size" disabled>
                        <option value="">-- Select Size --</option>
                        <?php foreach($all_sizes_for_new_items as $size_item_tpl): // New rows use all sizes ?>
                        <option value="<?php echo $size_item_tpl['id']; ?>" data-sqm_per_box="<?php echo htmlspecialchars($size_item_tpl['sqm_per_box']); ?>">
                            <?php echo htmlspecialchars($size_item_tpl['size_prefix'] . " [" . $size_item_tpl['size_text'] . "]"); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="item_product_id[]" class="form-control item-product" disabled>
                        <option value="">-- Select Product --</option>
                    </select>
                </td>
                <td><input type="text" name="item_description[]" class="form-control item-description" value="<?php echo htmlspecialchars($default_item_description); ?>" disabled></td>
                <td><input type="number" name="item_weight_per_box[]" class="form-control item-weight" step="0.01" placeholder="Wt/Box" disabled></td>
                <td><input type="number" name="item_boxes[]" class="form-control item-boxes" step="0.01" placeholder="Boxes" disabled></td>
                <td><input type="text" name="item_thickness[]" class="form-control item-thickness" value="<?php echo htmlspecialchars($default_item_thickness); ?>" disabled></td>
                <td><button type="button" class="btn btn-danger btn-sm remove_po_item_row">Del</button></td>
            </tr>
        </table>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Create Purchase Order">
            <a href="../performa_invoices/performa_invoice_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsTableBody = document.getElementById('po_items_table_body');
    const addItemButton = document.getElementById('add_po_item_row');
    const templateRowHTML = document.getElementById('po_item_template_row').innerHTML;
    const totalBoxesDisplay = document.getElementById('total_boxes_display');

    function calculateTotalBoxes() {
        let total = 0;
        itemsTableBody.querySelectorAll('.po-item-row .item-boxes').forEach(function(boxesInput) {
            const val = parseFloat(boxesInput.value);
            if (!isNaN(val) && val > 0) {
                total += val;
            }
        });
        totalBoxesDisplay.textContent = total.toFixed(2);
    }

    function attachRowEventListeners(rowElement) {
        const sizeSelect = rowElement.querySelector('.item-size');
        const productSelect = rowElement.querySelector('.item-product');
        const weightInput = rowElement.querySelector('.item-weight');
        const boxesInput = rowElement.querySelector('.item-boxes');

        if (sizeSelect) {
            sizeSelect.addEventListener('change', function() {
                const selectedSizeId = this.value;
                productSelect.innerHTML = '<option value="">-- Loading Products --</option>';
                if(weightInput) weightInput.value = '';

                if (selectedSizeId) {
                    // Use the PI ajax helper as it fetches products by size
                    fetch(`../performa_invoices/ajax_get_products_by_size.php?size_id=${selectedSizeId}`)
                        .then(response => response.json())
                        .then(data => {
                            productSelect.innerHTML = '<option value="">-- Select Product --</option>';
                            if (data.success && data.products.length > 0) {
                                data.products.forEach(product => {
                                    const option = document.createElement('option');
                                    option.value = product.id;
                                    option.textContent = product.design_name;
                                    // Store other product details if needed, e.g., default weight
                                    // option.dataset.weight_per_box = product.effective_box_weight; // If ajax_get_products_by_size provided this
                                    productSelect.appendChild(option);
                                });
                            }
                        }).catch(err => {
                            console.error("AJAX error for products:", err);
                            productSelect.innerHTML = '<option value="">-- Error --</option>';
                        });
                }
            });
        }

        if (productSelect && weightInput) { // If weight input exists
            productSelect.addEventListener('change', function() {
                const selectedProductId = this.value;
                const currentSizeId = sizeSelect ? sizeSelect.value : null;
                weightInput.value = ''; // Clear previous weight

                if (selectedProductId && currentSizeId) {
                    fetch(`ajax_get_product_details_for_po.php?product_id=${selectedProductId}&size_id=${currentSizeId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data && data.data.weight_per_box !== null) {
                                weightInput.value = parseFloat(data.data.weight_per_box).toFixed(2);
                            } else {
                                console.error("Error fetching weight or no weight defined:", data.error);
                            }
                        }).catch(err => console.error("AJAX error for weight:", err));
                }
            });
        }

        if (boxesInput) {
            boxesInput.addEventListener('input', calculateTotalBoxes);
        }

        const removeButton = rowElement.querySelector('.remove_po_item_row');
        if (removeButton) {
            removeButton.addEventListener('click', function() {
                rowElement.remove();
                calculateTotalBoxes();
            });
        }
         // Trigger change on size for existing rows to populate products
        if (sizeSelect.value && productSelect.options.length <=1) { // If size selected and product not populated
            // For pre-filled rows from PI, we need to select the product after products are loaded.
            // This requires knowing the product ID that should be selected for this row.
            // The PHP part for pre-filling items should store this product ID.
            const preselectedProductId = productSelect.querySelector('option[selected]')?.value;

            sizeSelect.dispatchEvent(new Event('change')); // This will load products

            if(preselectedProductId){
                // Need to wait for products to load, then select. This is tricky.
                // A MutationObserver or a timeout could work.
                setTimeout(() => {
                    productSelect.value = preselectedProductId;
                    if(productSelect.value === preselectedProductId){ // if selection worked
                         productSelect.dispatchEvent(new Event('change')); // to trigger weight fetch
                    } else {
                        // If the product ID from PI is not in the list for that size, it remains "--Select Product--"
                        // This can happen if product's size was changed in products module after PI was made.
                    }
                }, 1000); // Delay to allow products to load. Adjust as needed.
            }
        }


    } // end attachRowEventListeners

    if (addItemButton) {
        addItemButton.addEventListener('click', function() {
            const newRow = itemsTableBody.insertRow();
            newRow.className = 'po-item-row';
            newRow.innerHTML = templateRowHTML;

            newRow.querySelector('.item-size').disabled = false;
            newRow.querySelector('.item-size').required = true;
            newRow.querySelector('.item-product').disabled = false;
            newRow.querySelector('.item-product').required = true;
            newRow.querySelector('.item-description').disabled = false;
            newRow.querySelector('.item-weight').disabled = false;
            newRow.querySelector('.item-boxes').disabled = false;
            newRow.querySelector('.item-boxes').required = true;
            newRow.querySelector('.item-thickness').disabled = false;

            attachRowEventListeners(newRow);
        });
    }

    // Attach listeners to initially loaded rows (pre-filled from PI)
    itemsTableBody.querySelectorAll('.po-item-row').forEach(attachRowEventListeners);
    calculateTotalBoxes(); // Initial calculation

     // Add one empty row if no items were pre-filled (e.g. PI had no items)
    if (itemsTableBody.querySelectorAll('.po-item-row').length === 0) {
        addItemButton.click();
    }

});
</script>

<?php require_once '../../includes/footer.php'; ?>
