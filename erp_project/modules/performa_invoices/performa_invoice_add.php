<?php
ini_set('display_errors', 1); // Keep for debugging
ini_set('display_startup_errors', 1); // Keep for debugging
error_reporting(E_ALL); // Keep for debugging

require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

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


// --- Initialize variables for form fields and errors ---
$exporter_id = $invoice_number = $invoice_date = $consignee_id = $final_destination = "";
$total_container = $container_size = $currency_type = $total_gross_weight_kg = "";
$bank_id = $freight_amount = $discount_amount = "";
$notify_party_line1 = $notify_party_line2 = $terms_delivery_payment = $note = "";
$errors = [];
$item_errors = []; // For item specific errors

// Default values
$invoice_number_default = generate_invoice_number($conn);
$invoice_date_default = get_current_date_for_input();
$container_size_default = '20';
$currency_type_default = 'USD';
$terms_default = "30 % advance and 70% against BL ( against scan copy of BL)";
$note_default = "TRANSSHIPMENT ALLOWED. : PARTIAL SHIPMENT ALLOWED\nSHIPMENT : AS EARLY AS POSSIBLE\nQUANTITY AND VALUE +/-10% ALLOWED. NOT ACCEPTED ANY REFUND OR EXCHANGE\nANY TRANSACTION CHARGES WILL BE PAIDED BY CONSIGNEE.";

