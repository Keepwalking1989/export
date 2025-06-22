<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables
$client_id = null;
$name = $contact_person = $email = $phone = $address = $vat_number = "";
$errors = [];

// --- POST Request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["id"]) && !empty(trim($_POST["id"]))) {
    $client_id = trim($_POST["id"]);
    // Assign POST values for processing and sticky form on error
    $name = trim($_POST["name"]);
    $contact_person = trim($_POST["contact_person"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $vat_number = trim($_POST["vat_number"]);

    if (empty($name)) {
        $errors["name"] = "Please enter a client name.";
    }
    // Add other validations as needed...

    if (empty($errors)) {
        $sql_update = "UPDATE clients SET name=?, contact_person=?, email=?, phone=?, address=?, vat_number=? WHERE id=?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssssssi", $name, $contact_person, $email, $phone, $address, $vat_number, $client_id);
            if ($stmt_update->execute()) {
                header("location: client_list.php?status=success_edit");
                exit();
            } else {
                $errors['db_error'] = "Something went wrong. Please try again later. Error: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors['db_error'] = "Error preparing statement: " . $conn->error;
        }
    }
}
// --- End of POST Request Processing ---
// --- GET Request Processing (for initial form load) ---
elseif (isset($_GET["id"]) && !empty(trim($_GET["id"])) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $client_id = trim($_GET["id"]);
    $sql_fetch = "SELECT * FROM clients WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $client_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $name = $row['name'];
                $contact_person = $row['contact_person'];
                $email = $row['email'];
                $phone = $row['phone'];
                $address = $row['address'];
                $vat_number = $row['vat_number'];
            } else {
                $errors['load_error'] = "Error: Client not found for ID " . htmlspecialchars($client_id) . ".";
            }
        } else {
            $errors['load_error'] = "Error fetching client data for ID " . htmlspecialchars($client_id) . ". " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $errors['load_error'] = "Error preparing fetch statement: " . $conn->error;
    }
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
    $errors['load_error'] = "No Client ID specified for editing.";
}
// --- End of GET Request Processing ---

// Include header AFTER processing
require_once '../../includes/header.php';

// If critical load error on GET, display message and exit
if ($_SERVER["REQUEST_METHOD"] != "POST" && !empty($errors['load_error'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>" . htmlspecialchars($errors['load_error']) . " <a href='client_list.php' class='alert-link'>Back to List</a>.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <h2>Edit Client</h2>
    <p>Please update the client details and submit.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>
    <?php if (($_SERVER["REQUEST_METHOD"] == "POST") && !empty($errors['load_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($client_id); ?>"/>

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
            <input type="submit" class="btn btn-primary" value="Update">
            <a href="client_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
