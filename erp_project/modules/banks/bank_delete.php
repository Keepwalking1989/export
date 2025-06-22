<?php
require_once '../../includes/db_connect.php';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $bank_id = trim($_GET["id"]);

    // Consider if there are dependencies on this bank record (e.g., in transactions)
    // For now, direct delete. In a real system, you might prevent deletion if linked.
    $sql = "DELETE FROM banks WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $bank_id;

        if ($stmt->execute()) {
            header("location: bank_list.php?deleted=success");
            exit();
        } else {
            header("location: bank_list.php?deleted=error&err_msg=" . urlencode("Could not delete bank account. Database error."));
            exit();
        }
        $stmt->close();
    } else {
        header("location: bank_list.php?deleted=error_prepare&err_msg=" . urlencode("Database error: Could not prepare delete statement."));
        exit();
    }
} else {
    header("location: bank_list.php?deleted=no_id&err_msg=" . urlencode("No Bank ID provided for deletion."));
    exit();
}
?>