// --- POST request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction(); // START TRANSACTION

    // Assign POST values for header
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

    // --- Header Validations ---
    if (empty($exporter_id)) $errors['exporter_id'] = "Exporter is required.";
    if (empty($invoice_number)) $errors['invoice_number'] = "Invoice number is required.";
    else {
        $sql_check_inv = "SELECT id FROM performa_invoices WHERE invoice_number = ? LIMIT 1";
        if($stmt_check_inv = $conn->prepare($sql_check_inv)){
            $stmt_check_inv->bind_param("s", $invoice_number);
            $stmt_check_inv->execute();
            $stmt_check_inv->store_result();
            if($stmt_check_inv->num_rows > 0) $errors['invoice_number'] = "This invoice number already exists.";
            $stmt_check_inv->close();
        }
    }
    if (empty($invoice_date)) $errors['invoice_date'] = "Invoice date is required.";
    if (empty($consignee_id)) $errors['consignee_id'] = "Consignee is required.";
    // ... (other header validations)

    if (empty($errors)) {
        $sql_header_insert = "INSERT INTO performa_invoices (exporter_id, invoice_number, invoice_date, consignee_id, final_destination,
                                            total_container, container_size, currency_type, total_gross_weight_kg,
                                            bank_id, freight_amount, discount_amount, notify_party_line1,
                                            notify_party_line2, terms_delivery_payment, note)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt_header = $conn->prepare($sql_header_insert)) {
            $stmt_header->bind_param("ississssdi ddssss",
                $exporter_id, $invoice_number, $invoice_date, $consignee_id, $final_destination,
                $total_container, $container_size, $currency_type, $param_total_gross_weight_kg,
                $param_bank_id, $param_freight_amount, $param_discount_amount, $notify_party_line1,
                $notify_party_line2, $terms_delivery_payment, $note
            );

            $param_total_gross_weight_kg = !empty($total_gross_weight_kg) ? (float)$total_gross_weight_kg : null;
            $param_bank_id = !empty($bank_id) ? (int)$bank_id : null;
            $param_freight_amount = !empty($freight_amount) ? (float)$freight_amount : null;
            $param_discount_amount = !empty($discount_amount) ? (float)$discount_amount : null;

            if ($stmt_header->execute()) {
                $new_pi_id = $stmt_header->insert_id;
                $stmt_header->close();

                // --- Process Invoice Items ---
                $item_size_ids = $_POST['item_size_id'] ?? [];
                $item_product_ids = $_POST['item_product_id'] ?? [];
                $item_boxes_arr = $_POST['item_boxes'] ?? [];
                $item_rates_arr = $_POST['item_rate_per_sqm'] ?? [];
                $item_commissions_arr = $_POST['item_commission_percentage'] ?? [];

                if (!empty($item_size_ids) && count($item_size_ids) > 0) { // Check if there are any items
                    $sql_item_insert = "INSERT INTO performa_invoice_items
                                        (performa_invoice_id, size_id, product_id, boxes, rate_per_sqm, commission_percentage, quantity_sqm, amount)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_item = $conn->prepare($sql_item_insert);

                    if ($stmt_item) {
                        for ($i = 0; $i < count($item_size_ids); $i++) {
                            if (empty($item_size_ids[$i]) || empty($item_product_ids[$i]) || !isset($item_boxes_arr[$i]) || $item_boxes_arr[$i] === '' || !isset($item_rates_arr[$i]) || $item_rates_arr[$i] === '') {
                                $item_errors[] = "Error in item #".($i+1).": Size, Product, Boxes, and Rate are required.";
                                continue; // Skip this item, or rollback and show error
                            }

                            $item_size_id = (int)$item_size_ids[$i];
                            $item_product_id = (int)$item_product_ids[$i];
                            $item_boxes = (float)$item_boxes_arr[$i];
                            $item_rate = (float)$item_rates_arr[$i];
                            $item_commission = !empty($item_commissions_arr[$i]) ? (float)$item_commissions_arr[$i] : null;

                            $sqm_per_box_item = 0;
                            $sql_get_sqm = "SELECT sqm_per_box FROM sizes WHERE id = ? LIMIT 1";
                            if($stmt_get_sqm = $conn->prepare($sql_get_sqm)){
                                $stmt_get_sqm->bind_param("i", $item_size_id);
                                $stmt_get_sqm->execute();
                                $res_sqm = $stmt_get_sqm->get_result();
                                if ($res_sqm->num_rows > 0) {
                                    $sqm_per_box_item = (float)$res_sqm->fetch_assoc()['sqm_per_box'];
                                }
                                $stmt_get_sqm->close();
                            }

                            if ($sqm_per_box_item <= 0) {
                                $item_errors[] = "Error in item #".($i+1).": Invalid SQM per Box for selected size.";
                                continue;
                            }

                            $item_quantity_sqm = $item_boxes * $sqm_per_box_item;
                            $item_amount = $item_quantity_sqm * $item_rate;

                            $stmt_item->bind_param("iiiddddd",
                                $new_pi_id, $item_size_id, $item_product_id,
                                $item_boxes, $item_rate, $item_commission,
                                $item_quantity_sqm, $item_amount
                            );

                            if (!$stmt_item->execute()) {
                                $item_errors[] = "Error saving item #".($i+1).": " . $stmt_item->error;
                            }
                        }
                        $stmt_item->close();
                    } else { // $stmt_item prepare failed
                         $errors['db_error'] = "Error preparing item insert statement: " . $conn->error;
                    }
                } // else: no items submitted, proceed with header only if allowed

                if (empty($item_errors) && empty($errors['db_error'])) {
                    $conn->commit();
                    header("location: performa_invoice_list.php?status=success_add&id=" . $new_pi_id);
                    exit();
                } else {
                    $conn->rollback();
                    // Consolidate item errors into db_error for display
                    if(!empty($item_errors)) $errors['db_error'] = ($errors['db_error'] ?? "") . "<br>Item Errors:<br>" . implode("<br>", $item_errors);
                }

            } else { // Header insert failed
                $conn->rollback();
                if ($conn->errno == 1062 && strpos($stmt_header->error, 'unique_invoice_number') !== false) {
                     $errors['db_error'] = "Database Error: This Invoice Number already exists.";
                } else {
                    $errors['db_error'] = "Database error creating performa invoice header: " . $stmt_header->error;
                }
            }
        } else { // Header prepare failed
            $errors['db_error'] = "Error preparing PI header statement: " . $conn->error;
            // No transaction started if prepare failed, so no rollback needed here.
        }
    } // End if empty($errors) for header
    else { // Header validation errors
        // No transaction started, so no rollback needed.
    }
} else {
    $invoice_number = $invoice_number_default;
    $invoice_date = $invoice_date_default;
    $container_size = $container_size_default;
    $currency_type = $currency_type_default;
    $terms_delivery_payment = $terms_default;
    $note = $note_default;
}
// --- End of POST request Processing ---

