<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables
$name = $contact_person = $email = $phone = $address = $gst_number = $stuffing_number = $examination_date = $pincode = "";
$errors = [];

// --- POST request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $contact_person = trim($_POST["contact_person"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $gst_number = trim($_POST["gst_number"]);
    $stuffing_number = trim($_POST["stuffing_number"]);
    $examination_date = trim($_POST["examination_date"]);
    if (empty($examination_date)) { // Handle empty date for DB
        $examination_date = null;
    }
    $pincode = trim($_POST["pincode"]);

    if (empty($name)) {
        $errors["name"] = "Please enter a manufacturer name.";
    }
    // Add other validations as needed...

    if (empty($errors)) {
        $sql = "INSERT INTO manufacturers (name, contact_person, email, phone, address, gst_number, stuffing_number, examination_date, pincode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssssssss", $name, $contact_person, $email, $phone, $address, $gst_number, $stuffing_number, $examination_date, $pincode);
            if ($stmt->execute()) {
                header("location: manufacturer_list.php?status=success_add");
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
    <h2>Add New Manufacturer</h2>
    <p>Please fill this form to create a new manufacturer record.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Manufacturer Name <span class="text-danger">*</span></label>
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
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>">
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
                    <label>GST Number</label>
                    <input type="text" name="gst_number" class="form-control" value="<?php echo htmlspecialchars($gst_number); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Stuffing Number</label>
                    <input type="text" name="stuffing_number" class="form-control" value="<?php echo htmlspecialchars($stuffing_number); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Examination Date</label>
                    <input type="date" name="examination_date" class="form-control" value="<?php echo htmlspecialchars($examination_date ?? ''); // Handle null for date input ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?php echo htmlspecialchars($pincode); ?>">
                </div>
            </div>
        </div>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Submit">
            <a href="manufacturer_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
