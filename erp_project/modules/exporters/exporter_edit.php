<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables
$exporter_id = null;
$company_name = $person_name = $contact_number = $email = $website = "";
$address = $gst_number = $iec_code = $city = $state = $country = $pincode = "";
$errors = [];

// --- POST Request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["id"]) && !empty(trim($_POST["id"]))) {
    $exporter_id = trim($_POST["id"]);
    // Assign POST values
    $company_name = trim($_POST["company_name"]);
    $person_name = trim($_POST["person_name"]);
    $contact_number = trim($_POST["contact_number"]);
    $email = trim($_POST["email"]);
    $website = trim($_POST["website"]);
    $address = trim($_POST["address"]);
    $gst_number = trim($_POST["gst_number"]);
    $iec_code = trim($_POST["iec_code"]);
    $city = trim($_POST["city"]);
    $state = trim($_POST["state"]);
    $country = trim($_POST["country"]);
    $pincode = trim($_POST["pincode"]);

    // --- Validations ---
    if (empty($company_name)) {
        $errors["company_name"] = "Please enter the company name.";
    }
    // Check for uniqueness: email, gst_number, iec_code (if provided and changed)
    if (!empty($email)) {
        $sql_check_email = "SELECT id FROM exporters WHERE email = ? AND id != ? LIMIT 1";
        if ($stmt_check = $conn->prepare($sql_check_email)) {
            $stmt_check->bind_param("si", $email, $exporter_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors["email"] = "This email address already exists for another exporter.";
            }
            $stmt_check->close();
        }
    }
    if (!empty($gst_number)) {
        $sql_check_gst = "SELECT id FROM exporters WHERE gst_number = ? AND id != ? LIMIT 1";
        if ($stmt_check = $conn->prepare($sql_check_gst)) {
            $stmt_check->bind_param("si", $gst_number, $exporter_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors["gst_number"] = "This GST number already exists for another exporter.";
            }
            $stmt_check->close();
        }
    }
    if (!empty($iec_code)) {
        $sql_check_iec = "SELECT id FROM exporters WHERE iec_code = ? AND id != ? LIMIT 1";
        if ($stmt_check = $conn->prepare($sql_check_iec)) {
            $stmt_check->bind_param("si", $iec_code, $exporter_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors["iec_code"] = "This IEC code already exists for another exporter.";
            }
            $stmt_check->close();
        }
    }
    // --- End Validations ---

    if (empty($errors)) {
        $sql_update = "UPDATE exporters SET company_name=?, person_name=?, contact_number=?, email=?, website=?, address=?, gst_number=?, iec_code=?, city=?, state=?, country=?, pincode=? WHERE id=?";

        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssssssssssssi",
                $company_name, $person_name, $contact_number, $email, $website, $address,
                $gst_number, $iec_code, $city, $state, $country, $pincode, $exporter_id
            );

            // Handle NULL for empty optional fields
            $p_person_name = !empty($person_name) ? $person_name : null;
            // ... (apply to all nullable fields similarly for $stmt_update->execute if not directly binding)
            // However, direct binding with variables already set to "" or value is fine for VARCHAR/TEXT, DB will handle NULL if column allows.
            // For stricter NULL handling, set $person_name = null explicitly if empty before bind_param.

            if ($stmt_update->execute()) {
                header("location: exporter_list.php?status=success_edit");
                exit();
            } else {
                if ($conn->errno == 1062) {
                     $errors['db_error'] = "Database Error: Duplicate entry. Check Email, GST Number, or IEC Code.";
                } else {
                     $errors['db_error'] = "Error updating exporter: " . $stmt_update->error;
                }
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
    $exporter_id = trim($_GET["id"]);
    $sql_fetch = "SELECT * FROM exporters WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $exporter_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $company_name = $row['company_name'];
                $person_name = $row['person_name'];
                $contact_number = $row['contact_number'];
                $email = $row['email'];
                $website = $row['website'];
                $address = $row['address'];
                $gst_number = $row['gst_number'];
                $iec_code = $row['iec_code'];
                $city = $row['city'];
                $state = $row['state'];
                $country = $row['country'];
                $pincode = $row['pincode'];
            } else {
                $errors['load_error'] = "Error: Exporter not found for ID " . htmlspecialchars($exporter_id) . ".";
            }
        } else {
            $errors['load_error'] = "Error fetching exporter data: " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $errors['load_error'] = "Error preparing fetch statement: " . $conn->error;
    }
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
     $errors['load_error'] = "No Exporter ID specified for editing.";
}
// --- End of GET Request Processing ---

require_once '../../includes/header.php'; // Include header AFTER processing

if ($_SERVER["REQUEST_METHOD"] != "POST" && !empty($errors['load_error'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>" . htmlspecialchars($errors['load_error']) . " <a href='exporter_list.php' class='alert-link'>Back to List</a>.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <h2>Edit Exporter</h2>
    <p>Update the details for this exporter.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>
    <?php if (($_SERVER["REQUEST_METHOD"] == "POST") && !empty($errors['load_error'])): // Should not happen if ID is part of POST ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($exporter_id); ?>"/>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Company Name <span class="text-danger">*</span></label>
                    <input type="text" name="company_name" class="form-control <?php echo isset($errors['company_name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($company_name); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['company_name'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Person Name</label>
                    <input type="text" name="person_name" class="form-control" value="<?php echo htmlspecialchars($person_name); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($contact_number); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>">
                    <span class="invalid-feedback"><?php echo $errors['email'] ?? '';?></span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Website</label>
            <input type="url" name="website" class="form-control" value="<?php echo htmlspecialchars($website); ?>" placeholder="https://example.com">
        </div>

        <div class="form-group">
            <label>Address</label>
            <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($address); ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>GST Number</label>
                    <input type="text" name="gst_number" class="form-control <?php echo isset($errors['gst_number']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($gst_number); ?>">
                    <span class="invalid-feedback"><?php echo $errors['gst_number'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>IEC Code</label>
                    <input type="text" name="iec_code" class="form-control <?php echo isset($errors['iec_code']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($iec_code); ?>">
                    <span class="invalid-feedback"><?php echo $errors['iec_code'] ?? '';?></span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($city); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>State</label>
                    <input type="text" name="state" class="form-control" value="<?php echo htmlspecialchars($state); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="country" class="form-control" value="<?php echo htmlspecialchars($country); ?>">
                </div>
            </div>
        </div>
         <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?php echo htmlspecialchars($pincode); ?>">
                </div>
            </div>
        </div>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Update">
            <a href="exporter_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
