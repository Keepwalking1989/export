<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

$client_id = null;
$client = null;

// Check if ID is set
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $client_id = trim($_GET["id"]);

    // Fetch client data
    $sql_fetch = "SELECT * FROM clients WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $client_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $client = $result->fetch_assoc();
            } else {
                echo "<div class='alert alert-danger'>Error: Client not found.</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Error fetching client data.</div>";
        }
        $stmt_fetch->close();
    } else {
        echo "<div class='alert alert-danger'>Error preparing statement: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='alert alert-warning'>No client ID specified.</div>";
}

// $conn->close(); // Connection closed by footer
?>

<div class="container">
    <?php if ($client): ?>
        <h2>View Client Details: <?php echo htmlspecialchars($client['name']); ?></h2>
        <table class="table table-bordered">
            <tr>
                <th>ID</th>
                <td><?php echo htmlspecialchars($client['id']); ?></td>
            </tr>
            <tr>
                <th>Name</th>
                <td><?php echo htmlspecialchars($client['name']); ?></td>
            </tr>
            <tr>
                <th>Contact Person</th>
                <td><?php echo htmlspecialchars($client['contact_person']); ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?php echo htmlspecialchars($client['email']); ?></td>
            </tr>
            <tr>
                <th>Phone</th>
                <td><?php echo htmlspecialchars($client['phone']); ?></td>
            </tr>
            <tr>
                <th>Address</th>
                <td><?php echo nl2br(htmlspecialchars($client['address'])); ?></td>
            </tr>
            <tr>
                <th>VAT Number</th>
                <td><?php echo htmlspecialchars($client['vat_number']); ?></td>
            </tr>
            <tr>
                <th>Created At</th>
                <td><?php echo htmlspecialchars($client['created_at']); ?></td>
            </tr>
            <tr>
                <th>Last Updated At</th>
                <td><?php echo htmlspecialchars($client['updated_at']); ?></td>
            </tr>
        </table>
        <a href="client_list.php" class="btn btn-primary">Back to List</a>
        <a href="client_edit.php?id=<?php echo $client['id']; ?>" class="btn btn-warning">Edit Client</a>
    <?php else: ?>
        <p>Client details could not be loaded. Please return to the <a href="client_list.php">client list</a>.</p>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
