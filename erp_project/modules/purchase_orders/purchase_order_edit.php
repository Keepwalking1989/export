<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// --- Initialize variables ---
$po_id_from_get = null; // ID of the PO being edited
$po_header_data = [];
$po_items_data = [];
$source_pi_number_display = ''; // To display the source PI number

$errors = [];
$item_errors = [];

// --- Fetch data for dropdowns ---
$all_exporters = []; // Fetch all for dropdown, pre-select later
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

$all_sizes_for_new_items = [];
$sql_all_sizes = "SELECT id, size_text, size_prefix, sqm_per_box FROM sizes ORDER BY size_prefix, size_text ASC";
$result_all_sizes = $conn->query($sql_all_sizes);
if ($result_all_sizes && $result_all_sizes->num_rows > 0) {
    while ($row = $result_all_sizes->fetch_assoc()) $all_sizes_for_new_items[] = $row;
}

$default_item_description = "As Per Sample / Master";
$default_item_thickness = "8.7 MM to 9.0 MM";

// --- POST Request Processing (Update PO) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id']) && !empty(trim($_POST['id']))) {
    $po_id_from_get = (int)trim($_POST['id']); // Get PO ID from hidden form field

    // Assign Header POST values
    $po_header_data['id'] = $po_id_from_get; // Keep track of the ID for update
    $po_header_data['performa_invoice_id'] = trim($_POST['performa_invoice_id']); // This should be from a hidden field, not editable
    $po_header_data['exporter_id'] = trim($_POST['exporter_id']);
    $po_header_data['manufacturer_id'] = trim($_POST['manufacturer_id']);
    $po_header_data['po_number'] = trim($_POST['po_number']);
    $po_header_data['po_date'] = trim($_POST['po_date']);
    $po_header_data['number_of_containers'] = trim($_POST['number_of_containers']);

    // --- Header Validations ---
    if (empty($po_header_data['exporter_id'])) $errors['exporter_id'] = "Exporter is required.";
    if (empty($po_header_data['manufacturer_id'])) $errors['manufacturer_id'] = "Manufacturer is required.";
    if (empty($po_header_data['po_number'])) $errors['po_number'] = "PO number is required.";
    else {
        $sql_check_po_num = "SELECT id FROM purchase_orders WHERE po_number = ? AND id != ? LIMIT 1";
        if($stmt_check_po_num = $conn->prepare($sql_check_po_num)){
            $stmt_check_po_num->bind_param("si", $po_header_data['po_number'], $po_id_from_get);
            $stmt_check_po_num->execute();
            $stmt_check_po_num->store_result();
            if($stmt_check_po_num->num_rows > 0) $errors['po_number'] = "This PO number already exists for another PO.";
            $stmt_check_po_num->close();
        }
    }
    if (empty($po_header_data['po_date'])) $errors['po_date'] = "PO date is required.";
    // --- End Header Validations ---

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sql_po_header_update = "UPDATE purchase_orders SET
                                        exporter_id=?, manufacturer_id=?, po_number=?,
                                        po_date=?, number_of_containers=?
                                     WHERE id=?";
            if ($stmt_po_header = $conn->prepare($sql_po_header_update)) {
                $stmt_po_header->bind_param("iisssi",
                    $po_header_data['exporter_id'], $po_header_data['manufacturer_id'],
                    $po_header_data['po_number'], $po_header_data['po_date'],
                    $po_header_data['number_of_containers'],
                    $po_id_from_get
                );
                if (!$stmt_po_header->execute()) {
                    throw new Exception($conn->errno == 1062 ? "DB Error: PO Number already exists." : "DB error updating PO header: " . $stmt_po_header->error);
                }
                $stmt_po_header->close();

                // Process Items: Delete existing items then re-insert all submitted items
                $sql_delete_items = "DELETE FROM purchase_order_items WHERE purchase_order_id = ?";
                if($stmt_delete = $conn->prepare($sql_delete_items)){
                    $stmt_delete->bind_param("i", $po_id_from_get);
                    if(!$stmt_delete->execute()){ throw new Exception("Error deleting existing PO items: ".$stmt_delete->error); }
                    $stmt_delete->close();
                } else { throw new Exception("Error preparing to delete PO items: ".$conn->error); }

                $item_size_ids = $_POST['item_size_id'] ?? [];
                // ... (rest of item array fetching as in _add.php) ...
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
                    if(!$stmt_po_item) throw new Exception("Error preparing PO item insert: ".$conn->error);

                    for ($i = 0; $i < count($item_size_ids); $i++) {
                        // ... (validation for each item as in _add.php) ...
                         if (empty($item_size_ids[$i]) || empty($item_product_ids[$i]) || !isset($item_boxes_arr[$i]) || $item_boxes_arr[$i] === '') {
                            $item_errors[] = "Error in item #".($i+1).": Size, Product, and Boxes are required.";
                            continue;
                        }
                        $param_weight_pb = !empty($item_weights_per_box[$i]) ? (float)$item_weights_per_box[$i] : null;
                        $param_boxes = (float)$item_boxes_arr[$i];
                        $param_desc = !empty($item_descriptions[$i]) ? $item_descriptions[$i] : $default_item_description;
                        $param_thick = !empty($item_thickness_arr[$i]) ? $item_thickness_arr[$i] : $default_item_thickness;

                        $stmt_po_item->bind_param("iiisdds",
                            $po_id_from_get, $item_size_ids[$i], $item_product_ids[$i],
                            $param_desc, $param_weight_pb, $param_boxes, $param_thick
                        );
                        if (!$stmt_po_item->execute()) {
                            $item_errors[] = "Error saving PO item #".($i+1).": " . $stmt_po_item->error;
                        }
                    }
                    $stmt_po_item->close();
                }
                if (!empty($item_errors)) {
                    throw new Exception("Errors occurred while processing PO items: " . implode("; ", $item_errors));
                }
                $conn->commit();
                header("location: purchase_order_list.php?status=success_edit&id=" . $po_id_from_get);
                exit();
            } else { // Header prepare failed
                throw new Exception("Error preparing PO header update statement: " . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors['db_error'] = $e->getMessage();
        }
    } else { // Header validation errors
        // Repopulate items if POST failed due to header errors
        $posted_item_size_ids = $_POST['item_size_id'] ?? [];
        if (!empty($posted_item_size_ids)) {
            for ($i=0; $i < count($posted_item_size_ids); $i++) {
                $po_items_data[] = [ // Store submitted item data to repopulate form
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
}
// --- End of POST ---
// --- GET Request: Load PO data for editing ---
elseif (isset($_GET['id']) && !empty(trim($_GET['id'])) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $po_id_from_get = (int)trim($_GET['id']);
    $sql_fetch_po_header = "SELECT po.*, pi.invoice_number as source_pi_number
                           FROM purchase_orders po
                           JOIN performa_invoices pi ON po.performa_invoice_id = pi.id
                           WHERE po.id = ?";
    if ($stmt_po_h = $conn->prepare($sql_fetch_po_header)) {
        $stmt_po_h->bind_param("i", $po_id_from_get);
        $stmt_po_h->execute();
        $res_po_h = $stmt_po_h->get_result();
        if ($res_po_h->num_rows == 1) {
            $po_header_data = $res_po_h->fetch_assoc();
            $source_pi_number_display = $po_header_data['source_pi_number']; // For display

            // Fetch existing PO Items
            $sql_po_items_fetch = "SELECT poi.*, s.size_text, s.size_prefix, p.design_name
                                 FROM purchase_order_items poi
                                 JOIN sizes s ON poi.size_id = s.id
                                 JOIN products p ON poi.product_id = p.id
                                 WHERE poi.purchase_order_id = ? ORDER BY poi.id ASC";
            if($stmt_po_items_fetch = $conn->prepare($sql_po_items_fetch)){
                $stmt_po_items_fetch->bind_param("i", $po_id_from_get);
                $stmt_po_items_fetch->execute();
                $res_po_items = $stmt_po_items_fetch->get_result();
                while($item = $res_po_items->fetch_assoc()){
                     $item['size_text_display'] = htmlspecialchars($item['size_prefix'] . " [" . $item['size_text'] . "]");
                     $item['product_design_name_display'] = htmlspecialchars($item['design_name']);
                    $po_items_data[] = $item;
                }
                $stmt_po_items_fetch->close();
            } else { $errors['load_error'] = "Error fetching PO items: ".$conn->error; }
        } else { $errors['load_error'] = "Purchase Order not found."; }
        $stmt_po_h->close();
    } else { $errors['load_error'] = "Error preparing to fetch PO: ".$conn->error; }
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
    $errors['load_error'] = "No Purchase Order ID specified for editing.";
}
// --- End of GET ---

require_once '../../includes/header.php';

if ($_SERVER["REQUEST_METHOD"] != "POST" && !empty($errors['load_error'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>" . htmlspecialchars($errors['load_error']) . " <a href='purchase_order_list.php' class='alert-link'>Back to List</a>.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>
<div class="container">
    <h2>Edit Purchase Order - ID: <?php echo htmlspecialchars($po_id_from_get); ?></h2>
    <?php if($source_pi_number_display): ?>
        <p class="text-muted">Source Performa Invoice: <?php echo htmlspecialchars($source_pi_number_display); ?></p>
    <?php endif; ?>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo $errors['db_error']; ?></div>
    <?php endif; ?>
     <?php if (($_SERVER["REQUEST_METHOD"] == "POST") && !empty($errors['load_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="purchaseOrderForm">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($po_header_data['id'] ?? $po_id_from_get); ?>">
        <input type="hidden" name="performa_invoice_id" value="<?php echo htmlspecialchars($po_header_data['performa_invoice_id'] ?? ''); ?>">

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
                    <input type="text" name="po_number" class="form-control <?php echo isset($errors['po_number']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($po_header_data['po_number'] ?? ''); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['po_number'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>PO Date <span class="text-danger">*</span></label>
                    <input type="date" name="po_date" class="form-control <?php echo isset($errors['po_date']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($po_header_data['po_date'] ?? ''); ?>" required>
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
                            <?php foreach($all_sizes_for_new_items as $s): // All sizes available for edit ?>
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
                    <td><input type="text" name="item_description[]" class="form-control item-description" value="<?php echo htmlspecialchars($item_data['description'] ?? $default_item_description); ?>"></td>
                    <td><input type="number" name="item_weight_per_box[]" class="form-control item-weight" step="0.01" value="<?php echo htmlspecialchars($item_data['weight_per_box'] ?? ''); ?>"></td>
                    <td><input type="number" name="item_boxes[]" class="form-control item-boxes" step="0.01" value="<?php echo htmlspecialchars($item_data['boxes'] ?? ''); ?>" required></td>
                    <td><input type="text" name="item_thickness[]" class="form-control item-thickness" value="<?php echo htmlspecialchars($item_data['thickness'] ?? $default_item_thickness); ?>"></td>
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

        <table style="display:none;">
            <tr id="po_item_template_row" class="po-item-row">
                <td>
                    <select name="item_size_id[]" class="form-control item-size" disabled>
                        <option value="">-- Select Size --</option>
                        <?php foreach($all_sizes_for_new_items as $size_item_tpl): ?>
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
                <td><input type="number" name="item_weight_per_box[]" class="form-control item-weight" step="0.01" placeholder="Wt./Box" disabled></td>
                <td><input type="number" name="item_boxes[]" class="form-control item-boxes" step="0.01" placeholder="Boxes" disabled></td>
                <td><input type="text" name="item_thickness[]" class="form-control item-thickness" value="<?php echo htmlspecialchars($default_item_thickness); ?>" disabled></td>
                <td><button type="button" class="btn btn-danger btn-sm remove_po_item_row">Del</button></td>
            </tr>
        </table>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Update Purchase Order">
            <a href="purchase_order_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
// Similar JS to po_add.php, with adjustments for edit if needed
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

    function attachRowEventListeners(rowElement, isExistingRow = false) {
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
                    fetch(`../performa_invoices/ajax_get_products_by_size.php?size_id=${selectedSizeId}`)
                        .then(response => response.json())
                        .then(data => {
                            productSelect.innerHTML = '<option value="">-- Select Product --</option>';
                            if (data.success && data.products.length > 0) {
                                data.products.forEach(product => {
                                    const option = document.createElement('option');
                                    option.value = product.id;
                                    option.textContent = product.design_name;
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

        if (productSelect && weightInput) {
            productSelect.addEventListener('change', function() {
                const selectedProductId = this.value;
                const currentSizeId = sizeSelect ? sizeSelect.value : null;
                weightInput.value = '';

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

        // For existing rows, trigger change on size to populate products and then try to reselect product
        if(isExistingRow && sizeSelect.value){
            const preselectedProductId = productSelect.value; // The value PHP set for product_id

            // Temporarily disable product change listener to avoid it clearing weight on product re-selection
            const productChangeListener = productSelect.listeners && productSelect.listeners.change;
            if(productChangeListener) productSelect.removeEventListener('change', productChangeListener[0]);


            sizeSelect.dispatchEvent(new Event('change')); // Load products for this size

            setTimeout(() => {
                productSelect.value = preselectedProductId;
                // Re-attach listener if it was removed
                // if(productChangeListener) productSelect.addEventListener('change', productChangeListener[0]);

                // If product got selected, its own change event (if re-attached properly) should fetch weight.
                // Or, if weight was already pre-filled by PHP, it's fine.
                // The current PHP pre-fills weight from DB, so it should be okay.
                // We might need to trigger product change if weight needs re-fetch after product list re-population
                if (productSelect.value === preselectedProductId && weightInput) {
                     // If weightInput is empty, it means it wasn't pre-filled from DB or product's default.
                     // This implies it should be fetched or was intended to be empty.
                     // The current PHP for edit pre-fills weightInput from $item_data['weight_per_box']
                }
                 calculateTotalBoxes(); // Ensure totals are up-to-date
            }, 1200); // Increased delay
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

            attachRowEventListeners(newRow, false); // false for isExistingRow
        });
    }

    itemsTableBody.querySelectorAll('.po-item-row').forEach(row => attachRowEventListeners(row, true)); // true for isExistingRow
    calculateTotalBoxes();

    if (itemsTableBody.querySelectorAll('.po-item-row').length === 0 && addItemButton) {
       // addItemButton.click(); // Optionally add a blank row if no items are loaded for edit
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
