<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables
$bank_id = null;
$bank_name = $bank_address = $account_number = $swift_code = $ifsc_code = $current_balance = "";
$errors = [];

// --- POST Request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["id"]) && !empty(trim($_POST["id"]))) {
    $bank_id = trim($_POST["id"]);
    // Assign POST values for processing and sticky form
    $bank_name = trim($_POST["bank_name"]);
    $bank_address = trim($_POST["bank_address"]);
    $account_number = trim($_POST["account_number"]);
    $swift_code = trim($_POST["swift_code"]);
    $ifsc_code = trim($_POST["ifsc_code"]);
    $current_balance = trim($_POST["current_balance"]);

    // --- Validations ---
    if (empty($bank_name)) {
        $errors["bank_name"] = "Please enter the bank name.";
    }
    if (empty($account_number)) {
        $errors["account_number"] = "Please enter the account number.";
    } else {
        // Check if account number is unique (for other records)
        $sql_check_ac = "SELECT id FROM banks WHERE account_number = ? AND id != ? LIMIT 1";
        if ($stmt_check = $conn->prepare($sql_check_ac)) {
            $stmt_check->bind_param("si", $account_number, $bank_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors["account_number"] = "This account number already exists for another bank.";
            }
            $stmt_check->close();
        }
    }
    if (!empty($current_balance) && !is_numeric($current_balance)) {
        $errors["current_balance"] = "Current Balance must be a number.";
    }
    // --- End Validations ---

    if (empty($errors)) {
        $sql_update = "UPDATE banks SET bank_name=?, bank_address=?, account_number=?, swift_code=?, ifsc_code=?, current_balance=? WHERE id=?";

        if ($stmt_update = $conn->prepare($sql_update)) {
            // s s s s s d i (5 strings, 1 decimal, 1 int)
            $stmt_update->bind_param("sssssdi",
                $param_bank_name, $param_bank_address, $param_account_number,
                $param_swift_code, $param_ifsc_code, $param_current_balance,
                $param_id
            );

            $param_bank_name = $bank_name;
            $param_bank_address = !empty($bank_address) ? $bank_address : null;
            $param_account_number = $account_number;
            $param_swift_code = !empty($swift_code) ? $swift_code : null;
            $param_ifsc_code = !empty($ifsc_code) ? $ifsc_code : null;
            $param_current_balance = !empty($current_balance) ? (float)$current_balance : null; // Store null if empty
            $param_id = $bank_id;

            if ($stmt_update->execute()) {
                header("location: bank_list.php?status=success_edit");
                exit();
            } else {
                 if ($conn->errno == 1062) {
                     $errors['db_error'] = "Database Error: A bank with this account number already exists.";
                } else {
                     $errors['db_error'] = "Error updating bank: " . $stmt_update->error;
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
    $bank_id = trim($_GET["id"]);
    $sql_fetch = "SELECT * FROM banks WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $bank_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $bank_name = $row['bank_name'];
                $bank_address = $row['bank_address'];
                $account_number = $row['account_number'];
                $swift_code = $row['swift_code'];
                $ifsc_code = $row['ifsc_code'];
                $current_balance = $row['current_balance'];
            } else {
                $errors['load_error'] = "Error: Bank not found for ID " . htmlspecialchars($bank_id) . ".";
            }
        } else {
            $errors['load_error'] = "Error fetching bank data: " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $errors['load_error'] = "Error preparing fetch statement: " . $conn->error;
    }
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
     $errors['load_error'] = "No Bank ID specified for editing.";
}
// --- End of GET Request Processing ---

require_once '../../includes/header.php'; // Include header AFTER processing

if ($_SERVER["REQUEST_METHOD"] != "POST" && !empty($errors['load_error'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>" . htmlspecialchars($errors['load_error']) . " <a href='bank_list.php' class='alert-link'>Back to List</a>.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <h2>Edit Bank Account</h2>
    <p>Update the details for this bank account.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>
    <?php if (($_SERVER["REQUEST_METHOD"] == "POST") && !empty($errors['load_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
    <?php endif; ?>


    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($bank_id); ?>"/>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Bank Name <span class="text-danger">*</span></label>
                    <input type="text" name="bank_name" class="form-control <?php echo isset($errors['bank_name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($bank_name); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['bank_name'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Bank A/C No. <span class="text-danger">*</span></label>
                    <input type="text" name="account_number" class="form-control <?php echo isset($errors['account_number']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($account_number); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['account_number'] ?? '';?></span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Bank Address</label>
            <textarea name="bank_address" class="form-control" rows="3"><?php echo htmlspecialchars($bank_address); ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Swift Code</label>
                    <input type="text" name="swift_code" class="form-control" value="<?php echo htmlspecialchars($swift_code); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>IFSC Code</label>
                    <input type="text" name="ifsc_code" class="form-control" value="<?php echo htmlspecialchars($ifsc_code); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Current Balance</label>
                    <input type="number" step="0.01" name="current_balance" class="form-control <?php echo isset($errors['current_balance']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($current_balance); ?>">
                    <span class="invalid-feedback"><?php echo $errors['current_balance'] ?? '';?></span>
                </div>
            </div>
        </div>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Update">
            <a href="bank_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
