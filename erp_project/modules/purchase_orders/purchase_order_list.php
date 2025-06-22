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
$sql_total_base = "FROM purchase_orders po
                   JOIN exporters e ON po.exporter_id = e.id
                   JOIN manufacturers m ON po.manufacturer_id = m.id";
$sql_where_clause = "";

if (!empty($search_term)) {
    $sql_where_clause = " WHERE (po.po_number LIKE '%$search_term%'
                             OR e.company_name LIKE '%$search_term%'
                             OR m.name LIKE '%$search_term%')";
}

$sql_total = "SELECT COUNT(po.id) AS total " . $sql_total_base . $sql_where_clause;
$result_total = $conn->query($sql_total);
$total_records = ($result_total) ? $result_total->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $limit);

// Fetch purchase order data
$sql = "SELECT
            po.id, po.po_number, po.po_date, po.number_of_containers,
            e.company_name AS exporter_name,
            m.name AS manufacturer_name
        FROM purchase_orders po
        JOIN exporters e ON po.exporter_id = e.id
        JOIN manufacturers m ON po.manufacturer_id = m.id
        $sql_where_clause
        ORDER BY po.po_date DESC, po.po_number DESC
        LIMIT $start, $limit";

$result = $conn->query($sql);

if (!$result) {
    echo "<div class='container'><div class='alert alert-danger'>Error fetching purchase orders: " . $conn->error . "</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Purchase Order List</h2>
        </div>
        <div class="col-md-6">
            <form action="purchase_order_list.php" method="GET" class="form-inline float-md-right">
                <input type="text" name="search" class="form-control mr-sm-2" placeholder="Search POs..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-outline-success my-2 my-sm-0">Search</button>
                <?php if (!empty($search_term)): ?>
                    <a href="purchase_order_list.php" class="btn btn-outline-secondary ml-2">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <!-- Normally, POs are created from PIs, so a direct "Add New PO" button might not be primary. -->
    <!-- <a href="purchase_order_add.php" class="btn btn-primary mb-3">Add New Purchase Order</a> -->
     <p><em>Purchase Orders are typically created from Performa Invoices.</em></p>


    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] == 'success_add'): ?>
            <div class="alert alert-success">New Purchase Order (ID: <?php echo htmlspecialchars($_GET['id'] ?? ''); ?>) added successfully.</div>
        <?php elseif ($_GET['status'] == 'success_edit'): ?>
            <div class="alert alert-success">Purchase Order updated successfully.</div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 'success'): ?>
        <div class="alert alert-success">Purchase Order deleted successfully.</div>
    <?php elseif (isset($_GET['deleted']) && $_GET['deleted'] != 'success' && isset($_GET['err_msg'])): ?>
        <div class="alert alert-danger">Error deleting Purchase Order: <?php echo htmlspecialchars(urldecode($_GET['err_msg'])); ?></div>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>PO Number</th>
                    <th>PO Date</th>
                    <th>Exporter</th>
                    <th>Manufacturer</th>
                    <th>No. of Containers</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['po_number']); ?></td>
                        <td><?php echo htmlspecialchars(date("d-M-Y", strtotime($row['po_date']))); ?></td>
                        <td><?php echo htmlspecialchars($row['exporter_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['manufacturer_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['number_of_containers'] ?? 'N/A'); ?></td>
                        <td class="action-buttons">
                            <a href="purchase_order_view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm" title="View">View</a>
                            <a href="purchase_order_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm" title="Edit">Edit</a>
                            <button type="button" class="btn btn-secondary btn-sm" title="Generate PDF (Not Implemented)" disabled>PDF</button>
                            <button type="button" class="btn btn-light btn-sm border" title="ED (Not Implemented)" disabled>ED</button>
                            <a href="purchase_order_delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Are you sure you want to delete this purchase order? This will delete associated items as well.');">Del</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="purchase_order_list.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>">Previous</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                        <a class="page-link" href="purchase_order_list.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="purchase_order_list.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>

    <?php elseif (!empty($search_term)): ?>
        <div class="alert alert-info">No purchase orders found matching your search criteria. <a href="purchase_order_list.php">Show all</a>.</div>
    <?php else: ?>
        <div class="alert alert-info">No purchase orders found. Purchase Orders are typically created from Performa Invoices.</div>
    <?php endif; ?>
</div>

<?php
if ($result) $result->free();
require_once '../../includes/footer.php';
?>
