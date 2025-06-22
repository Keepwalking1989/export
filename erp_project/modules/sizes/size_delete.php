<?php
require_once '../../includes/db_connect.php'; // For database connection

// Check if ID is set and not empty
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $size_id = trim($_GET["id"]);

    // Prepare a delete statement
    // Consider foreign key constraints: if products are linked to sizes,
    // you might want to prevent deletion or handle it (e.g., set product's size_id to NULL if allowed, or show an error).
    // For now, we'll proceed with direct deletion. A more robust system would check dependencies.
    $sql = "DELETE FROM sizes WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $size_id;

        if ($stmt->execute()) {
            // Records deleted successfully. Redirect to landing page.
            header("location: size_list.php?deleted=success");
            exit();
        } else {
            // If deletion fails (e.g., due to foreign key constraints if not handled by ON DELETE SET NULL/CASCADE)
            // It's good to provide a more specific error if possible, but stmt->error might not always be user-friendly.
            // For now, a generic error or redirecting with an error status.
            // In a real-world app, you might log $stmt->error.
            header("location: size_list.php?deleted=error&err_msg=" . urlencode("Could not delete size. It might be in use or a database error occurred."));
            exit();
        }
        $stmt->close();
    } else {
        // Error preparing statement
        header("location: size_list.php?deleted=error_prepare&err_msg=" . urlencode("Database error: Could not prepare delete statement."));
        exit();
    }
    // $conn->close(); // Connection will be closed by PHP automatically at script end
} else {
    // If ID is not set, or empty, redirect with an error
    header("location: size_list.php?deleted=no_id&err_msg=" . urlencode("No ID provided for deletion."));
    exit();
}
?>
