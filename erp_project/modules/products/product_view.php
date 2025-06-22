<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

$product_id = null;
$product_details = null;
$error_message = '';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $product_id = trim($_GET["id"]);

    $sql_fetch = "SELECT
                    p.id, p.design_name, p.product_type, p.product_code, p.description,
                    p.created_at, p.updated_at,
                    s.size_text, s.size_prefix, s.sqm_per_box,
                    COALESCE(p.box_weight_override, s.box_weight) AS effective_box_weight,
                    COALESCE(p.purchase_price_override, s.purchase_price) AS effective_purchase_price,
                    COALESCE(p.price_per_sqm_override, s.price_per_sqm) AS effective_price_per_sqm,
                    p.box_weight_override, s.box_weight AS original_size_box_weight,
                    p.purchase_price_override, s.purchase_price AS original_size_purchase_price,
                    p.price_per_sqm_override, s.price_per_sqm AS original_size_price_per_sqm
                  FROM products p
                  JOIN sizes s ON p.size_id = s.id
                  WHERE p.id = ?";

    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $product_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $product_details = $result->fetch_assoc();
            } else {
                $error_message = "Error: Product not found.";
            }
        } else {
            $error_message = "Error fetching product data: " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $error_message = "Error preparing statement: " . $conn->error;
    }
} else {
    $error_message = "No product ID specified.";
}

?>

<div class="container">
    <h2>View Product Details</h2>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
            <a href="product_list.php" class="btn btn-link">Back to List</a>
        </div>
    <?php elseif ($product_details): ?>
        <?php
            $full_size_desc = htmlspecialchars($product_details['size_prefix'] . " [" . $product_details['size_text'] . "]");
            $sqft_per_box_display = '';
            if (!empty($product_details['sqm_per_box'])) {
                $conversionFactor = 10.7639;
                $sqft_per_box_display = number_format((float)$product_details['sqm_per_box'] * $conversionFactor, 4);
            }
        ?>
        <table class="table table-bordered table-striped">
            <tr>
                <th>Product ID</th>
                <td><?php echo htmlspecialchars($product_details['id']); ?></td>
            </tr>
            <tr>
                <th>Design Name</th>
                <td><?php echo htmlspecialchars($product_details['design_name']); ?></td>
            </tr>
            <tr>
                <th>Product Code</th>
                <td><?php echo htmlspecialchars($product_details['product_code'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Full Size</th>
                <td><?php echo $full_size_desc; ?></td>
            </tr>
            <tr>
                <th>Product Type</th>
                <td><?php echo htmlspecialchars($product_details['product_type'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>SQM Per Box (from Size)</th>
                <td><?php echo htmlspecialchars(number_format((float)$product_details['sqm_per_box'], 4)); ?></td>
            </tr>
            <tr>
                <th>SQFT Per Box (Calculated, from Size)</th>
                <td><?php echo htmlspecialchars($sqft_per_box_display); ?></td>
            </tr>
            <tr>
                <th>Effective Box Weight (KG)</th>
                <td>
                    <?php echo htmlspecialchars(number_format((float)$product_details['effective_box_weight'], 2)); ?>
                    <?php if ($product_details['box_weight_override'] !== null): ?>
                        <small class="text-muted">(Override. Original: <?php echo htmlspecialchars(number_format((float)$product_details['original_size_box_weight'], 2)); ?>)</small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Effective Purchase Price</th>
                <td>
                    <?php echo htmlspecialchars(number_format((float)$product_details['effective_purchase_price'], 2)); ?>
                    <?php if ($product_details['purchase_price_override'] !== null): ?>
                        <small class="text-muted">(Override. Original: <?php echo htmlspecialchars(number_format((float)$product_details['original_size_purchase_price'], 2)); ?>)</small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Effective Price Per SQM (Selling)</th>
                <td>
                    <?php echo htmlspecialchars(number_format((float)$product_details['effective_price_per_sqm'], 2)); ?>
                    <?php if ($product_details['price_per_sqm_override'] !== null): ?>
                        <small class="text-muted">(Override. Original: <?php echo htmlspecialchars(number_format((float)$product_details['original_size_price_per_sqm'], 2)); ?>)</small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Description</th>
                <td><?php echo nl2br(htmlspecialchars($product_details['description'] ?? 'N/A')); ?></td>
            </tr>
            <tr>
                <th>Created At</th>
                <td><?php echo htmlspecialchars($product_details['created_at']); ?></td>
            </tr>
            <tr>
                <th>Last Updated At</th>
                <td><?php echo htmlspecialchars($product_details['updated_at']); ?></td>
            </tr>
        </table>
        <a href="product_list.php" class="btn btn-primary">Back to List</a>
        <a href="product_edit.php?id=<?php echo $product_details['id']; ?>" class="btn btn-warning">Edit Product</a>
    <?php else: ?>
        <div class="alert alert-info">Product details could not be loaded. Please return to the <a href="product_list.php">product list</a>.</div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
