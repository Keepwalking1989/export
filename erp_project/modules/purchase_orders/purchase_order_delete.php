<?php
require_once '../../includes/db_connect.php';

if (isset($_GET["id"]) && !empty(trim($_GET["id"])) && filter_var(trim($_GET['id']), FILTER_VALIDATE_INT)) {
    $po_id = (int)trim($_GET["id"]);

    // Because of ON DELETE CASCADE on performa_invoice_id in purchase_order_items,
    // items associated with this PO will be deleted automatically by the database
    // when the PO header is deleted.

    $sql = "DELETE FROM purchase_orders WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $po_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                header("location: purchase_order_list.php?deleted=success");
                exit();
            } else {
                // No rows affected, likely PO ID didn't exist, or was already deleted.
                header("location: purchase_order_list.php?deleted=notfound&err_msg=" . urlencode("Purchase Order not found or already deleted."));
                exit();
            }
        } else {
            // Error during deletion
            // Foreign key constraints from other tables (if any in future) might prevent deletion here if not handled.
            header("location: purchase_order_list.php?deleted=error&err_msg=" . urlencode("Could not delete Purchase Order. Database error: " . $stmt->error));
            exit();
        }
        $stmt->close();
    } else {
        // Error preparing statement
        header("location: purchase_order_list.php?deleted=error_prepare&err_msg=" . urlencode("Database error: Could not prepare delete statement. " . $conn->error));
        exit();
    }
    // $conn->close(); // Connection closed automatically
} else {
    // No ID provided or invalid ID
    header("location: purchase_order_list.php?deleted=no_id&err_msg=" . urlencode("No valid Purchase Order ID provided for deletion."));
    exit();
}
?>
