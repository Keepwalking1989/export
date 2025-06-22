<?php
require_once '../../includes/db_connect.php';
// No header/footer needed for a processing script like this, unless you want to show a confirmation message page

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $client_id = trim($_GET["id"]);

    // Prepare a delete statement
    $sql = "DELETE FROM clients WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $client_id;

        if ($stmt->execute()) {
            // Records deleted successfully. Redirect to landing page.
            header("location: client_list.php?deleted=success"); // Optional: add a success param
            exit();
        } else {
            // echo "Oops! Something went wrong. Please try again later.";
            header("location: client_list.php?deleted=error"); // Optional: add an error param
            exit();
        }
        $stmt->close();
    } else {
        // echo "Error preparing statement: " . $conn->error;
        header("location: client_list.php?deleted=error_prepare"); // Optional: add an error param
        exit();
    }
    // $conn->close(); // Connection will be closed by PHP automatically
} else {
    // If ID is not set, or empty, redirect with an error or show message
    // echo "Error: No ID was provided for deletion.";
    header("location: client_list.php?deleted=no_id"); // Optional: add an error param
    exit();
}
?>
