<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables
$name = $contact_person = $gst_number = $phone = $address = $product_category = "";
$errors = [];

// --- POST request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $contact_person = trim($_POST["contact_person"]);
    $gst_number = trim($_POST["gst_number"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $product_category = trim($_POST["product_category"]);

    if (empty($name)) {
        $errors["name"] = "Please enter a supplier name.";
    }
    // Add other validations...

    if (empty($errors)) {
        $sql = "INSERT INTO suppliers (name, contact_person, gst_number, phone, address, product_category) VALUES (?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssssss", $name, $contact_person, $gst_number, $phone, $address, $product_category);
            if ($stmt->execute()) {
                header("location: supplier_list.php?status=success_add");
                exit();
            } else {
                $errors['db_error'] = "Something went wrong. Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors['db_error'] = "Error preparing statement: " . $conn->error;
        }
    }
}
// --- End of POST request Processing ---

require_once '../../includes/header.php'; // Include header AFTER POST processing
?>

<div class="container">
    <h2>Add New Supplier</h2>
    <p>Please fill this form to create a new supplier record.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Supplier Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['name'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?php echo htmlspecialchars($contact_person); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>GST Number</label>
                    <input type="text" name="gst_number" class="form-control" value="<?php echo htmlspecialchars($gst_number); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Address</label>
            <textarea name="address" class="form-control"><?php echo htmlspecialchars($address); ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Product Category</label>
                    <input type="text" name="product_category" class="form-control" value="<?php echo htmlspecialchars($product_category); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <!-- Placeholder for a potential next field in this row -->
            </div>
        </div>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Submit">
            <a href="supplier_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
