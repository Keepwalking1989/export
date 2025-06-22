<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

$exporter_id = null;
$exporter_details = null;
$error_message = '';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $exporter_id = trim($_GET["id"]);

    $sql_fetch = "SELECT * FROM exporters WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $exporter_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $exporter_details = $result->fetch_assoc();
            } else {
                $error_message = "Error: Exporter not found.";
            }
        } else {
            $error_message = "Error fetching exporter data: " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $error_message = "Error preparing statement: " . $conn->error;
    }
} else {
    $error_message = "No exporter ID specified.";
}
?>

<div class="container">
    <h2>View Exporter Details</h2>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
            <a href="exporter_list.php" class="btn btn-link">Back to List</a>
        </div>
    <?php elseif ($exporter_details): ?>
        <table class="table table-bordered table-striped">
            <tr>
                <th>ID</th>
                <td><?php echo htmlspecialchars($exporter_details['id']); ?></td>
            </tr>
            <tr>
                <th>Company Name</th>
                <td><?php echo htmlspecialchars($exporter_details['company_name']); ?></td>
            </tr>
            <tr>
                <th>Person Name</th>
                <td><?php echo htmlspecialchars($exporter_details['person_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Contact Number</th>
                <td><?php echo htmlspecialchars($exporter_details['contact_number'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?php echo htmlspecialchars($exporter_details['email'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Website</th>
                <td>
                    <?php if (!empty($exporter_details['website'])): ?>
                        <a href="<?php echo htmlspecialchars($exporter_details['website']); ?>" target="_blank"><?php echo htmlspecialchars($exporter_details['website']); ?></a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Address</th>
                <td><?php echo nl2br(htmlspecialchars($exporter_details['address'] ?? 'N/A')); ?></td>
            </tr>
            <tr>
                <th>GST Number</th>
                <td><?php echo htmlspecialchars($exporter_details['gst_number'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>IEC Code</th>
                <td><?php echo htmlspecialchars($exporter_details['iec_code'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>City</th>
                <td><?php echo htmlspecialchars($exporter_details['city'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>State</th>
                <td><?php echo htmlspecialchars($exporter_details['state'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Country</th>
                <td><?php echo htmlspecialchars($exporter_details['country'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Pincode</th>
                <td><?php echo htmlspecialchars($exporter_details['pincode'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Created At</th>
                <td><?php echo htmlspecialchars($exporter_details['created_at']); ?></td>
            </tr>
            <tr>
                <th>Last Updated At</th>
                <td><?php echo htmlspecialchars($exporter_details['updated_at']); ?></td>
            </tr>
        </table>
        <a href="exporter_list.php" class="btn btn-primary">Back to List</a>
        <a href="exporter_edit.php?id=<?php echo $exporter_details['id']; ?>" class="btn btn-warning">Edit Exporter</a>
    <?php else: ?>
        <div class="alert alert-info">Exporter details could not be loaded. Please return to the <a href="exporter_list.php">exporter list</a>.</div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
