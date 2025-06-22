<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Fetch all sizes for the dropdown - needs to be available early
$sizes_dropdown_data = [];
$sql_sizes_all = "SELECT id, size_text, size_prefix FROM sizes ORDER BY size_prefix, size_text ASC";
$result_sizes_all = $conn->query($sql_sizes_all);
if ($result_sizes_all && $result_sizes_all->num_rows > 0) {
    while ($row_s = $result_sizes_all->fetch_assoc()) {
        $sizes_dropdown_data[] = $row_s;
    }
}

// Initialize variables
$product_id = null;
$size_id_selected = $product_type = $design_name = "";
$box_weight_form = $purchase_price_form = $price_per_sqm_form = "";
$product_code_form = $description_form = "";
$original_size_box_weight = $original_size_purchase_price = $original_size_price_per_sqm = ""; // For display
$errors = [];

// --- POST Request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["id"]) && !empty(trim($_POST["id"]))) {
    $product_id = trim($_POST["id"]);
    // Assign POST values for processing and sticky form
    $size_id_selected = trim($_POST["size_id"]);
    $product_type = trim($_POST["product_type"]);
    $design_name = trim($_POST["design_name"]);
    $product_code_form = trim($_POST["product_code"]);
    $description_form = trim($_POST["description"]);
    $box_weight_form = trim($_POST["box_weight"]);
    $purchase_price_form = trim($_POST["purchase_price"]);
    $price_per_sqm_form = trim($_POST["price_per_sqm"]);

    // --- Validations ---
    if (empty($size_id_selected)) $errors["size_id"] = "Please select a size.";
    if (empty($design_name)) $errors["design_name"] = "Design name cannot be empty.";
    // Add other necessary validations...
    // --- End Validations ---

    if (empty($errors)) {
        $new_size_details = null;
        if (!empty($size_id_selected)) {
            $sql_new_size = "SELECT box_weight, purchase_price, price_per_sqm FROM sizes WHERE id = ?";
            if($stmt_new_size = $conn->prepare($sql_new_size)){
                $stmt_new_size->bind_param("i", $size_id_selected);
                if($stmt_new_size->execute()){
                    $res_new_size = $stmt_new_size->get_result();
                    if($res_new_size->num_rows == 1){
                        $new_size_details = $res_new_size->fetch_assoc();
                    }
                }
                $stmt_new_size->close();
            }
        }

        $box_weight_override_val = null;
        if ($new_size_details && $box_weight_form !== '' && (float)$box_weight_form != (float)$new_size_details['box_weight']) {
            $box_weight_override_val = (float)$box_weight_form;
        } elseif ($box_weight_form === '' && $new_size_details && $new_size_details['box_weight'] !== null) {
            // If user cleared field, and size default is not null, then it's an override to be null (or keep product override null)
             $box_weight_override_val = null;
        } elseif ($box_weight_form !== '') { // If no new_size_details (error) or field has value not matching (or matching but we save it anyway if not empty)
            $box_weight_override_val = (float)$box_weight_form;
        }


        $purchase_price_override_val = null;
        if ($new_size_details && $purchase_price_form !== '' && (float)$purchase_price_form != (float)$new_size_details['purchase_price']) {
            $purchase_price_override_val = (float)$purchase_price_form;
        } elseif ($purchase_price_form === '' && $new_size_details && $new_size_details['purchase_price'] !== null) {
            $purchase_price_override_val = null;
        } elseif ($purchase_price_form !== '') {
            $purchase_price_override_val = (float)$purchase_price_form;
        }


        $price_per_sqm_override_val = null;
        if ($new_size_details && $price_per_sqm_form !== '' && (float)$price_per_sqm_form != (float)$new_size_details['price_per_sqm']) {
            $price_per_sqm_override_val = (float)$price_per_sqm_form;
        } elseif ($price_per_sqm_form === '' && $new_size_details && $new_size_details['price_per_sqm'] !== null) {
            $price_per_sqm_override_val = null;
        } elseif ($price_per_sqm_form !== '') {
             $price_per_sqm_override_val = (float)$price_per_sqm_form;
        }

        // Ensure product_code and description are null if empty strings
        $product_code_val = !empty($product_code_form) ? $product_code_form : null;
        $description_val = !empty($description_form) ? $description_form : null;


        $sql_update = "UPDATE products SET size_id=?, design_name=?, product_type=?,
                                       box_weight_override=?, purchase_price_override=?, price_per_sqm_override=?,
                                       product_code=?, description=?
                       WHERE id=?";

        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("isssddssi",
                $size_id_selected, $design_name, $product_type,
                $box_weight_override_val, $purchase_price_override_val, $price_per_sqm_override_val,
                $product_code_val, $description_val,
                $product_id
            );

            if ($stmt_update->execute()) {
                header("location: product_list.php?status=success_edit");
                exit();
            } else {
                $errors['db_error'] = "Error updating product: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors['db_error'] = "Error preparing update statement: " . $conn->error;
        }
    }
}
// --- End of POST Request Processing ---
// --- GET Request Processing (for initial form load) ---
elseif (isset($_GET["id"]) && !empty(trim($_GET["id"])) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $product_id = trim($_GET["id"]);
    $sql_fetch = "SELECT p.*, s.box_weight AS size_box_weight, s.purchase_price AS size_purchase_price, s.price_per_sqm AS size_price_per_sqm
                  FROM products p JOIN sizes s ON p.size_id = s.id WHERE p.id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $product_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $product = $result->fetch_assoc();
                $size_id_selected = $product['size_id'];
                $product_type = $product['product_type'];
                $design_name = $product['design_name'];
                $product_code_form = $product['product_code'];
                $description_form = $product['description'];
                $box_weight_form = $product['box_weight_override'] ?? $product['size_box_weight'];
                $purchase_price_form = $product['purchase_price_override'] ?? $product['size_purchase_price'];
                $price_per_sqm_form = $product['price_per_sqm_override'] ?? $product['size_price_per_sqm'];
                $original_size_box_weight = $product['size_box_weight'];
                $original_size_purchase_price = $product['size_purchase_price'];
                $original_size_price_per_sqm = $product['size_price_per_sqm'];
            } else {
                $errors['load_error'] = "Error: Product not found for ID " . htmlspecialchars($product_id) . ".";
            }
        } else {
            $errors['load_error'] = "Error fetching product data: " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $errors['load_error'] = "Error preparing fetch statement: " . $conn->error;
    }
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
     $errors['load_error'] = "No Product ID specified for editing.";
}
// --- End of GET Request Processing ---

