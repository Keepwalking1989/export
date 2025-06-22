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
$sql_total = "SELECT COUNT(id) AS total FROM exporters";
if (!empty($search_term)) {
    $sql_total .= " WHERE company_name LIKE '%$search_term%'
                      OR person_name LIKE '%$search_term%'
                      OR email LIKE '%$search_term%'
                      OR gst_number LIKE '%$search_term%'
                      OR iec_code LIKE '%$search_term%'
                      OR city LIKE '%$search_term%'";
}
$result_total = $conn->query($sql_total);
$total_records = $result_total->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Fetch exporter data with search and pagination
$sql = "SELECT id, company_name, person_name, email, gst_number, iec_code, city FROM exporters";
if (!empty($search_term)) {
     $sql .= " WHERE company_name LIKE '%$search_term%'
               OR person_name LIKE '%$search_term%'
               OR email LIKE '%$search_term%'
               OR gst_number LIKE '%$search_term%'
               OR iec_code LIKE '%$search_term%'
               OR city LIKE '%$search_term%'";
}
$sql .= " ORDER BY company_name ASC LIMIT $start, $limit";

$result = $conn->query($sql);

if (!$result) {
    echo "<div class='container'><div class='alert alert-danger'>Error fetching exporter data: " . $conn->error . "</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Exporter List</h2>
        </div>
        <div class="col-md-6">
            <form action="exporter_list.php" method="GET" class="form-inline float-md-right">
                <input type="text" name="search" class="form-control mr-sm-2" placeholder="Search Exporters..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-outline-success my-2 my-sm-0">Search</button>
                <?php if (!empty($search_term)): ?>
                    <a href="exporter_list.php" class="btn btn-outline-secondary ml-2">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <a href="exporter_add.php" class="btn btn-primary mb-3">Add New Exporter</a>

    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] == 'success_add'): ?>
            <div class="alert alert-success">New exporter added successfully.</div>
        <?php elseif ($_GET['status'] == 'success_edit'): ?>
            <div class="alert alert-success">Exporter details updated successfully.</div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 'success'): ?>
        <div class="alert alert-success">Exporter deleted successfully.</div>
    <?php elseif (isset($_GET['deleted']) && $_GET['deleted'] != 'success' && isset($_GET['err_msg'])): ?>
        <div class="alert alert-danger">Error deleting exporter: <?php echo htmlspecialchars(urldecode($_GET['err_msg'])); ?></div>
    <?php endif; ?>


    <?php if ($result->num_rows > 0): ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company Name</th>
                    <th>Person Name</th>
                    <th>Email</th>
                    <th>GST No.</th>
                    <th>IEC Code</th>
                    <th>City</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['person_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['gst_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['iec_code'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['city'] ?? 'N/A'); ?></td>
                        <td class="action-buttons">
                            <a href="exporter_view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                            <a href="exporter_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="exporter_delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this exporter?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="exporter_list.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>">Previous</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                        <a class="page-link" href="exporter_list.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="exporter_list.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>

    <?php elseif (!empty($search_term)): ?>
        <div class="alert alert-info">No exporters found matching your search criteria. <a href="exporter_list.php">Show all exporters</a>.</div>
    <?php else: ?>
        <div class="alert alert-info">No exporters found. <a href="exporter_add.php">Add a new exporter</a>.</div>
    <?php endif; ?>
</div>

<?php
if ($result) $result->free();
require_once '../../includes/footer.php';
?>
