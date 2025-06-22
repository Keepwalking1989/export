<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables
$manufacturer_id = null;
$name = $contact_person = $email = $phone = $address = "";
$gst_number = $stuffing_number = $examination_date = $pincode = "";
$errors = [];

// --- POST Request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["id"]) && !empty(trim($_POST["id"]))) {
    $manufacturer_id = trim($_POST["id"]);
    // Assign POST values for processing and sticky form on error
    $name = trim($_POST["name"]);
    $contact_person = trim($_POST["contact_person"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $gst_number = trim($_POST["gst_number"]);
    $stuffing_number = trim($_POST["stuffing_number"]);
    $examination_date = trim($_POST["examination_date"]);
    if (empty($examination_date)) { $examination_date = null; }
    $pincode = trim($_POST["pincode"]);

    if (empty($name)) {
        $errors["name"] = "Please enter a manufacturer name.";
    }
    // Add other validations...

    if (empty($errors)) {
        $sql_update = "UPDATE manufacturers SET name=?, contact_person=?, email=?, phone=?, address=?, gst_number=?, stuffing_number=?, examination_date=?, pincode=? WHERE id=?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("sssssssssi", $name, $contact_person, $email, $phone, $address, $gst_number, $stuffing_number, $examination_date, $pincode, $manufacturer_id);
            if ($stmt_update->execute()) {
                header("location: manufacturer_list.php?status=success_edit");
                exit();
            } else {
                $errors['db_error'] = "Error updating manufacturer: " . $stmt_update->error;
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
    $manufacturer_id = trim($_GET["id"]);
    $sql_fetch = "SELECT * FROM manufacturers WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $manufacturer_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $name = $row['name'];
                $contact_person = $row['contact_person'];
                $email = $row['email'];
                $phone = $row['phone'];
                $address = $row['address'];
                $gst_number = $row['gst_number'];
                $stuffing_number = $row['stuffing_number'];
                $examination_date = $row['examination_date'];
                $pincode = $row['pincode'];
            } else {
                $errors['load_error'] = "Error: Manufacturer not found for ID " . htmlspecialchars($manufacturer_id) . ".";
            }
        } else {
            $errors['load_error'] = "Error fetching manufacturer data: " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $errors['load_error'] = "Error preparing fetch statement: " . $conn->error;
    }
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
    $errors['load_error'] = "No Manufacturer ID specified for editing.";
}
// --- End of GET Request Processing ---

require_once '../../includes/header.php'; // Include header AFTER processing

if ($_SERVER["REQUEST_METHOD"] != "POST" && !empty($errors['load_error'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>" . htmlspecialchars($errors['load_error']) . " <a href='manufacturer_list.php' class='alert-link'>Back to List</a>.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <h2>Edit Manufacturer</h2>
    <p>Please update the manufacturer details and submit.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>
     <?php if (($_SERVER["REQUEST_METHOD"] == "POST") && !empty($errors['load_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($manufacturer_id); ?>"/>

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
            <input type="submit" class="btn btn-primary" value="Update">
            <a href="manufacturer_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
