<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/db_connect.php';

// --- Initial Data Fetching for Dropdowns ---
$exporters = [];
$sql_exporters = "SELECT id, company_name FROM exporters ORDER BY company_name ASC";
$result_exporters = $conn->query($sql_exporters);
if ($result_exporters && $result_exporters->num_rows > 0) {
    while ($row = $result_exporters->fetch_assoc()) $exporters[] = $row;
}

$clients = [];
$sql_clients = "SELECT id, name FROM clients ORDER BY name ASC";
$result_clients = $conn->query($sql_clients);
if ($result_clients && $result_clients->num_rows > 0) {
    while ($row = $result_clients->fetch_assoc()) $clients[] = $row;
}

$banks = [];
$sql_banks = "SELECT id, bank_name, account_number FROM banks ORDER BY bank_name ASC";
$result_banks = $conn->query($sql_banks);
if ($result_banks && $result_banks->num_rows > 0) {
    while ($row = $result_banks->fetch_assoc()) $banks[] = $row;
}

$sizes_dropdown_data = [];
$sql_sizes_all = "SELECT id, size_text, size_prefix, sqm_per_box FROM sizes ORDER BY size_prefix, size_text ASC";
$result_sizes_all = $conn->query($sql_sizes_all);
if ($result_sizes_all && $result_sizes_all->num_rows > 0) {
    while ($row_s = $result_sizes_all->fetch_assoc()) {
        $sizes_dropdown_data[] = $row_s;
    }
}

// --- Initialize variables ---
$pi_id = null;
$exporter_id = $invoice_number = $invoice_date = $consignee_id = $final_destination = "";
$total_container = $container_size = $currency_type = $total_gross_weight_kg = "";
$bank_id = $freight_amount = $discount_amount = "";
$notify_party_line1 = $notify_party_line2 = $terms_delivery_payment = $note = "";
$errors = [];
$item_errors = [];
$invoice_items_data = [];

