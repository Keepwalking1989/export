<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables for form fields and errors
$company_name = $person_name = $contact_number = $email = $website = "";
$address = $gst_number = $iec_code = $city = $state = $country = $pincode = "";
$errors = [];

// --- POST request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Assign POST values for processing and sticky form
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

    // Check for uniqueness: email, gst_number, iec_code (if provided)
    if (!empty($email)) {
        $sql_check_email = "SELECT id FROM exporters WHERE email = ? LIMIT 1";
        if ($stmt_check = $conn->prepare($sql_check_email)) {
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors["email"] = "This email address already exists.";
            }
            $stmt_check->close();
        }
    }
    if (!empty($gst_number)) {
        $sql_check_gst = "SELECT id FROM exporters WHERE gst_number = ? LIMIT 1";
        if ($stmt_check = $conn->prepare($sql_check_gst)) {
            $stmt_check->bind_param("s", $gst_number);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors["gst_number"] = "This GST number already exists.";
            }
            $stmt_check->close();
        }
    }
    if (!empty($iec_code)) {
        $sql_check_iec = "SELECT id FROM exporters WHERE iec_code = ? LIMIT 1";
        if ($stmt_check = $conn->prepare($sql_check_iec)) {
            $stmt_check->bind_param("s", $iec_code);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors["iec_code"] = "This IEC code already exists.";
            }
            $stmt_check->close();
        }
    }
    // --- End Validations ---

    if (empty($errors)) {
        $sql = "INSERT INTO exporters (company_name, person_name, contact_number, email, website, address, gst_number, iec_code, city, state, country, pincode)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            // All are strings or text, so 's' for all 12.
            $stmt->bind_param("ssssssssssss",
                $param_company_name, $param_person_name, $param_contact_number, $param_email,
                $param_website, $param_address, $param_gst_number, $param_iec_code,
                $param_city, $param_state, $param_country, $param_pincode
            );

            $param_company_name = $company_name;
            $param_person_name = !empty($person_name) ? $person_name : null;
            $param_contact_number = !empty($contact_number) ? $contact_number : null;
            $param_email = !empty($email) ? $email : null;
            $param_website = !empty($website) ? $website : null;
            $param_address = !empty($address) ? $address : null;
            $param_gst_number = !empty($gst_number) ? $gst_number : null;
            $param_iec_code = !empty($iec_code) ? $iec_code : null;
            $param_city = !empty($city) ? $city : null;
            $param_state = !empty($state) ? $state : null;
            $param_country = !empty($country) ? $country : null;
            $param_pincode = !empty($pincode) ? $pincode : null;

            if ($stmt->execute()) {
                header("location: exporter_list.php?status=success_add");
                exit();
            } else {
                // Check for MySQL duplicate entry error (errno 1062)
                if ($conn->errno == 1062) {
                     $errors['db_error'] = "Database Error: Duplicate entry. Check Email, GST Number, or IEC Code.";
                } else {
                     $errors['db_error'] = "Something went wrong. Please try again later. Error: " . $stmt->error;
                }
            }
            $stmt->close();
        } else {
            $errors['db_error'] = "Error preparing statement: " . $conn->error;
        }
    }
}
// --- End of POST request Processing ---

// Include header AFTER POST processing
require_once '../../includes/header.php';
?>

<div class="container">
    <h2>Add New Exporter</h2>
    <p>Enter the details for the new exporter.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
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
            <input type="submit" class="btn btn-primary" value="Submit">
            <a href="exporter_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
