<?php
require_once '../../includes/db_connect.php';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $product_id = trim($_GET["id"]);

    $sql = "DELETE FROM products WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $product_id;

        if ($stmt->execute()) {
            header("location: product_list.php?deleted=success");
            exit();
        } else {
            // Error during deletion
            header("location: product_list.php?deleted=error&err_msg=" . urlencode("Could not delete product. A database error occurred."));
            exit();
        }
        $stmt->close();
    } else {
        // Error preparing statement
        header("location: product_list.php?deleted=error_prepare&err_msg=" . urlencode("Database error: Could not prepare delete statement."));
        exit();
    }
    // $conn->close(); // Connection closed automatically
} else {
    // No ID provided
    header("location: product_list.php?deleted=no_id&err_msg=" . urlencode("No Product ID provided for deletion."));
    exit();
}
?>
