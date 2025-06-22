<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Fetch all sizes for the dropdown - This needs to be available for both GET (form display) and POST (validation if needed)
$sizes_dropdown_data = []; // Renamed to avoid conflict if $sizes is used elsewhere
$sql_sizes_all = "SELECT id, size_text, size_prefix FROM sizes ORDER BY size_prefix, size_text ASC";
$result_sizes_all = $conn->query($sql_sizes_all);
if ($result_sizes_all && $result_sizes_all->num_rows > 0) {
    while ($row_s = $result_sizes_all->fetch_assoc()) {
        $sizes_dropdown_data[] = $row_s;
    }
}

// Initialize variables for form fields and errors
$size_id_selected = $product_type = $design_names_input = "";
$box_weight_form = $purchase_price_form = $price_per_sqm_form = "";
$errors = [];

// --- POST request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Assign POST values for processing and sticky form
    $size_id_selected = trim($_POST["size_id"]);
    $product_type = trim($_POST["product_type"]);
    $design_names_input = trim($_POST["design_names"]);
    $box_weight_form = trim($_POST["box_weight"]);
    $purchase_price_form = trim($_POST["purchase_price"]);
    $price_per_sqm_form = trim($_POST["price_per_sqm"]);

    // --- Validations ---
    if (empty($size_id_selected)) {
        $errors["size_id"] = "Please select a size.";
    }
    if (empty($design_names_input)) {
        $errors["design_names"] = "Please enter at least one design name.";
    }
    if (!empty($box_weight_form) && !is_numeric($box_weight_form)) {
        $errors["box_weight"] = "Box Weight must be a number if entered.";
    }
    if (!empty($purchase_price_form) && !is_numeric($purchase_price_form)) {
        $errors["purchase_price"] = "Purchase Price must be a number if entered.";
    }
    if (!empty($price_per_sqm_form) && !is_numeric($price_per_sqm_form)) {
        $errors["price_per_sqm"] = "Price Per SQM must be a number if entered.";
    }
    // --- End Validations ---

    if (empty($errors)) {
        $design_names_array = array_map('trim', explode(',', $design_names_input));
        $design_names_array = array_filter($design_names_array);

        if (empty($design_names_array)) {
            $errors["design_names"] = "Please enter valid, comma-separated design names.";
        } else {
            $sql_insert_product = "INSERT INTO products (size_id, design_name, product_type,
                                        box_weight_override, purchase_price_override, price_per_sqm_override,
                                        product_code, description)
                                   VALUES (?, ?, ?, ?, ?, ?, NULL, NULL)";

            $stmt_insert_product = $conn->prepare($sql_insert_product);

            if ($stmt_insert_product) {
                $success_count = 0;
                $error_count = 0;
                $insert_errors_details = [];

                foreach ($design_names_array as $design_name) {
                    $box_weight_override_val = ($box_weight_form === '') ? null : (float)$box_weight_form;
                    $purchase_price_override_val = ($purchase_price_form === '') ? null : (float)$purchase_price_form;
                    $price_per_sqm_override_val = ($price_per_sqm_form === '') ? null : (float)$price_per_sqm_form;

                    $stmt_insert_product->bind_param("issddd",
                        $size_id_selected, $design_name, $product_type,
                        $box_weight_override_val, $purchase_price_override_val, $price_per_sqm_override_val
                    );

                    if ($stmt_insert_product->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $insert_errors_details[] = "Error for '{$design_name}': " . $stmt_insert_product->error;
                    }
                }
                $stmt_insert_product->close();

                if ($error_count == 0 && $success_count > 0) {
                    header("location: product_list.php?status=success_add&count=" . $success_count);
                    exit();
                } elseif ($success_count > 0 && $error_count > 0) {
                     $errors['db_insert_error'] = "Some products created ({$success_count}), but errors occurred for others ({$error_count}):<br>" . implode("<br>", $insert_errors_details);
                } elseif ($error_count > 0) {
                     $errors['db_insert_error'] = "Could not create products. Errors:<br>" . implode("<br>", $insert_errors_details);
                } elseif ($error_count == 0 && $success_count == 0 && !empty($design_names_array)) {
                     $errors['db_insert_error'] = "No products were created. Please check input.";
                }
            } else {
                $errors['db_error'] = "Error preparing product insert statement: " . $conn->error;
            }
        }
    }
}
// --- End of POST request Processing ---

// Include header AFTER POST processing
require_once '../../includes/header.php';
?>