// --- POST Request Processing (Update) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["id"]) && !empty(trim($_POST["id"]))) {
    $pi_id = trim($_POST['id']);
    $exporter_id = trim($_POST['exporter_id']);
    $invoice_number = trim($_POST['invoice_number']);
    $invoice_date = trim($_POST['invoice_date']);
    $consignee_id = trim($_POST['consignee_id']);
    $final_destination = trim($_POST['final_destination']);
    $total_container = trim($_POST['total_container']);
    $container_size = trim($_POST['container_size']);
    $currency_type = trim($_POST['currency_type']);
    $total_gross_weight_kg = trim($_POST['total_gross_weight_kg']);
    $bank_id = trim($_POST['bank_id']);
    $freight_amount = trim($_POST['freight_amount']);
    $discount_amount = trim($_POST['discount_amount']);
    $notify_party_line1 = trim($_POST['notify_party_line1']);
    $notify_party_line2 = trim($_POST['notify_party_line2']);
    $terms_delivery_payment = trim($_POST['terms_delivery_payment']);
    $note = trim($_POST['note']);

    if (empty($exporter_id)) $errors['exporter_id'] = "Exporter is required.";
    if (empty($invoice_number)) $errors['invoice_number'] = "Invoice number is required.";
    else {
        $sql_check_inv = "SELECT id FROM performa_invoices WHERE invoice_number = ? AND id != ? LIMIT 1";
        if($stmt_check_inv = $conn->prepare($sql_check_inv)){
            $stmt_check_inv->bind_param("si", $invoice_number, $pi_id);
            $stmt_check_inv->execute();
            $stmt_check_inv->store_result();
            if($stmt_check_inv->num_rows > 0) $errors['invoice_number'] = "This invoice number already exists for another PI.";
            $stmt_check_inv->close();
        }
    }
    if (empty($invoice_date)) $errors['invoice_date'] = "Invoice date is required.";
    if (empty($consignee_id)) $errors['consignee_id'] = "Consignee is required.";

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sql_update_header = "UPDATE performa_invoices SET
                            exporter_id=?, invoice_number=?, invoice_date=?, consignee_id=?, final_destination=?,
                            total_container=?, container_size=?, currency_type=?, total_gross_weight_kg=?,
                            bank_id=?, freight_amount=?, discount_amount=?, notify_party_line1=?,
                            notify_party_line2=?, terms_delivery_payment=?, note=?
                           WHERE id=?";

            if ($stmt_header = $conn->prepare($sql_update_header)) {
                $stmt_header->bind_param("ississssdiddssssi",
                    $exporter_id, $invoice_number, $invoice_date, $consignee_id, $final_destination,
                    $total_container, $container_size, $currency_type, $param_total_gross_weight_kg,
                    $param_bank_id, $param_freight_amount, $param_discount_amount, $notify_party_line1,
                    $notify_party_line2, $terms_delivery_payment, $note,
                    $pi_id
                );

                $param_total_gross_weight_kg = !empty($total_gross_weight_kg) ? (float)$total_gross_weight_kg : null;
                $param_bank_id = !empty($bank_id) ? (int)$bank_id : null;
                $param_freight_amount = !empty($freight_amount) ? (float)$freight_amount : null;
                $param_discount_amount = !empty($discount_amount) ? (float)$discount_amount : null;

                if (!$stmt_header->execute()) {
                    throw new Exception($conn->errno == 1062 ? "DB Error: Invoice number already exists." : "DB error updating PI header: " . $stmt_header->error);
                }
                $stmt_header->close();

                $sql_delete_items = "DELETE FROM performa_invoice_items WHERE performa_invoice_id = ?";
                if ($stmt_delete = $conn->prepare($sql_delete_items)) {
                    $stmt_delete->bind_param("i", $pi_id);
                    if(!$stmt_delete->execute()){
                        throw new Exception("Error deleting existing items: " . $stmt_delete->error);
                    }
                    $stmt_delete->close();
                } else {
                    throw new Exception("Error preparing to delete items: " . $conn->error);
                }

                $item_size_ids = $_POST['item_size_id'] ?? [];
                $item_product_ids = $_POST['item_product_id'] ?? [];
                $item_boxes_arr = $_POST['item_boxes'] ?? [];
                $item_rates_arr = $_POST['item_rate_per_sqm'] ?? [];
                $item_commissions_arr = $_POST['item_commission_percentage'] ?? [];

                if (!empty($item_size_ids)) {
                    $sql_item_insert = "INSERT INTO performa_invoice_items
                                        (performa_invoice_id, size_id, product_id, boxes, rate_per_sqm, commission_percentage, quantity_sqm, amount)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_item = $conn->prepare($sql_item_insert);
                    if (!$stmt_item) throw new Exception("Error preparing item insert statement: " . $conn->error);

                    for ($i = 0; $i < count($item_size_ids); $i++) {
                        if (empty($item_size_ids[$i]) || empty($item_product_ids[$i]) || !isset($item_boxes_arr[$i]) || $item_boxes_arr[$i] === '' || !isset($item_rates_arr[$i]) || $item_rates_arr[$i] === '') {
                            $item_errors[] = "Error in item #".($i+1).": Size, Product, Boxes, and Rate are required.";
                            continue;
                        }
                        $item_size_id_val = (int)$item_size_ids[$i];
                        $item_product_id_val = (int)$item_product_ids[$i];
                        $item_boxes_val = (float)$item_boxes_arr[$i];
                        $item_rate_val = (float)$item_rates_arr[$i];
                        $item_commission_val = !empty($item_commissions_arr[$i]) ? (float)$item_commissions_arr[$i] : null;

                        $sqm_per_box_item = 0;
                        $sql_get_sqm_edit = "SELECT sqm_per_box FROM sizes WHERE id = ? LIMIT 1";
                        if($stmt_get_sqm_edit = $conn->prepare($sql_get_sqm_edit)){
                            $stmt_get_sqm_edit->bind_param("i", $item_size_id_val);
                            $stmt_get_sqm_edit->execute();
                            $res_sqm_edit = $stmt_get_sqm_edit->get_result();
                            if ($res_sqm_edit->num_rows > 0) {
                                $sqm_per_box_item = (float)$res_sqm_edit->fetch_assoc()['sqm_per_box'];
                            }
                            $stmt_get_sqm_edit->close();
                        }

                        if ($sqm_per_box_item <= 0) {
                            $item_errors[] = "Error in item #".($i+1).": Invalid SQM per Box for selected size.";
                            continue;
                        }

                        $item_quantity_sqm = $item_boxes_val * $sqm_per_box_item;
                        $item_amount = $item_quantity_sqm * $item_rate_val;

                        $stmt_item->bind_param("iiiddddd",
                            $pi_id, $item_size_id_val, $item_product_id_val,
                            $item_boxes_val, $item_rate_val, $item_commission_val,
                            $item_quantity_sqm, $item_amount
                        );
                        if (!$stmt_item->execute()) {
                            $item_errors[] = "Error saving item #".($i+1).": " . $stmt_item->error;
                        }
                    }
                    $stmt_item->close();
                }

                if (!empty($item_errors)) {
                    throw new Exception("Errors occurred while processing items: " . implode("; ", $item_errors));
                }

                $conn->commit();
                header("location: performa_invoice_list.php?status=success_edit&id=" . $pi_id);
                exit();

            } else {
                throw new Exception("Error preparing PI header update: " . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors['db_error'] = $e->getMessage();
        }
    }
    else {
        $posted_item_size_ids = $_POST['item_size_id'] ?? [];
        if (!empty($posted_item_size_ids)) {
            for ($i=0; $i < count($posted_item_size_ids); $i++) {
                $invoice_items_data[] = [
                    'size_id' => $_POST['item_size_id'][$i] ?? '',
                    'product_id' => $_POST['item_product_id'][$i] ?? '',
                    'boxes' => $_POST['item_boxes'][$i] ?? '',
                    'rate_per_sqm' => $_POST['item_rate_per_sqm'][$i] ?? '',
                    'commission_percentage' => $_POST['item_commission_percentage'][$i] ?? ''
                    // Note: We don't repopulate calculated fields here for sticky, JS will do it.
                ];
            }
        }
    }
}
elseif (isset($_GET["id"]) && !empty(trim($_GET["id"])) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $pi_id = trim($_GET["id"]);
    $sql_fetch_header = "SELECT * FROM performa_invoices WHERE id = ?";
    if ($stmt_fetch_header = $conn->prepare($sql_fetch_header)) {
        $stmt_fetch_header->bind_param("i", $pi_id);
        if ($stmt_fetch_header->execute()) {
            $result_header = $stmt_fetch_header->get_result();
            if ($result_header->num_rows == 1) {
                $pi_data = $result_header->fetch_assoc();
                $exporter_id = $pi_data['exporter_id'];
                $invoice_number = $pi_data['invoice_number'];
                $invoice_date = $pi_data['invoice_date'];
                $consignee_id = $pi_data['consignee_id'];
                $final_destination = $pi_data['final_destination'];
                $total_container = $pi_data['total_container'];
                $container_size = $pi_data['container_size'];
                $currency_type = $pi_data['currency_type'];
                $total_gross_weight_kg = $pi_data['total_gross_weight_kg'];
                $bank_id = $pi_data['bank_id'];
                $freight_amount = $pi_data['freight_amount'];
                $discount_amount = $pi_data['discount_amount'];
                $notify_party_line1 = $pi_data['notify_party_line1'];
                $notify_party_line2 = $pi_data['notify_party_line2'];
                $terms_delivery_payment = $pi_data['terms_delivery_payment'];
                $note = $pi_data['note'];

                $sql_fetch_items = "SELECT * FROM performa_invoice_items WHERE performa_invoice_id = ? ORDER BY id ASC";
                if($stmt_fetch_items = $conn->prepare($sql_fetch_items)){
                    $stmt_fetch_items->bind_param("i", $pi_id);
                    $stmt_fetch_items->execute();
                    $result_items = $stmt_fetch_items->get_result();
                    while($item_row = $result_items->fetch_assoc()){
                        $invoice_items_data[] = $item_row;
                    }
                    $stmt_fetch_items->close();
                } else {
                     $errors['load_error'] = "Error fetching PI items: " . $conn->error;
                }
            } else {
                $errors['load_error'] = "Error: Performa Invoice not found for ID " . htmlspecialchars($pi_id) . ".";
            }
        } else {
            $errors['load_error'] = "Error fetching PI header data: " . $stmt_fetch_header->error;
        }
        $stmt_fetch_header->close();
    } else {
        $errors['load_error'] = "Error preparing PI header fetch: " . $conn->error;
    }
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
     $errors['load_error'] = "No Performa Invoice ID specified for editing.";
}

