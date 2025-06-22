<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

$po_id = null;
$po_header_details = null;
$po_items = [];
$error_message = '';

if (isset($_GET["id"]) && !empty(trim($_GET["id"])) && filter_var(trim($_GET['id']), FILTER_VALIDATE_INT)) {
    $po_id = (int)trim($_GET["id"]);

    // Fetch PO Header, joining with related tables for names
    $sql_fetch_header = "SELECT
                            po.*,
                            e.company_name AS exporter_company_name,
                            m.name AS manufacturer_name,
                            pi.invoice_number AS source_pi_number
                         FROM purchase_orders po
                         JOIN exporters e ON po.exporter_id = e.id
                         JOIN manufacturers m ON po.manufacturer_id = m.id
                         JOIN performa_invoices pi ON po.performa_invoice_id = pi.id
                         WHERE po.id = ?";

    if ($stmt_fetch_header = $conn->prepare($sql_fetch_header)) {
        $stmt_fetch_header->bind_param("i", $po_id);
        if ($stmt_fetch_header->execute()) {
            $result_header = $stmt_fetch_header->get_result();
            if ($result_header->num_rows == 1) {
                $po_header_details = $result_header->fetch_assoc();

                // Fetch PO Items if header is found
                $sql_fetch_items = "SELECT
                                        poi.*,
                                        s.size_text, s.size_prefix,
                                        p.design_name
                                    FROM purchase_order_items poi
                                    JOIN sizes s ON poi.size_id = s.id
                                    JOIN products p ON poi.product_id = p.id
                                    WHERE poi.purchase_order_id = ?
                                    ORDER BY poi.id ASC";
                if ($stmt_fetch_items = $conn->prepare($sql_fetch_items)) {
                    $stmt_fetch_items->bind_param("i", $po_id);
                    $stmt_fetch_items->execute();
                    $result_items = $stmt_fetch_items->get_result();
                    while($item_row = $result_items->fetch_assoc()){
                        $po_items[] = $item_row;
                    }
                    $stmt_fetch_items->close();
                } else {
                    $error_message .= " Error fetching PO items: " . $conn->error;
                }
            } else {
                $error_message = "Error: Purchase Order not found.";
            }
        } else {
            $error_message = "Error fetching Purchase Order header data: " . $stmt_fetch_header->error;
        }
        $stmt_fetch_header->close();
    } else {
        $error_message = "Error preparing PO header statement: " . $conn->error;
    }
} else {
    $error_message = "No valid Purchase Order ID specified.";
}
?>

<div class="container">
    <h2>View Purchase Order Details (ID: <?php echo htmlspecialchars($po_id ?? ''); ?>)</h2>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
            <a href="purchase_order_list.php" class="btn btn-link">Back to List</a>
        </div>
    <?php elseif ($po_header_details): ?>
        <div class="card">
            <div class="card-header">
                <h4>PO Number: <?php echo htmlspecialchars($po_header_details['po_number']); ?></h4>
            </div>
            <div class="card-body">
                <h5>Header Details</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Source PI No.:</strong> <?php echo htmlspecialchars($po_header_details['source_pi_number']); ?></p>
                        <p><strong>Exporter:</strong> <?php echo htmlspecialchars($po_header_details['exporter_company_name']); ?></p>
                        <p><strong>Manufacturer:</strong> <?php echo htmlspecialchars($po_header_details['manufacturer_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>PO Date:</strong> <?php echo htmlspecialchars(date("d-M-Y", strtotime($po_header_details['po_date']))); ?></p>
                        <p><strong>Number of Container(s):</strong> <?php echo htmlspecialchars($po_header_details['number_of_containers'] ?? 'N/A'); ?></p>
                    </div>
                </div>
                <hr>
                <h5>Item Details</h5>
                <?php if (!empty($po_items)): ?>
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Size</th>
                            <th>Product (Design)</th>
                            <th>Description</th>
                            <th>Wt./Box (KG)</th>
                            <th>Boxes</th>
                            <th>Thickness</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $item_counter = 1;
                        $total_po_boxes = 0;
                        foreach($po_items as $item):
                            $item_full_size = htmlspecialchars(($item['size_prefix'] ?? '') . " [" . ($item['size_text'] ?? '') . "]");
                            $total_po_boxes += (float)($item['boxes'] ?? 0);
                        ?>
                        <tr>
                            <td><?php echo $item_counter++; ?></td>
                            <td><?php echo $item_full_size; ?></td>
                            <td><?php echo htmlspecialchars($item['design_name'] ?? 'N/A'); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($item['description'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars(number_format((float)($item['weight_per_box'] ?? 0), 2)); ?></td>
                            <td><?php echo htmlspecialchars(number_format((float)($item['boxes'] ?? 0), 2)); ?></td>
                            <td><?php echo htmlspecialchars($item['thickness'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                     <tfoot>
                        <tr>
                            <th colspan="5" class="text-right">Total Boxes:</th>
                            <th colspan="2"><?php echo htmlspecialchars(number_format($total_po_boxes, 2)); ?></th>
                        </tr>
                    </tfoot>
                </table>
                <?php else: ?>
                <p class="text-muted">No items found for this Purchase Order.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="purchase_order_list.php" class="btn btn-primary">Back to List</a>
                <a href="purchase_order_edit.php?id=<?php echo $po_header_details['id']; ?>" class="btn btn-warning">Edit PO</a>
                <!-- PDF/ED buttons will go here -->
            </div>
        </div>
    <?php else: // Should not happen if error_message is also empty, but as a fallback
        echo "<div class='alert alert-info'>Purchase Order details could not be loaded. Please return to the <a href='purchase_order_list.php'>list</a>.</div>";
    endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
