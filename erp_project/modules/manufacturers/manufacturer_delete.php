<?php
require_once '../../includes/db_connect.php';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $manufacturer_id = trim($_GET["id"]);

    $sql = "DELETE FROM manufacturers WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $manufacturer_id;

        if ($stmt->execute()) {
            header("location: manufacturer_list.php?deleted=success");
            exit();
        } else {
            header("location: manufacturer_list.php?deleted=error");
            exit();
        }
        $stmt->close();
    } else {
        header("location: manufacturer_list.php?deleted=error_prepare");
        exit();
    }
} else {
    header("location: manufacturer_list.php?deleted=no_id");
    exit();
}
?>
