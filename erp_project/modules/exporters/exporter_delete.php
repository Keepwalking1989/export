<?php
require_once '../../includes/db_connect.php';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $exporter_id = trim($_GET["id"]);

    // Consider if there are dependencies on this exporter record (e.g., in Performa Invoices)
    // For now, direct delete. In a real system, you might prevent deletion if linked.
    $sql = "DELETE FROM exporters WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $exporter_id;

        if ($stmt->execute()) {
            header("location: exporter_list.php?deleted=success");
            exit();
        } else {
            // It's possible deletion fails due to foreign key constraints if this exporter is used elsewhere.
            header("location: exporter_list.php?deleted=error&err_msg=" . urlencode("Could not delete exporter. It might be in use or a database error occurred."));
            exit();
        }
        $stmt->close();
    } else {
        header("location: exporter_list.php?deleted=error_prepare&err_msg=" . urlencode("Database error: Could not prepare delete statement."));
        exit();
    }
} else {
    header("location: exporter_list.php?deleted=no_id&err_msg=" . urlencode("No Exporter ID provided for deletion."));
    exit();
}
?>
