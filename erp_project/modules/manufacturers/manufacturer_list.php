<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$start = ($page - 1) * $limit;

$search_term = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';

$sql_total = "SELECT COUNT(id) AS total FROM manufacturers";
if (!empty($search_term)) {
    $sql_total .= " WHERE name LIKE '%$search_term%' OR email LIKE '%$search_term%' OR contact_person LIKE '%$search_term%'";
}
$result_total = $conn->query($sql_total);
$total_records = $result_total->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Added gst_number and pincode to the SELECT statement
$sql = "SELECT id, name, contact_person, email, phone, gst_number, pincode FROM manufacturers";
if (!empty($search_term)) {
    // Added gst_number and pincode to search criteria
    $sql .= " WHERE name LIKE '%$search_term%' OR email LIKE '%$search_term%' OR contact_person LIKE '%$search_term%' OR gst_number LIKE '%$search_term%' OR pincode LIKE '%$search_term%'";
}
$sql .= " ORDER BY name ASC LIMIT $start, $limit";

$result = $conn->query($sql);
?>

<div class="container">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Manufacturer List</h2>
        </div>
        <div class="col-md-6">
            <form action="manufacturer_list.php" method="GET" class="form-inline float-md-right">
                <input type="text" name="search" class="form-control mr-sm-2" placeholder="Search Manufacturers" value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-outline-success my-2 my-sm-0">Search</button>
                 <?php if (!empty($search_term)): ?>
                    <a href="manufacturer_list.php" class="btn btn-outline-secondary ml-2">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <a href="manufacturer_add.php" class="btn btn-primary mb-3">Add New Manufacturer</a>

    <?php if ($result && $result->num_rows > 0): ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Contact Person</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>GST Number</th>
                    <th>Pincode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['contact_person']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['gst_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['pincode']); ?></td>
                        <td class="action-buttons">
                            <a href="manufacturer_view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                            <a href="manufacturer_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="manufacturer_delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this manufacturer?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="manufacturer_list.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>">Previous</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                        <a class="page-link" href="manufacturer_list.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="manufacturer_list.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>

    <?php elseif (!empty($search_term)): ?>
        <div class="alert alert-info">No manufacturers found matching your search criteria. <a href="manufacturer_list.php">Show all manufacturers</a>.</div>
    <?php else: ?>
        <div class="alert alert-info">No manufacturers found. <a href="manufacturer_add.php">Add a new manufacturer</a>.</div>
    <?php endif; ?>
</div>

<?php
if ($result) $result->free();
require_once '../../includes/footer.php';
?>
