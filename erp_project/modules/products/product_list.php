<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search term
$search_term = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';

// Base SQL for counting total records
$sql_total_base = "FROM products p JOIN sizes s ON p.size_id = s.id";
$sql_where_clause = "";

if (!empty($search_term)) {
    $sql_where_clause = " WHERE (p.design_name LIKE '%$search_term%'
                             OR p.product_type LIKE '%$search_term%'
                             OR s.size_text LIKE '%$search_term%'
                             OR s.size_prefix LIKE '%$search_term%'
                             OR p.product_code LIKE '%$search_term%')";
}

$sql_total = "SELECT COUNT(p.id) AS total " . $sql_total_base . $sql_where_clause;
$result_total = $conn->query($sql_total);
$total_records = $result_total->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Fetch product data with search and pagination
// Using COALESCE to get override value if available, otherwise value from sizes table
$sql = "SELECT
            p.id, p.design_name, p.product_type, p.product_code,
            s.size_text, s.size_prefix,
            COALESCE(p.box_weight_override, s.box_weight) AS effective_box_weight,
            COALESCE(p.purchase_price_override, s.purchase_price) AS effective_purchase_price,
            COALESCE(p.price_per_sqm_override, s.price_per_sqm) AS effective_price_per_sqm
        FROM products p
        JOIN sizes s ON p.size_id = s.id
        $sql_where_clause
        ORDER BY s.size_prefix, s.size_text, p.design_name ASC
        LIMIT $start, $limit";

$result = $conn->query($sql);

if (!$result) {
    // Handle query error, e.g., log it or display a user-friendly message
    echo "<div class='container'><div class='alert alert-danger'>Error fetching products: " . $conn->error . "</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Product List</h2>
        </div>
        <div class="col-md-6">
            <form action="product_list.php" method="GET" class="form-inline float-md-right">
                <input type="text" name="search" class="form-control mr-sm-2" placeholder="Search Products..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-outline-success my-2 my-sm-0">Search</button>
                <?php if (!empty($search_term)): ?>
                    <a href="product_list.php" class="btn btn-outline-secondary ml-2">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <a href="product_add.php" class="btn btn-primary mb-3">Add New Product(s)</a>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success' && isset($_GET['count'])): ?>
        <div class="alert alert-success">
            Successfully added <?php echo htmlspecialchars((int)$_GET['count']); ?> product(s).
        </div>
    <?php endif; ?>


    <?php if ($result && $result->num_rows > 0): ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Size</th>
                    <th>Design Name</th>
                    <th>Product Type</th>
                    <th>Eff. Box Wt. (KG)</th>
                    <th>Eff. Purch. Price</th>
                    <th>Eff. Price/SQM</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $full_size_desc = htmlspecialchars($row['size_prefix'] . " [" . $row['size_text'] . "]");
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo $full_size_desc; ?></td>
                        <td><?php echo htmlspecialchars($row['design_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['product_type']); ?></td>
                        <td><?php echo htmlspecialchars(number_format((float)$row['effective_box_weight'], 2)); ?></td>
                        <td><?php echo htmlspecialchars(number_format((float)$row['effective_purchase_price'], 2)); ?></td>
                        <td><?php echo htmlspecialchars(number_format((float)$row['effective_price_per_sqm'], 2)); ?></td>
                        <td class="action-buttons">
                            <a href="product_view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                            <a href="product_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="product_delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="product_list.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>">Previous</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                        <a class="page-link" href="product_list.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="product_list.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>

    <?php elseif (!empty($search_term)): ?>
        <div class="alert alert-info">No products found matching your search criteria. <a href="product_list.php">Show all products</a>.</div>
    <?php else: ?>
        <div class="alert alert-info">No products found. <a href="product_add.php">Add new product(s)</a>.</div>
    <?php endif; ?>
</div>

<?php
if ($result) $result->free();
require_once '../../includes/footer.php';
?>
