<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

$manufacturer_id = null;
$manufacturer = null;

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $manufacturer_id = trim($_GET["id"]);

    $sql_fetch = "SELECT * FROM manufacturers WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $manufacturer_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $manufacturer = $result->fetch_assoc();
            } else {
                echo "<div class='alert alert-danger'>Error: Manufacturer not found.</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Error fetching manufacturer data.</div>";
        }
        $stmt_fetch->close();
    } else {
        echo "<div class='alert alert-danger'>Error preparing statement: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='alert alert-warning'>No manufacturer ID specified.</div>";
}
?>

<div class="container">
    <?php if ($manufacturer): ?>
        <h2>View Manufacturer Details: <?php echo htmlspecialchars($manufacturer['name']); ?></h2>
        <table class="table table-bordered">
            <tr>
                <th>ID</th>
                <td><?php echo htmlspecialchars($manufacturer['id']); ?></td>
            </tr>
            <tr>
                <th>Name</th>
                <td><?php echo htmlspecialchars($manufacturer['name']); ?></td>
            </tr>
            <tr>
                <th>Contact Person</th>
                <td><?php echo htmlspecialchars($manufacturer['contact_person']); ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?php echo htmlspecialchars($manufacturer['email']); ?></td>
            </tr>
            <tr>
                <th>Phone</th>
                <td><?php echo htmlspecialchars($manufacturer['phone']); ?></td>
            </tr>
            <tr>
                <th>Address</th>
                <td><?php echo nl2br(htmlspecialchars($manufacturer['address'])); ?></td>
            </tr>
            <tr>
                <th>GST Number</th>
                <td><?php echo htmlspecialchars($manufacturer['gst_number']); ?></td>
            </tr>
            <tr>
                <th>Stuffing Number</th>
                <td><?php echo htmlspecialchars($manufacturer['stuffing_number']); ?></td>
            </tr>
            <tr>
                <th>Examination Date</th>
                <td><?php echo htmlspecialchars($manufacturer['examination_date']); ?></td>
            </tr>
            <tr>
                <th>Pincode</th>
                <td><?php echo htmlspecialchars($manufacturer['pincode']); ?></td>
            </tr>
            <tr>
                <th>Created At</th>
                <td><?php echo htmlspecialchars($manufacturer['created_at']); ?></td>
            </tr>
            <tr>
                <th>Last Updated At</th>
                <td><?php echo htmlspecialchars($manufacturer['updated_at']); ?></td>
            </tr>
        </table>
        <a href="manufacturer_list.php" class="btn btn-primary">Back to List</a>
        <a href="manufacturer_edit.php?id=<?php echo $manufacturer['id']; ?>" class="btn btn-warning">Edit Manufacturer</a>
    <?php else: ?>
        <p>Manufacturer details could not be loaded. Please return to the <a href="manufacturer_list.php">manufacturer list</a>.</p>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
