<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search term
$search_term = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';

// Get total records for pagination
$sql_total = "SELECT COUNT(id) AS total FROM sizes";
if (!empty($search_term)) {
    $sql_total .= " WHERE size_text LIKE '%$search_term%' OR size_prefix LIKE '%$search_term%' OR hsn_code LIKE '%$search_term%'";
}
$result_total = $conn->query($sql_total);
$total_records = $result_total->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Fetch size data with search and pagination
$sql = "SELECT id, size_text, size_prefix, sqm_per_box, box_weight, hsn_code FROM sizes";
if (!empty($search_term)) {
    $sql .= " WHERE size_text LIKE '%$search_term%' OR size_prefix LIKE '%$search_term%' OR hsn_code LIKE '%$search_term%'";
}
$sql .= " ORDER BY size_prefix, size_text ASC LIMIT $start, $limit";

$result = $conn->query($sql);
?>

<div class="container">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Size List</h2>
        </div>
        <div class="col-md-6">
            <form action="size_list.php" method="GET" class="form-inline float-md-right">
                <input type="text" name="search" class="form-control mr-sm-2" placeholder="Search Sizes..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-outline-success my-2 my-sm-0">Search</button>
                <?php if (!empty($search_term)): ?>
                    <a href="size_list.php" class="btn btn-outline-secondary ml-2">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <a href="size_add.php" class="btn btn-primary mb-3">Add New Size</a>

    <?php if ($result && $result->num_rows > 0): ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Size Description</th>
                    <th>SQM/Box</th>
                    <th>Box Weight (KG)</th>
                    <th>HSN Code</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        // Construct full size description for display
                        $full_size_desc = htmlspecialchars($row['size_prefix'] . " [" . $row['size_text'] . "]");
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo $full_size_desc; ?></td>
                        <td><?php echo htmlspecialchars(number_format((float)$row['sqm_per_box'], 4)); ?></td>
                        <td><?php echo htmlspecialchars(number_format((float)$row['box_weight'], 2)); ?></td>
                        <td><?php echo htmlspecialchars($row['hsn_code']); ?></td>
                        <td class="action-buttons">
                            <a href="size_view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                            <a href="size_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="size_delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this size? This could affect products using this size.');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="size_list.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>">Previous</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                        <a class="page-link" href="size_list.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="size_list.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>

    <?php elseif (!empty($search_term)): ?>
        <div class="alert alert-info">No sizes found matching your search criteria. <a href="size_list.php">Show all sizes</a>.</div>
    <?php else: ?>
        <div class="alert alert-info">No sizes found. <a href="size_add.php">Add a new size</a>.</div>
    <?php endif; ?>
</div>

<?php
if ($result) $result->free();
require_once '../../includes/footer.php';
?>