<div class="container">
    <h2>Add New Product(s)</h2>
    <p>Select a size, enter details, and provide comma-separated design names to create multiple product entries.</p>

    <?php if (!empty($errors['db_error']) || !empty($errors['db_insert_error'])): ?>
        <div class="alert alert-danger">
            <?php echo $errors['db_error'] ?? ''; // Using ?? to avoid undefined index notice if only one error type is set ?>
            <?php echo $errors['db_insert_error'] ?? ''; ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="addProductForm">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="size_id">Size <span class="text-danger">*</span></label>
                    <select name="size_id" id="size_id" class="form-control <?php echo isset($errors['size_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">-- Select a Size --</option>
                        <?php foreach ($sizes_dropdown_data as $size_item): // Changed variable name to avoid conflict ?>
                            <?php
                                $full_size_desc = htmlspecialchars($size_item['size_prefix'] . " [" . $size_item['size_text'] . "]");
                                $selected_attr = ($size_id_selected == $size_item['id']) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $size_item['id']; ?>" <?php echo $selected_attr; ?>>
                                <?php echo $full_size_desc; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"><?php echo $errors['size_id'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="product_type">Product Type</label>
                    <input type="text" name="product_type" id="product_type" class="form-control" value="<?php echo htmlspecialchars($product_type); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="box_weight">Box Weight (KG)</label>
                    <input type="number" step="0.01" name="box_weight" id="box_weight" class="form-control <?php echo isset($errors['box_weight']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($box_weight_form); ?>">
                    <small class="form-text text-muted">Pre-filled from size, editable.</small>
                    <span class="invalid-feedback"><?php echo $errors['box_weight'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="purchase_price">Purchase Price (per box/unit)</label>
                    <input type="number" step="0.01" name="purchase_price" id="purchase_price" class="form-control <?php echo isset($errors['purchase_price']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($purchase_price_form); ?>">
                    <small class="form-text text-muted">Pre-filled from size, editable.</small>
                    <span class="invalid-feedback"><?php echo $errors['purchase_price'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="price_per_sqm">Price Per SQM (Selling)</label>
                    <input type="number" step="0.01" name="price_per_sqm" id="price_per_sqm" class="form-control <?php echo isset($errors['price_per_sqm']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($price_per_sqm_form); ?>">
                    <small class="form-text text-muted">Pre-filled from size, editable.</small>
                    <span class="invalid-feedback"><?php echo $errors['price_per_sqm'] ?? '';?></span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="design_names">Design Name(s) <span class="text-danger">*</span></label>
            <textarea name="design_names" id="design_names" class="form-control <?php echo isset($errors['design_names']) ? 'is-invalid' : ''; ?>" rows="3" required placeholder="Enter multiple design names separated by commas, e.g., Design A, Super White, Onyx Black"><?php echo htmlspecialchars($design_names_input); ?></textarea>
            <span class="invalid-feedback"><?php echo $errors['design_names'] ?? '';?></span>
            <small class="form-text text-muted">Each name will create a separate product entry.</small>
        </div>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Add Products">
            <a href="product_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sizeDropdown = document.getElementById('size_id');
    const boxWeightInput = document.getElementById('box_weight');
    const purchasePriceInput = document.getElementById('purchase_price');
    const pricePerSqmInput = document.getElementById('price_per_sqm');

    if (sizeDropdown) {
        sizeDropdown.addEventListener('change', function() {
            const selectedSizeId = this.value;

            if (!selectedSizeId) {
                boxWeightInput.value = '';
                purchasePriceInput.value = '';
                pricePerSqmInput.value = '';
                return;
            }

            fetch(`ajax_get_size_details.php?size_id=${selectedSizeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const details = data.data;
                        boxWeightInput.value = details.box_weight || '';
                        purchasePriceInput.value = details.purchase_price || '';
                        pricePerSqmInput.value = details.price_per_sqm || '';
                    } else {
                        console.error('Error fetching size details:', data.error);
                        boxWeightInput.value = '';
                        purchasePriceInput.value = '';
                        pricePerSqmInput.value = '';
                    }
                })
                .catch(error => {
                    console.error('AJAX request failed:', error);
                    boxWeightInput.value = '';
                    purchasePriceInput.value = '';
                    pricePerSqmInput.value = '';
                });
        });

        if (sizeDropdown.value) {
            sizeDropdown.dispatchEvent(new Event('change'));
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
