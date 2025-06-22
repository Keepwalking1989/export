<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

$bank_id = null;
$bank_details = null;
$error_message = '';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $bank_id = trim($_GET["id"]);

    $sql_fetch = "SELECT * FROM banks WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $bank_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $bank_details = $result->fetch_assoc();
            } else {
                $error_message = "Error: Bank account not found.";
            }
        } else {
            $error_message = "Error fetching bank data: " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $error_message = "Error preparing statement: " . $conn->error;
    }
} else {
    $error_message = "No bank ID specified.";
}
?>

<div class="container">
    <h2>View Bank Account Details</h2>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
            <a href="bank_list.php" class="btn btn-link">Back to List</a>
        </div>
    <?php elseif ($bank_details): ?>
        <table class="table table-bordered table-striped">
            <tr>
                <th>ID</th>
                <td><?php echo htmlspecialchars($bank_details['id']); ?></td>
            </tr>
            <tr>
                <th>Bank Name</th>
                <td><?php echo htmlspecialchars($bank_details['bank_name']); ?></td>
            </tr>
            <tr>
                <th>Account Number</th>
                <td><?php echo htmlspecialchars($bank_details['account_number']); ?></td>
            </tr>
            <tr>
                <th>Bank Address</th>
                <td><?php echo nl2br(htmlspecialchars($bank_details['bank_address'] ?? 'N/A')); ?></td>
            </tr>
            <tr>
                <th>Swift Code</th>
                <td><?php echo htmlspecialchars($bank_details['swift_code'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>IFSC Code</th>
                <td><?php echo htmlspecialchars($bank_details['ifsc_code'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Current Balance</th>
                <td><?php echo htmlspecialchars(number_format((float)($bank_details['current_balance'] ?? 0), 2)); ?></td>
            </tr>
            <tr>
                <th>Created At</th>
                <td><?php echo htmlspecialchars($bank_details['created_at']); ?></td>
            </tr>
            <tr>
                <th>Last Updated At</th>
                <td><?php echo htmlspecialchars($bank_details['updated_at']); ?></td>
            </tr>
        </table>
        <a href="bank_list.php" class="btn btn-primary">Back to List</a>
        <a href="bank_edit.php?id=<?php echo $bank_details['id']; ?>" class="btn btn-warning">Edit Bank</a>
    <?php else: ?>
        <div class="alert alert-info">Bank details could not be loaded. Please return to the <a href="bank_list.php">bank list</a>.</div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
