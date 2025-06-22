<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

$supplier_id = null;
$supplier = null;

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $supplier_id = trim($_GET["id"]);

    $sql_fetch = "SELECT * FROM suppliers WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $supplier_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $supplier = $result->fetch_assoc();
            } else {
                echo "<div class='alert alert-danger'>Error: Supplier not found.</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Error fetching supplier data.</div>";
        }
        $stmt_fetch->close();
    } else {
        echo "<div class='alert alert-danger'>Error preparing statement: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='alert alert-warning'>No supplier ID specified.</div>";
}
?>

<div class="container">
    <?php if ($supplier): ?>
        <h2>View Supplier Details: <?php echo htmlspecialchars($supplier['name']); ?></h2>
        <table class="table table-bordered">
            <tr>
                <th>ID</th>
                <td><?php echo htmlspecialchars($supplier['id']); ?></td>
            </tr>
            <tr>
                <th>Name</th>
                <td><?php echo htmlspecialchars($supplier['name']); ?></td>
            </tr>
            <tr>
                <th>Contact Person</th>
                <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
            </tr>
            <tr>
                <th>GST Number</th>
                <td><?php echo htmlspecialchars($supplier['gst_number']); ?></td>
            </tr>
            <tr>
                <th>Phone</th>
                <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
            </tr>
            <tr>
                <th>Address</th>
                <td><?php echo nl2br(htmlspecialchars($supplier['address'])); ?></td>
            </tr>
            <tr>
                <th>Product Category</th>
                <td><?php echo htmlspecialchars($supplier['product_category']); ?></td>
            </tr>
            <tr>
                <th>Created At</th>
                <td><?php echo htmlspecialchars($supplier['created_at']); ?></td>
            </tr>
            <tr>
                <th>Last Updated At</th>
                <td><?php echo htmlspecialchars($supplier['updated_at']); ?></td>
            </tr>
        </table>
        <a href="supplier_list.php" class="btn btn-primary">Back to List</a>
        <a href="supplier_edit.php?id=<?php echo $supplier['id']; ?>" class="btn btn-warning">Edit Supplier</a>
    <?php else: ?>
        <p>Supplier details could not be loaded. Please return to the <a href="supplier_list.php">supplier list</a>.</p>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
