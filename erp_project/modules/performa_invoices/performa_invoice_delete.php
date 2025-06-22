<?php
require_once '../../includes/db_connect.php';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $pi_id = trim($_GET["id"]);

    // Phase 1: Delete only the main performa_invoice record.
    // In a future phase, we'll need to delete associated items from performa_invoice_items table first,
    // or ensure ON DELETE CASCADE is set up on the foreign key from items to performa_invoices.

    $conn->begin_transaction(); // Start transaction

    try {
        // TODO: When items table exists, delete items first:
        // $sql_delete_items = "DELETE FROM performa_invoice_items WHERE performa_invoice_id = ?";
        // if ($stmt_items = $conn->prepare($sql_delete_items)) {
        //     $stmt_items->bind_param("i", $pi_id);
        //     $stmt_items->execute();
        //     $stmt_items->close();
        // } else {
        //     throw new Exception("Error preparing to delete items: " . $conn->error);
        // }

        $sql_delete_pi = "DELETE FROM performa_invoices WHERE id = ?";
        if ($stmt_pi = $conn->prepare($sql_delete_pi)) {
            $stmt_pi->bind_param("i", $pi_id);
            if (!$stmt_pi->execute()) {
                throw new Exception("Error deleting performa invoice header: " . $stmt_pi->error);
            }
            $stmt_pi->close();
        } else {
            throw new Exception("Error preparing to delete PI header: " . $conn->error);
        }

        $conn->commit(); // Commit transaction
        header("location: performa_invoice_list.php?deleted=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        $error_message = "Could not delete Performa Invoice. " . $e->getMessage();
        // Log the detailed error: error_log($e->getMessage());
        header("location: performa_invoice_list.php?deleted=error&err_msg=" . urlencode($error_message));
        exit();
    }

} else {
    // No ID provided
    header("location: performa_invoice_list.php?deleted=no_id&err_msg=" . urlencode("No Performa Invoice ID provided for deletion."));
    exit();
}
?>
