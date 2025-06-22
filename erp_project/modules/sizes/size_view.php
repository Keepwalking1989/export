<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

$size_id = null;
$size_details = null;
$error_message = '';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $size_id = trim($_GET["id"]);

    $sql_fetch = "SELECT * FROM sizes WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $size_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $size_details = $result->fetch_assoc();
            } else {
                $error_message = "Error: Size not found.";
            }
        } else {
            $error_message = "Error fetching size data: " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $error_message = "Error preparing statement: " . $conn->error;
    }
} else {
    $error_message = "No size ID specified.";
}

// Calculate SQFT for display
$sqft_per_box_display = '';
if ($size_details && !empty($size_details['sqm_per_box'])) {
    $conversionFactor = 10.7639;
    $sqft_per_box_display = number_format((float)$size_details['sqm_per_box'] * $conversionFactor, 4);
}

?>

<div class="container">
    <h2>View Size Details</h2>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
            <a href="size_list.php" class="btn btn-link">Back to List</a>
        </div>
    <?php elseif ($size_details): ?>
        <?php
            $full_size_desc = htmlspecialchars($size_details['size_prefix'] . " [" . $size_details['size_text'] . "]");
        ?>
        <table class="table table-bordered table-striped">
            <tr>
                <th>ID</th>
                <td><?php echo htmlspecialchars($size_details['id']); ?></td>
            </tr>
            <tr>
                <th>Full Size Description</th>
                <td><?php echo $full_size_desc; ?></td>
            </tr>
             <tr>
                <th>Size Prefix</th>
                <td><?php echo htmlspecialchars($size_details['size_prefix']); ?></td>
            </tr>
            <tr>
                <th>Size Text</th>
                <td><?php echo htmlspecialchars($size_details['size_text']); ?></td>
            </tr>
            <tr>
                <th>SQM Per Box</th>
                <td><?php echo htmlspecialchars(number_format((float)$size_details['sqm_per_box'], 4)); ?></td>
            </tr>
            <tr>
                <th>SQFT Per Box (Calculated)</th>
                <td><?php echo htmlspecialchars($sqft_per_box_display); ?></td>
            </tr>
            <tr>
                <th>Box Weight (KG)</th>
                <td><?php echo htmlspecialchars(number_format((float)$size_details['box_weight'], 2)); ?></td>
            </tr>
            <tr>
                <th>Purchase Price (per box/unit)</th>
                <td><?php echo htmlspecialchars(number_format((float)$size_details['purchase_price'], 2)); ?></td>
            </tr>
            <tr>
                <th>Price Per SQM (Selling)</th>
                <td><?php echo htmlspecialchars(number_format((float)$size_details['price_per_sqm'], 2)); ?></td>
            </tr>
            <tr>
                <th>HSN Code</th>
                <td><?php echo htmlspecialchars($size_details['hsn_code']); ?></td>
            </tr>
            <tr>
                <th>Pallet Details</th>
                <td><?php echo nl2br(htmlspecialchars($size_details['pallet_details'])); ?></td>
            </tr>
            <tr>
                <th>Created At</th>
                <td><?php echo htmlspecialchars($size_details['created_at']); ?></td>
            </tr>
            <tr>
                <th>Last Updated At</th>
                <td><?php echo htmlspecialchars($size_details['updated_at']); ?></td>
            </tr>
        </table>
        <a href="size_list.php" class="btn btn-primary">Back to List</a>
        <a href="size_edit.php?id=<?php echo $size_details['id']; ?>" class="btn btn-warning">Edit Size</a>
    <?php else: ?>
        <div class="alert alert-info">Size details could not be loaded. Please return to the <a href="size_list.php">size list</a>.</div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
