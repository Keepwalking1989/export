<?php
require_once '../../includes/db_connect.php';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $supplier_id = trim($_GET["id"]);

    $sql = "DELETE FROM suppliers WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $supplier_id;

        if ($stmt->execute()) {
            header("location: supplier_list.php?deleted=success");
            exit();
        } else {
            header("location: supplier_list.php?deleted=error");
            exit();
        }
        $stmt->close();
    } else {
        header("location: supplier_list.php?deleted=error_prepare");
        exit();
    }
} else {
    header("location: supplier_list.php?deleted=no_id");
    exit();
}
?>