require_once '../../includes/header.php';
?>

<div class="container">
    <h2>Add New Performa Invoice</h2>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo $errors['db_error']; /* Allow HTML for item errors */ ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="performaInvoiceForm">
        <!-- Header Fields Section -->
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

        <!-- Invoice Items Section -->
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
                    <!-- Item rows will be appended here by JavaScript -->
                     <?php
                    // For sticky form: if there was a POST and errors, repopulate items
                    $posted_item_size_ids = $_POST['item_size_id'] ?? [];
                    if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($posted_item_size_ids)) {
                        for ($i=0; $i < count($posted_item_size_ids); $i++) {
                            // This is a simplified repopulation. Does not re-fetch product options.
                            // A full solution would involve more complex JS state or server-side rendering of rows.
                            echo '<tr class="invoice-item-row">'; // Class for easier selection if needed
                            echo '<td><select name="item_size_id[]" class="form-control item-size" required><option value="">-- Select Size --</option>';
                            foreach($sizes_dropdown_data as $s) {
                                $sel = ($s['id'] == ($_POST['item_size_id'][$i] ?? '')) ? 'selected' : '';
                                echo "<option value='{$s['id']}' data-sqm_per_box='{$s['sqm_per_box']}' {$sel}>".htmlspecialchars($s['size_prefix'] . " [" . $s['size_text'] . "]")."</option>";
                            }
                            echo '</select></td>';
                            echo '<td><select name="item_product_id[]" class="form-control item-product" required><option value="">-- Select Product --</option>';
                            // Products would need to be re-fetched based on selected size if we want to make this sticky properly.
                            // For now, just showing selected ID if available.
                            if(isset($_POST['item_product_id'][$i]) && !empty($_POST['item_product_id'][$i])) {
                                echo "<option value='".htmlspecialchars($_POST['item_product_id'][$i])."' selected>Product ID ".htmlspecialchars($_POST['item_product_id'][$i])." (Repopulate manually)</option>";
                            }
                            echo '</select></td>';
                            echo '<td><input type="number" name="item_boxes[]" class="form-control item-boxes" step="0.01" value="'.htmlspecialchars($_POST['item_boxes'][$i] ?? '').'" required></td>';
                            echo '<td><input type="number" name="item_rate_per_sqm[]" class="form-control item-rate" step="0.01" value="'.htmlspecialchars($_POST['item_rate_per_sqm'][$i] ?? '').'" required></td>';
                            echo '<td><input type="number" name="item_commission_percentage[]" class="form-control item-commission" step="0.01" value="'.htmlspecialchars($_POST['item_commission_percentage'][$i] ?? '').'"></td>';
                            echo '<td><input type="text" name="item_quantity_sqm_display[]" class="form-control item-quantity-sqm-display" readonly></td>';
                            echo '<td><input type="text" name="item_amount_display[]" class="form-control item-amount-display" readonly></td>';
                            echo '<td><button type="button" class="btn btn-danger btn-sm remove_invoice_item_row">Del</button></td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-success mt-2" id="add_invoice_item_row">Add Item</button>
        </div>

        <table style="display:none;"> <!-- Hidden template for invoice item rows -->
            <tr id="invoice_item_template_row" class="invoice-item-row">
                <td>
                    <select name="item_size_id[]" class="form-control item-size" required>
                        <option value="">-- Select Size --</option>
                        <?php foreach($sizes_dropdown_data as $size_item_tpl): ?>
                        <option value="<?php echo $size_item_tpl['id']; ?>" data-sqm_per_box="<?php echo htmlspecialchars($size_item_tpl['sqm_per_box']); ?>">
                            <?php echo htmlspecialchars($size_item_tpl['size_prefix'] . " [" . $size_item_tpl['size_text'] . "]"); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="item_product_id[]" class="form-control item-product" required>
                        <option value="">-- Select Product --</option>
                    </select>
                </td>
                <td><input type="number" name="item_boxes[]" class="form-control item-boxes" step="0.01" placeholder="Boxes" required></td>
                <td><input type="number" name="item_rate_per_sqm[]" class="form-control item-rate" step="0.01" placeholder="Rate/SQM" required></td>
                <td><input type="number" name="item_commission_percentage[]" class="form-control item-commission" step="0.01" placeholder="Comm %"></td>
                <td><input type="text" class="form-control item-quantity-sqm-display" readonly placeholder="SQM"></td> <!-- Not submitted -->
                <td><input type="text" class="form-control item-amount-display" readonly placeholder="Amount"></td> <!-- Not submitted -->
                <td><button type="button" class="btn btn-danger btn-sm remove_invoice_item_row">Del</button></td>
            </tr>
        </table>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Save Performa Invoice">
            <a href="performa_invoice_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsTableBody = document.getElementById('invoice_items_table_body');
    const addItemButton = document.getElementById('add_invoice_item_row');
    const templateRowHTML = document.getElementById('invoice_item_template_row').innerHTML; // Get innerHTML of the template row

    function addNewItemRow() {
        const newRow = itemsTableBody.insertRow();
        newRow.className = 'invoice-item-row'; // Add class for styling or easier selection
        newRow.innerHTML = templateRowHTML;
        attachEventListenersToRow(newRow);
        if(itemsTableBody.querySelectorAll('tr').length === 1 && newRow.querySelector('.remove_invoice_item_row')) { // First row
             // newRow.querySelector('.remove_invoice_item_row').disabled = true; // Disable delete for first row
        }
    }

    function attachEventListenersToRow(rowElement) {
        const sizeSelect = rowElement.querySelector('.item-size');
        const productSelect = rowElement.querySelector('.item-product');
        const boxesInput = rowElement.querySelector('.item-boxes');
        const rateInput = rowElement.querySelector('.item-rate');
        // Commission input could also trigger recalculation if it affects amount, but not for now.

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
                            } else if (data.products.length === 0) {
                                // productSelect.innerHTML = '<option value="">-- No products --</option>';
                            } else {
                                console.error("Error/No products:", data.error);
                            }
                        })
                        .catch(err => {
                            console.error("AJAX error:", err);
                            productSelect.innerHTML = '<option value="">-- Error --</option>';
                        });
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
                // if(itemsTableBody.querySelectorAll('tr').length === 1 && itemsTableBody.querySelector('.remove_invoice_item_row')) {
                //      itemsTableBody.querySelector('.remove_invoice_item_row').disabled = true;
                // }
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
        addItemButton.addEventListener('click', addNewItemRow);
    }

    // Attach listeners to any rows that might be server-rendered (e.g., on form error POST back)
    document.querySelectorAll('#invoice_items_table_body tr.invoice-item-row').forEach(row => {
        attachEventListenersToRow(row);
        // Trigger calculation for existing rows if values are present
        const sizeSelect = row.querySelector('.item-size');
        if(sizeSelect && sizeSelect.value) { // if a size is selected
            const selectedSizeOption = sizeSelect.options[sizeSelect.selectedIndex];
            row.dataset.sqm_per_box = selectedSizeOption.dataset.sqm_per_box || '';
             // If product is also selected, trigger its change to prefill rate
            const productSelect = row.querySelector('.item-product');
            if(productSelect && productSelect.value){
                // This is tricky because product options are not repopulated by PHP here.
                // For now, just calculate based on existing rate.
            }
        }
        calculateRowTotals(row);
    });

    // Add one row by default if no items are present from POST back
    if (document.querySelectorAll('#invoice_items_table_body tr.invoice-item-row').length === 0) {
        addNewItemRow();
    }

});
</script>

<?php require_once '../../includes/footer.php'; ?>
