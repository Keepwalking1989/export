<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables
$size_id = null;
$size_text = $size_prefix = $sqm_per_box = $box_weight = $purchase_price = $price_per_sqm = $hsn_code = $pallet_details = "";
$hsn_code_default = "69072100";
$size_prefix_default = "Porcelain Glazed Vitrified Tiles ( PGVT )"; // Should match add form's default
$errors = [];

// --- POST Request Processing (for form submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["id"]) && !empty(trim($_POST["id"]))) {
    $size_id = trim($_POST["id"]); // Get ID from POST for update processing

    // Assign POST values to variables for processing and sticky form on error
    $size_text = trim($_POST["size_text"]);
    $size_prefix = trim($_POST["size_prefix"]);
    $sqm_per_box = trim($_POST["sqm_per_box"]);
    $box_weight = trim($_POST["box_weight"]);
    $purchase_price = trim($_POST["purchase_price"]);
    $price_per_sqm = trim($_POST["price_per_sqm"]);
    $hsn_code = trim($_POST["hsn_code"]);
    $pallet_details = trim($_POST["pallet_details"]);

    // --- Validations ---
    if (empty($size_text)) {
        $errors["size_text"] = "Please enter the size text.";
    }
    if (empty($size_prefix)) {
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
        $sql_update = "UPDATE sizes SET size_text=?, size_prefix=?, sqm_per_box=?, box_weight=?, purchase_price=?, price_per_sqm=?, hsn_code=?, pallet_details=? WHERE id=?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssddddssi",
                $param_size_text, $param_size_prefix, $param_sqm_per_box, $param_box_weight,
                $param_purchase_price, $param_price_per_sqm, $param_hsn_code, $param_pallet_details,
                $param_id
            );

            $param_size_text = $size_text;
            $param_size_prefix = $size_prefix;
            $param_sqm_per_box = !empty($sqm_per_box) ? (float)$sqm_per_box : null;
            $param_box_weight = !empty($box_weight) ? (float)$box_weight : null;
            $param_purchase_price = !empty($purchase_price) ? (float)$purchase_price : null;
            $param_price_per_sqm = !empty($price_per_sqm) ? (float)$price_per_sqm : null;
            $param_hsn_code = $hsn_code;
            $param_pallet_details = $pallet_details;
            $param_id = $size_id;

            if ($stmt_update->execute()) {
                header("location: size_list.php?status=success_edit"); // Redirect on success
                exit();
            } else {
                $errors['db_error'] = "Error updating size: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors['db_error'] = "Error preparing update statement: " . $conn->error;
        }
    }
    // If validation errors or DB error, script continues to display form with current (POSTed) values and errors
}
// --- End of POST Request Processing ---
// --- GET Request Processing (for initial form load) ---
// This part only runs if it's not a POST request that successfully redirected OR if it's a POST with errors.
// If it's a POST with errors, the variables are already set from POST data above.
// If it's a GET request, we fetch from DB.
elseif (isset($_GET["id"]) && !empty(trim($_GET["id"])) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $size_id = trim($_GET["id"]);
    $sql_fetch = "SELECT * FROM sizes WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $size_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $size_text = $row['size_text'];
                $size_prefix = $row['size_prefix'];
                $sqm_per_box = $row['sqm_per_box'];
                $box_weight = $row['box_weight'];
                $purchase_price = $row['purchase_price'];
                $price_per_sqm = $row['price_per_sqm'];
                $hsn_code = $row['hsn_code'];
                $pallet_details = $row['pallet_details'];
            } else {
                $errors['load_error'] = "Error: Size not found for ID " . htmlspecialchars($size_id) . ".";
            }
        } else {
            $errors['load_error'] = "Error fetching size data for ID " . htmlspecialchars($size_id) . ". " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $errors['load_error'] = "Error preparing fetch statement: " . $conn->error;
    }
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
    // This case handles GET request without an ID, which is an error for edit page.
    $errors['load_error'] = "No Size ID specified for editing.";
}
// --- End of GET Request Processing ---


// Include header.php. If redirect happened, this part is not reached.
require_once '../../includes/header.php';

// If there was a critical load error (e.g., no ID on GET, or ID not found), display error and stop.
// We don't do this for POST errors as we want to show the form again.
if ($_SERVER["REQUEST_METHOD"] != "POST" && !empty($errors['load_error'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>" . htmlspecialchars($errors['load_error']) . " <a href='size_list.php' class='alert-link'>Back to List</a>.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <h2>Edit Size</h2>
    <p>Update the details for this size.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>
    <?php if (($_SERVER["REQUEST_METHOD"] == "POST") && !empty($errors['load_error'])): // Display load error if POST failed to find ID ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
    <?php endif; ?>


    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($size_id); ?>"/>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Size Prefix <span class="text-danger">*</span></label>
                    <input type="text" name="size_prefix" class="form-control <?php echo isset($errors['size_prefix']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($size_prefix); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['size_prefix'] ?? '';?></span>
                    <small class="form-text text-muted">e.g., Porcelain Glazed Vitrified Tiles ( PGVT )</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Size <span class="text-danger">*</span></label>
                    <input type="text" name="size_text" class="form-control <?php echo isset($errors['size_text']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($size_text); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['size_text'] ?? '';?></span>
                    <small class="form-text text-muted">e.g., 60x60 CM, 800x1600 MM</small>
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
            <input type="submit" class="btn btn-primary" value="Update">
            <a href="size_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sqmInput = document.getElementById('sqm_per_box');
    const sqftDisplay = document.getElementById('sqft_per_box_display');
    const conversionFactor = 10.7639;

    function calculateSqft() {
        const sqmValue = parseFloat(sqmInput.value);
        if (!isNaN(sqmValue) && sqmValue > 0) {
            sqftDisplay.value = (sqmValue * conversionFactor).toFixed(4);
        } else {
            sqftDisplay.value = '';
        }
    }

    if (sqmInput) {
        sqmInput.addEventListener('input', calculateSqft);
        calculateSqft(); // Calculate on page load for pre-filled value
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