require_once '../../includes/header.php';

if ($_SERVER["REQUEST_METHOD"] != "POST" && !empty($errors['load_error'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>" . htmlspecialchars($errors['load_error']) . " <a href='performa_invoice_list.php' class='alert-link'>Back to List</a>.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <h2>Edit Performa Invoice - ID: <?php echo htmlspecialchars($pi_id); ?></h2>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo $errors['db_error']; ?></div>
    <?php endif; ?>
    <?php if (($_SERVER["REQUEST_METHOD"] == "POST") && !empty($errors['load_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="performaInvoiceForm">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($pi_id); ?>"/>

        <h4 class="mt-3">Invoice Header</h4>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Exporter <span class="text-danger">*</span></label>
                    <select name="exporter_id" class="form-control <?php echo isset($errors['exporter_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">-- Select Exporter --</option>
                        <?php foreach($exporters as $exp): ?>
                        <option value="<?php echo $exp['id']; ?>" <?php if($exporter_id == $exp['id']) echo 'selected'; ?>><?php echo htmlspecialchars($exp['company_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"><?php echo $errors['exporter_id'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Invoice Number <span class="text-danger">*</span></label>
                    <input type="text" name="invoice_number" class="form-control <?php echo isset($errors['invoice_number']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($invoice_number); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['invoice_number'] ?? '';?></span>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Invoice Date <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" class="form-control <?php echo isset($errors['invoice_date']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($invoice_date); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['invoice_date'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Consignee (Client) <span class="text-danger">*</span></label>
                    <select name="consignee_id" class="form-control <?php echo isset($errors['consignee_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">-- Select Consignee --</option>
                         <?php foreach($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>" <?php if($consignee_id == $client['id']) echo 'selected'; ?>><?php echo htmlspecialchars($client['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"><?php echo $errors['consignee_id'] ?? '';?></span>
                </div>
            </div>
        </div>
         <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Final Destination</label>
                    <input type="text" name="final_destination" class="form-control" value="<?php echo htmlspecialchars($final_destination); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Total Container(s)</label>
                    <input type="text" name="total_container" class="form-control" value="<?php echo htmlspecialchars($total_container); ?>" placeholder="e.g., 1x20 FT, 2x40 HC">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Container Size</label>
                    <select name="container_size" class="form-control">
                        <option value="20" <?php if($container_size == '20') echo 'selected'; ?>>20 FT</option>
                        <option value="40" <?php if($container_size == '40') echo 'selected'; ?>>40 FT</option>
                        <option value="40 HC" <?php if($container_size == '40 HC') echo 'selected'; ?>>40 FT HC</option>
                        <option value="LCL" <?php if($container_size == 'LCL') echo 'selected'; ?>>LCL</option>
                        <option value="Other" <?php if($container_size == 'Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Currency Type</label>
                    <select name="currency_type" class="form-control">
                        <option value="USD" <?php if($currency_type == 'USD') echo 'selected'; ?>>USD</option>
                        <option value="EUR" <?php if($currency_type == 'EUR') echo 'selected'; ?>>EUR</option>
                        <option value="INR" <?php if($currency_type == 'INR') echo 'selected'; ?>>INR</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Total Gross Weight (KG)</label>
                    <input type="number" step="0.01" name="total_gross_weight_kg" class="form-control <?php echo isset($errors['total_gross_weight_kg']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($total_gross_weight_kg); ?>">
                     <span class="invalid-feedback"><?php echo $errors['total_gross_weight_kg'] ?? '';?></span>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Bank</label>
                    <select name="bank_id" class="form-control">
                        <option value="">-- Select Bank (Optional) --</option>
                         <?php foreach($banks as $bank): ?>
                        <option value="<?php echo $bank['id']; ?>" <?php if($bank_id == $bank['id']) echo 'selected'; ?>><?php echo htmlspecialchars($bank['bank_name'] . ' - ' . $bank['account_number']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
         <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Freight Amount</label>
                    <input type="number" step="0.01" name="freight_amount" class="form-control <?php echo isset($errors['freight_amount']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($freight_amount); ?>">
                     <span class="invalid-feedback"><?php echo $errors['freight_amount'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Discount Amount</label>
                    <input type="number" step="0.01" name="discount_amount" class="form-control <?php echo isset($errors['discount_amount']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($discount_amount); ?>">
                     <span class="invalid-feedback"><?php echo $errors['discount_amount'] ?? '';?></span>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Notify Party Line 1</label>
            <textarea name="notify_party_line1" class="form-control" rows="2"><?php echo htmlspecialchars($notify_party_line1); ?></textarea>
        </div>
        <div class="form-group">
            <label>Notify Party Line 2</label>
            <textarea name="notify_party_line2" class="form-control" rows="2"><?php echo htmlspecialchars($notify_party_line2); ?></textarea>
        </div>
        <div class="form-group">
            <label>Terms & Conditions of Delivery & Payment</label>
            <textarea name="terms_delivery_payment" class="form-control" rows="3"><?php echo htmlspecialchars($terms_delivery_payment); ?></textarea>
        </div>
        <div class="form-group">
            <label>Note</label>
            <textarea name="note" class="form-control" rows="5"><?php echo htmlspecialchars($note); ?></textarea>
        </div>

        <div class="form-group mt-4">
            <h4 class="border-bottom pb-2 mb-3">Invoice Items</h4>
            <table class="table table-bordered" id="invoice_items_table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Size <span class="text-danger">*</span></th>
                        <th style="width: 20%;">Product <span class="text-danger">*</span></th>
                        <th style="width: 10%;">Boxes <span class="text-danger">*</span></th>
                        <th style="width: 10%;">Rate/SQM <span class="text-danger">*</span></th>
                        <th style="width: 10%;">Comm. %</th>
                        <th style="width: 15%;">Qty (SQM)</th>
                        <th style="width: 10%;">Amount</th>
                        <th style="width: 5%;">Action</th>
                    </tr>
                </thead>
                <tbody id="invoice_items_table_body">
                    <?php foreach ($invoice_items_data as $idx => $item): // Loop through fetched/posted items ?>
                    <tr class="invoice-item-row">
                        <td>
                            <select name="item_size_id[]" class="form-control item-size" required>
                                <option value="">-- Select Size --</option>
                                <?php foreach($sizes_dropdown_data as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-sqm_per_box="<?php echo htmlspecialchars($s['sqm_per_box']); ?>" <?php if(isset($item['size_id']) && $item['size_id'] == $s['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($s['size_prefix'] . " [" . $s['size_text'] . "]"); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="item_product_id[]" class="form-control item-product" required>
                                <option value="">-- Select Product --</option>
                                <?php
                                // For existing items, we'd ideally populate this dynamically via JS on load,
                                // or pass a product list specific to the item's size_id from PHP.
                                // Simple pre-selection if product_id is known:
                                if(isset($item['product_id']) && !empty($item['product_id'])):
                                ?>
                                <option value="<?php echo htmlspecialchars($item['product_id']); ?>" selected>(Product ID: <?php echo htmlspecialchars($item['product_id']); ?> - JS will repopulate)</option>
                                <?php endif; ?>
                            </select>
                        </td>
                        <td><input type="number" name="item_boxes[]" class="form-control item-boxes" step="0.01" value="<?php echo htmlspecialchars($item['boxes'] ?? ''); ?>" required></td>
                        <td><input type="number" name="item_rate_per_sqm[]" class="form-control item-rate" step="0.01" value="<?php echo htmlspecialchars($item['rate_per_sqm'] ?? ''); ?>" required></td>
                        <td><input type="number" name="item_commission_percentage[]" class="form-control item-commission" step="0.01" value="<?php echo htmlspecialchars($item['commission_percentage'] ?? ''); ?>"></td>
                        <td><input type="text" class="form-control item-quantity-sqm-display" value="<?php echo htmlspecialchars($item['quantity_sqm'] ?? ''); ?>" readonly></td>
                        <td><input type="text" class="form-control item-amount-display" value="<?php echo htmlspecialchars($item['amount'] ?? ''); ?>" readonly></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove_invoice_item_row">Del</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-success mt-2" id="add_invoice_item_row">Add Item</button>
        </div>

        <table style="display:none;">
             <tr id="invoice_item_template_row" class="invoice-item-row">
                <td>
                    <select name="item_size_id[]" class="form-control item-size" disabled>
                        <option value="">-- Select Size --</option>
                        <?php foreach($sizes_dropdown_data as $size_item_tpl): ?>
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
                <td><input type="number" name="item_boxes[]" class="form-control item-boxes" step="0.01" placeholder="Boxes" disabled></td>
                <td><input type="number" name="item_rate_per_sqm[]" class="form-control item-rate" step="0.01" placeholder="Rate/SQM" disabled></td>
                <td><input type="number" name="item_commission_percentage[]" class="form-control item-commission" step="0.01" placeholder="Comm %" disabled></td>
                <td><input type="text" class="form-control item-quantity-sqm-display" readonly placeholder="SQM"></td>
                <td><input type="text" class="form-control item-amount-display" readonly placeholder="Amount"></td>
                <td><button type="button" class="btn btn-danger btn-sm remove_invoice_item_row">Del</button></td>
            </tr>
        </table>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Update Performa Invoice">
            <a href="performa_invoice_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsTableBody = document.getElementById('invoice_items_table_body');
    const addItemButton = document.getElementById('add_invoice_item_row');
    const templateRowHTML = document.getElementById('invoice_item_template_row').innerHTML;

    function addNewItemRow(prefillData = null) {
        const newRow = itemsTableBody.insertRow();
        newRow.className = 'invoice-item-row';
        newRow.innerHTML = templateRowHTML;

        const sizeSelect = newRow.querySelector('.item-size');
        const productSelect = newRow.querySelector('.item-product');
        const boxesInput = newRow.querySelector('.item-boxes');
        const rateInput = newRow.querySelector('.item-rate');
        const commissionInput = newRow.querySelector('.item-commission');

        sizeSelect.disabled = false;
        sizeSelect.required = true;

        productSelect.disabled = false;
        productSelect.required = true;

        boxesInput.disabled = false;
        boxesInput.required = true;

        rateInput.disabled = false;
        rateInput.required = true;

        if(commissionInput) commissionInput.disabled = false;

        attachEventListenersToRow(newRow);

        if (prefillData) { // Used for edit page initial load if we choose to prefill via JS
            newRow.querySelector('.item-size').value = prefillData.size_id || '';
            newRow.querySelector('.item-boxes').value = prefillData.boxes || '';
            newRow.querySelector('.item-rate').value = prefillData.rate_per_sqm || '';
            newRow.querySelector('.item-commission').value = prefillData.commission_percentage || '';

            if(prefillData.size_id) {
                 setTimeout(() => {
                    const currentSizeSelect = newRow.querySelector('.item-size'); // get it again from newRow
                    if (currentSizeSelect) {
                        currentSizeSelect.dispatchEvent(new Event('change', {'bubbles': true}));
                        if(prefillData.product_id) {
                            setTimeout(() => { // Delay to allow products to load
                                const currentProductSelect = newRow.querySelector('.item-product');
                                currentProductSelect.value = prefillData.product_id;
                                // Manually set rate again as product change might overwrite if not perfectly timed
                                newRow.querySelector('.item-rate').value = prefillData.rate_per_sqm || '';
                                calculateRowTotals(newRow); // Ensure calculation with correct rate
                            }, 800); // Increased delay
                        }
                    }
                }, 100);
            } else {
                 calculateRowTotals(newRow);
            }
        } else {
             calculateRowTotals(newRow);
        }
    }

    function attachEventListenersToRow(rowElement) {
        const sizeSelect = rowElement.querySelector('.item-size');
        const productSelect = rowElement.querySelector('.item-product');
        const boxesInput = rowElement.querySelector('.item-boxes');
        const rateInput = rowElement.querySelector('.item-rate');

        if (sizeSelect) {
            sizeSelect.addEventListener('change', function() {
                const selectedSizeId = this.value;
                const selectedSizeOption = this.options[this.selectedIndex];
                rowElement.dataset.sqm_per_box = selectedSizeOption.dataset.sqm_per_box || '';

                productSelect.innerHTML = '<option value="">-- Loading Products --</option>';
                rateInput.value = '';
                calculateRowTotals(rowElement);

                if (selectedSizeId) {
                    fetch(`ajax_get_products_by_size.php?size_id=${selectedSizeId}`)
                        .then(response => response.json())
                        .then(data => {
                            productSelect.innerHTML = '<option value="">-- Select Product --</option>';
                            if (data.success && data.products.length > 0) {
                                data.products.forEach(product => {
                                    const option = document.createElement('option');
                                    option.value = product.id;
                                    option.textContent = product.design_name;
                                    option.dataset.price_per_sqm = product.effective_price_per_sqm;
                                    productSelect.appendChild(option);
                                });
                            }
                        })
                        .catch(err => { console.error("AJAX error:", err); productSelect.innerHTML = '<option value="">-- Error --</option>'; });
                }
            });
        }

        if (productSelect) {
            productSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                rateInput.value = (selectedOption && selectedOption.dataset.price_per_sqm) ? parseFloat(selectedOption.dataset.price_per_sqm).toFixed(2) : '';
                calculateRowTotals(rowElement);
            });
        }

        if (boxesInput) boxesInput.addEventListener('input', () => calculateRowTotals(rowElement));
        if (rateInput) rateInput.addEventListener('input', () => calculateRowTotals(rowElement));

        const removeButton = rowElement.querySelector('.remove_invoice_item_row');
        if (removeButton) {
            removeButton.addEventListener('click', function() {
                rowElement.remove();
            });
        }
    }

    function calculateRowTotals(rowElement) {
        const sqmPerBoxVal = parseFloat(rowElement.dataset.sqm_per_box) || 0;
        const boxes = parseFloat(rowElement.querySelector('.item-boxes').value) || 0;
        const rate = parseFloat(rowElement.querySelector('.item-rate').value) || 0;
        const qtyDisplay = rowElement.querySelector('.item-quantity-sqm-display');
        const amountDisplay = rowElement.querySelector('.item-amount-display');

        if (sqmPerBoxVal > 0 && boxes > 0) {
            const quantitySqm = boxes * sqmPerBoxVal;
            qtyDisplay.value = quantitySqm.toFixed(4);
            if (rate > 0) {
                amountDisplay.value = (quantitySqm * rate).toFixed(2);
            } else {
                amountDisplay.value = '0.00';
            }
        } else {
            qtyDisplay.value = '0.0000';
            amountDisplay.value = '0.00';
        }
    }

    if (addItemButton) {
        addItemButton.addEventListener('click', () => addNewItemRow(null));
    }

    document.querySelectorAll('#invoice_items_table_body tr.invoice-item-row').forEach(row => {
        attachEventListenersToRow(row);
        const sizeSelect = row.querySelector('.item-size');
        if (sizeSelect && sizeSelect.value) {
            const selectedSizeOption = sizeSelect.options[sizeSelect.selectedIndex];
            if(selectedSizeOption && selectedSizeOption.dataset.sqm_per_box){
                 row.dataset.sqm_per_box = selectedSizeOption.dataset.sqm_per_box;
            }

            const currentProductId = row.querySelector('.item-product option[selected]')?.value;
            const currentRate = row.querySelector('.item-rate').value; // This is the saved item rate
            const productSelect = row.querySelector('.item-product');

            // Fetch products for the current size to populate dropdown correctly
            fetch(`ajax_get_products_by_size.php?size_id=${sizeSelect.value}`)
                .then(response => response.json())
                .then(data => {
                    productSelect.innerHTML = '<option value="">-- Select Product --</option>';
                    let productStillExists = false;
                    if (data.success && data.products.length > 0) {
                        data.products.forEach(product => {
                            const option = document.createElement('option');
                            option.value = product.id;
                            option.textContent = product.design_name;
                            option.dataset.price_per_sqm = product.effective_price_per_sqm;
                            if (product.id == currentProductId) {
                                option.selected = true;
                                productStillExists = true;
                            }
                            productSelect.appendChild(option);
                        });
                    }
                    // If the originally selected product is no longer in the list (e.g. size changed or product deleted)
                    // we keep the saved rate. If a new product is chosen, rate will update.
                    if (productStillExists) {
                         row.querySelector('.item-rate').value = currentRate; // Restore original item rate
                    } else if (currentProductId && !productSelect.querySelector(`option[value='${currentProductId}']`)) {
                        // If original product ID is not in the new list, add a placeholder option for it
                        // This indicates the data is for a product not currently available for this size
                        const oldProductOption = document.createElement('option');
                        oldProductOption.value = currentProductId;
                        oldProductOption.textContent = `(Saved Product ID: ${currentProductId})`;
                        oldProductOption.selected = true;
                        productSelect.appendChild(oldProductOption);
                        row.querySelector('.item-rate').value = currentRate; // Keep saved rate
                    }
                    calculateRowTotals(row);
                })
                .catch(err => {
                    console.error("AJAX error on initial row product load:", err);
                    productSelect.innerHTML = '<option value="">-- Error Loading Products --</option>';
                    calculateRowTotals(row); // Calculate with potentially no rate
                });
        } else {
             calculateRowTotals(row);
        }
    });

    if (document.querySelectorAll('#invoice_items_table_body tr.invoice-item-row').length === 0) {
        if(typeof addNewItemRow === "function") addNewItemRow(null);
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