require_once '../../includes/header.php'; // Include header AFTER processing

if ($_SERVER["REQUEST_METHOD"] != "POST" && !empty($errors['load_error'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>" . htmlspecialchars($errors['load_error']) . " <a href='product_list.php' class='alert-link'>Back to List</a>.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <h2>Edit Product</h2>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>
    <?php if (($_SERVER["REQUEST_METHOD"] == "POST") && !empty($errors['load_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="editProductForm">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($product_id); ?>">

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="size_id">Size <span class="text-danger">*</span></label>
                    <select name="size_id" id="size_id" class="form-control <?php echo isset($errors['size_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">-- Select a Size --</option>
                        <?php foreach ($sizes_dropdown_data as $size_item): ?>
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
                    <label for="design_name">Design Name <span class="text-danger">*</span></label>
                    <input type="text" name="design_name" id="design_name" class="form-control <?php echo isset($errors['design_name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($design_name); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['design_name'] ?? '';?></span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="product_type">Product Type</label>
                    <input type="text" name="product_type" id="product_type" class="form-control" value="<?php echo htmlspecialchars($product_type); ?>">
                </div>
            </div>
             <div class="col-md-6">
                <div class="form-group">
                    <label for="product_code">Product Code</label>
                    <input type="text" name="product_code" id="product_code" class="form-control" value="<?php echo htmlspecialchars($product_code_form); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="box_weight">Box Weight (KG)</label>
                    <input type="number" step="0.01" name="box_weight" id="box_weight" class="form-control" value="<?php echo htmlspecialchars($box_weight_form); ?>">
                    <small class="form-text text-muted">Size Default: <span id="original_box_weight"><?php echo htmlspecialchars($original_size_box_weight ?? ''); ?></span>. Editable.</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="purchase_price">Purchase Price</label>
                    <input type="number" step="0.01" name="purchase_price" id="purchase_price" class="form-control" value="<?php echo htmlspecialchars($purchase_price_form); ?>">
                     <small class="form-text text-muted">Size Default: <span id="original_purchase_price"><?php echo htmlspecialchars($original_size_purchase_price ?? ''); ?></span>. Editable.</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="price_per_sqm">Price Per SQM</label>
                    <input type="number" step="0.01" name="price_per_sqm" id="price_per_sqm" class="form-control" value="<?php echo htmlspecialchars($price_per_sqm_form); ?>">
                    <small class="form-text text-muted">Size Default: <span id="original_price_per_sqm"><?php echo htmlspecialchars($original_size_price_per_sqm ?? ''); ?></span>. Editable.</small>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-control" rows="3"><?php echo htmlspecialchars($description_form); ?></textarea>
        </div>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Update Product">
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

    const originalBoxWeightSpan = document.getElementById('original_box_weight');
    const originalPurchasePriceSpan = document.getElementById('original_purchase_price');
    const originalPricePerSqmSpan = document.getElementById('original_price_per_sqm');

    function updateOriginalValueDisplay(details) {
        if(originalBoxWeightSpan) originalBoxWeightSpan.textContent = details.box_weight !== null ? parseFloat(details.box_weight).toFixed(2) : 'N/A';
        if(originalPurchasePriceSpan) originalPurchasePriceSpan.textContent = details.purchase_price !== null ? parseFloat(details.purchase_price).toFixed(2) : 'N/A';
        if(originalPricePerSqmSpan) originalPricePerSqmSpan.textContent = details.price_per_sqm !== null ? parseFloat(details.price_per_sqm).toFixed(2) : 'N/A';
    }

    // Function to pre-fill inputs based on fetched size details
    function prefillInputsFromSize(details) {
        boxWeightInput.value = details.box_weight !== null ? parseFloat(details.box_weight).toFixed(2) : '';
        purchasePriceInput.value = details.purchase_price !== null ? parseFloat(details.purchase_price).toFixed(2) : '';
        pricePerSqmInput.value = details.price_per_sqm !== null ? parseFloat(details.price_per_sqm).toFixed(2) : '';
    }

    if (sizeDropdown) {
        sizeDropdown.addEventListener('change', function() {
            const selectedSizeId = this.value;

            if (!selectedSizeId) {
                updateOriginalValueDisplay({box_weight:'', purchase_price:'', price_per_sqm:''});
                // Optionally clear inputs or leave them as is, depending on desired UX
                // boxWeightInput.value = ''; purchasePriceInput.value = ''; pricePerSqmInput.value = '';
                return;
            }

            fetch(`ajax_get_size_details.php?size_id=${selectedSizeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const details = data.data;
                        updateOriginalValueDisplay(details);
                        // When size changes, pre-fill input fields with the new size's defaults.
                        // The user can then edit these if they want an override for this new size.
                        prefillInputsFromSize(details);
                    } else {
                        console.error('Error fetching size details:', data.error);
                        updateOriginalValueDisplay({box_weight:'Error', purchase_price:'Error', price_per_sqm:'Error'});
                    }
                })
                .catch(error => {
                    console.error('AJAX request failed:', error);
                    updateOriginalValueDisplay({box_weight:'Error', purchase_price:'Error', price_per_sqm:'Error'});
                });
        });
        // Initial state of original values is set by PHP. No need to trigger change on load for edit form
        // unless we want to immediately overwrite potentially saved overrides if the initial size had different values,
        // which is not the desired behavior for an edit form. The PHP pre-fills the inputs correctly with overrides or size defaults.
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
