<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables for form fields and errors
$bank_name = $bank_address = $account_number = $swift_code = $ifsc_code = $current_balance = "";
$errors = [];

// --- POST request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
        // Check if account number is unique (optional, DB constraint should also handle this)
        $sql_check_ac = "SELECT id FROM banks WHERE account_number = ? LIMIT 1";
        if ($stmt_check = $conn->prepare($sql_check_ac)) {
            $stmt_check->bind_param("s", $account_number);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors["account_number"] = "This account number already exists.";
            }
            $stmt_check->close();
        }
    }
    if (!empty($current_balance) && !is_numeric($current_balance)) {
        $errors["current_balance"] = "Current Balance must be a number.";
    }
    // Add other validations as needed (e.g., for swift/ifsc formats if strict rules apply)
    // --- End Validations ---

    if (empty($errors)) {
        $sql = "INSERT INTO banks (bank_name, bank_address, account_number, swift_code, ifsc_code, current_balance) VALUES (?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            // s s s s s d (5 strings, 1 decimal/double)
            $stmt->bind_param("sssssd",
                $param_bank_name, $param_bank_address, $param_account_number,
                $param_swift_code, $param_ifsc_code, $param_current_balance
            );

            $param_bank_name = $bank_name;
            $param_bank_address = !empty($bank_address) ? $bank_address : null;
            $param_account_number = $account_number;
            $param_swift_code = !empty($swift_code) ? $swift_code : null;
            $param_ifsc_code = !empty($ifsc_code) ? $ifsc_code : null;
            $param_current_balance = !empty($current_balance) ? (float)$current_balance : 0.00;

            if ($stmt->execute()) {
                header("location: bank_list.php?status=success_add");
                exit();
            } else {
                if ($conn->errno == 1062) { // Error code for duplicate entry
                     $errors['db_error'] = "Database Error: A bank with this account number already exists.";
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
    <h2>Add New Bank</h2>
    <p>Enter the details for the new bank account.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
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
                    <small class="form-text text-muted">Initial balance, user input for now.</small>
                </div>
            </div>
        </div>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Submit">
            <a href="bank_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
