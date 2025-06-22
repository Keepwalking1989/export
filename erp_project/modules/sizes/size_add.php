<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables for form fields and errors
$size_text = $sqm_per_box = $box_weight = $purchase_price = $price_per_sqm = $hsn_code = $pallet_details = "";
$size_prefix_default = "Porcelain Glazed Vitrified Tiles ( PGVT )";
$hsn_code_default = "69072100";
$_POST_size_prefix = $_POST['size_prefix'] ?? $size_prefix_default; // Capture potential POST value for prefix for sticky form

$errors = [];

// --- POST request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $size_text = trim($_POST["size_text"]);
    $sqm_per_box = trim($_POST["sqm_per_box"]);
    $box_weight = trim($_POST["box_weight"]);
    $purchase_price = trim($_POST["purchase_price"]);
    $price_per_sqm = trim($_POST["price_per_sqm"]);
    $hsn_code = trim($_POST["hsn_code"]);
    $pallet_details = trim($_POST["pallet_details"]);
    $current_size_prefix = trim($_POST["size_prefix"] ?? $size_prefix_default); // Use submitted prefix or default


    // --- Validations ---
    if (empty($size_text)) {
        $errors["size_text"] = "Please enter the size text (e.g., 60x60 CM).";
    }
    if (empty($current_size_prefix)) { // Added validation for prefix
        $errors["size_prefix"] = "Size prefix cannot be empty.";
    }
    if (!empty($sqm_per_box) && !is_numeric($sqm_per_box)) {
        $errors["sqm_per_box"] = "SQM Per Box must be a number.";
    }
    if (!empty($box_weight) && !is_numeric($box_weight)) {
        $errors["box_weight"] = "Box Weight must be a number.";
    }
    if (!empty($purchase_price) && !is_numeric($purchase_price)) {
        $errors["purchase_price"] = "Purchase Price must be a number.";
    }
    if (!empty($price_per_sqm) && !is_numeric($price_per_sqm)) {
        $errors["price_per_sqm"] = "Price Per SQM must be a number.";
    }
    if (empty($hsn_code)) {
        $errors["hsn_code"] = "HSN Code is required.";
    }
    // --- End Validations ---

    if (empty($errors)) {
        $sql = "INSERT INTO sizes (size_text, size_prefix, sqm_per_box, box_weight, purchase_price, price_per_sqm, hsn_code, pallet_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssddddss",
                $param_size_text,
                $param_size_prefix,
                $param_sqm_per_box,
                $param_box_weight,
                $param_purchase_price,
                $param_price_per_sqm,
                $param_hsn_code,
                $param_pallet_details
            );

            $param_size_text = $size_text;
            $param_size_prefix = $current_size_prefix;
            $param_sqm_per_box = !empty($sqm_per_box) ? (float)$sqm_per_box : null;
            $param_box_weight = !empty($box_weight) ? (float)$box_weight : null;
            $param_purchase_price = !empty($purchase_price) ? (float)$purchase_price : null;
            $param_price_per_sqm = !empty($price_per_sqm) ? (float)$price_per_sqm : null;
            $param_hsn_code = $hsn_code;
            $param_pallet_details = $pallet_details;

            if ($stmt->execute()) {
                // SUCCESS: Redirect BEFORE any HTML output
                header("location: size_list.php?status=success_add");
                exit(); // IMPORTANT: Always exit after a header redirect
            } else {
                $errors['db_error'] = "Something went wrong. Please try again later. Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors['db_error'] = "Error preparing statement: " . $conn->error;
        }
    }
    // If validation errors or DB error, script continues below to display form with errors
    // The $_POST_size_prefix is used to make the size_prefix field sticky on error
    $_POST_size_prefix = $current_size_prefix; // Ensure sticky value reflects what was submitted
}
// --- End of POST request Processing ---


// Now, include header.php. If redirect happened due to successful POST, this part is not reached.
require_once '../../includes/header.php';
?>

<div class="container">
    <h2>Add New Size</h2>
    <p>Define a new product size and its properties.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Size Prefix <span class="text-danger">*</span></label>
                    <input type="text" name="size_prefix" class="form-control <?php echo isset($errors['size_prefix']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($_POST_size_prefix); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['size_prefix'] ?? '';?></span>
                    <small class="form-text text-muted">e.g., Porcelain Glazed Vitrified Tiles ( PGVT )</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Size <span class="text-danger">*</span></label>
                    <input type="text" name="size_text" class="form-control <?php echo isset($errors['size_text']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($size_text); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['size_text'] ?? '';?></span>
                    <small class="form-text text-muted">Enter the variable part of the size, e.g., 60x60 CM, 800x1600 MM</small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>SQM Per Box</label>
                    <input type="number" step="0.0001" name="sqm_per_box" id="sqm_per_box" class="form-control <?php echo isset($errors['sqm_per_box']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($sqm_per_box); ?>">
                    <span class="invalid-feedback"><?php echo $errors['sqm_per_box'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>SQFT Per Box (Calculated)</label>
                    <input type="text" id="sqft_per_box_display" class="form-control" readonly>
                    <small class="form-text text-muted">SQM Per Box * 10.7639</small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Box Weight (KG)</label>
                    <input type="number" step="0.01" name="box_weight" class="form-control <?php echo isset($errors['box_weight']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($box_weight); ?>">
                    <span class="invalid-feedback"><?php echo $errors['box_weight'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>HSN Code <span class="text-danger">*</span></label>
                    <input type="text" name="hsn_code" class="form-control <?php echo isset($errors['hsn_code']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars(!empty($hsn_code) ? $hsn_code : $hsn_code_default); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['hsn_code'] ?? '';?></span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Purchase Price (per box/unit)</label>
                    <input type="number" step="0.01" name="purchase_price" class="form-control <?php echo isset($errors['purchase_price']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($purchase_price); ?>">
                    <span class="invalid-feedback"><?php echo $errors['purchase_price'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Price Per SQM (Selling)</label>
                    <input type="number" step="0.01" name="price_per_sqm" class="form-control <?php echo isset($errors['price_per_sqm']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($price_per_sqm); ?>">
                    <span class="invalid-feedback"><?php echo $errors['price_per_sqm'] ?? '';?></span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Pallet Details</label>
            <textarea name="pallet_details" class="form-control" rows="3"><?php echo htmlspecialchars($pallet_details); ?></textarea>
        </div>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Submit">
            <a href="size_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sqmInput = document.getElementById('sqm_per_box');
    const sqftDisplay = document.getElementById('sqft_per_box_display');
    const conversionFactor = 10.7639; // More precise factor

    function calculateSqft() {
        const sqmValue = parseFloat(sqmInput.value);
        if (!isNaN(sqmValue) && sqmValue > 0) {
            sqftDisplay.value = (sqmValue * conversionFactor).toFixed(4); // Display with 4 decimal places
        } else {
            sqftDisplay.value = '';
        }
    }

    if (sqmInput) {
        sqmInput.addEventListener('input', calculateSqft);
        // Calculate on page load if there's an initial value (e.g. in edit form or error resubmission)
        calculateSqft();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
