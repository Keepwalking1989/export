<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables for form fields and errors
$name = $contact_person = $email = $phone = $address = $vat_number = "";
$errors = [];

// --- POST request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Assign POST values for processing and sticky form
    $name = trim($_POST["name"]);
    $contact_person = trim($_POST["contact_person"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $vat_number = trim($_POST["vat_number"]);

    // Validate name
    if (empty($name)) {
        $errors["name"] = "Please enter a client name.";
    }
    // Add other validations as needed...

    // Check input errors before inserting into database
    if (empty($errors)) {
        $sql = "INSERT INTO clients (name, contact_person, email, phone, address, vat_number) VALUES (?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssssss", $param_name, $param_contact_person, $param_email, $param_phone, $param_address, $param_vat_number);

            $param_name = $name;
            $param_contact_person = $contact_person;
            $param_email = $email;
            $param_phone = $phone;
            $param_address = $address;
            $param_vat_number = $vat_number;

            if ($stmt->execute()) {
                header("location: client_list.php?status=success_add");
                exit();
            } else {
                $errors['db_error'] = "Something went wrong. Please try again later. Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors['db_error'] = "Error preparing statement: " . $conn->error;
        }
    }
}
// --- End of POST request Processing ---

// Include header AFTER POST processing (if no redirect occurred)
require_once '../../includes/header.php';
?>

<div class="container">
    <h2>Add New Client</h2>
    <p>Please fill this form to create a new client record.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Client Name <span class="text-danger">*</span></label>
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

        <div class="form-group">
            <label>VAT Number</label>
            <input type="text" name="vat_number" class="form-control" value="<?php echo htmlspecialchars($vat_number); ?>">
        </div>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Submit">
            <a href="client_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
