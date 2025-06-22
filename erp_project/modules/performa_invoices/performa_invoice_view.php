<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/header.php';

$pi_id = null;
$pi_details = null;
$pi_items = []; // To store fetched items
$error_message = '';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $pi_id = trim($_GET["id"]);

    // Fetch PI Header
    $sql_fetch_header = "SELECT
                            pi.*,
                            e.company_name AS exporter_company_name,
                            c.name AS consignee_name,
                            b.bank_name AS bank_account_name,
                            b.account_number AS bank_account_number
                         FROM performa_invoices pi
                         JOIN exporters e ON pi.exporter_id = e.id
                         JOIN clients c ON pi.consignee_id = c.id
                         LEFT JOIN banks b ON pi.bank_id = b.id
                         WHERE pi.id = ?";

    if ($stmt_fetch_header = $conn->prepare($sql_fetch_header)) {
        $stmt_fetch_header->bind_param("i", $pi_id);
        if ($stmt_fetch_header->execute()) {
            $result_header = $stmt_fetch_header->get_result();
            if ($result_header->num_rows == 1) {
                $pi_details = $result_header->fetch_assoc();

                // Fetch PI Items if header is found
                $sql_fetch_items = "SELECT
                                        pii.*,
                                        s.size_text, s.size_prefix, s.sqm_per_box AS size_sqm_per_box,
                                        p.design_name, p.product_type
                                    FROM performa_invoice_items pii
                                    JOIN sizes s ON pii.size_id = s.id
                                    JOIN products p ON pii.product_id = p.id
                                    WHERE pii.performa_invoice_id = ?
                                    ORDER BY pii.id ASC"; // Or some other logical order
                if ($stmt_fetch_items = $conn->prepare($sql_fetch_items)) {
                    $stmt_fetch_items->bind_param("i", $pi_id);
                    $stmt_fetch_items->execute();
                    $result_items = $stmt_fetch_items->get_result();
                    while($item_row = $result_items->fetch_assoc()){
                        $pi_items[] = $item_row;
                    }
                    $stmt_fetch_items->close();
                } else {
                    $error_message .= " Error fetching PI items: " . $conn->error;
                }

            } else {
                $error_message = "Error: Performa Invoice not found.";
            }
        } else {
            $error_message = "Error fetching Performa Invoice header data: " . $stmt_fetch_header->error;
        }
        $stmt_fetch_header->close();
    } else {
        $error_message = "Error preparing header statement: " . $conn->error;
    }
} else {
    $error_message = "No Performa Invoice ID specified.";
}
?>

<div class="container">
    <h2>View Performa Invoice Details (ID: <?php echo htmlspecialchars($pi_id); ?>)</h2>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
            <a href="performa_invoice_list.php" class="btn btn-link">Back to List</a>
        </div>
    <?php elseif ($pi_details): ?>
        <div class="card">
            <div class="card-header">
                <h4>Invoice: <?php echo htmlspecialchars($pi_details['invoice_number']); ?></h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Exporter:</strong> <?php echo htmlspecialchars($pi_details['exporter_company_name']); ?></p>
                        <p><strong>Invoice Date:</strong> <?php echo htmlspecialchars(date("d-M-Y", strtotime($pi_details['invoice_date']))); ?></p>
                        <p><strong>Consignee:</strong> <?php echo htmlspecialchars($pi_details['consignee_name']); ?></p>
                        <p><strong>Final Destination:</strong> <?php echo htmlspecialchars($pi_details['final_destination'] ?? 'N/A'); ?></p>
                        <p><strong>Total Container(s):</strong> <?php echo htmlspecialchars($pi_details['total_container'] ?? 'N/A'); ?></p>
                        <p><strong>Container Size:</strong> <?php echo htmlspecialchars($pi_details['container_size'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Currency Type:</strong> <?php echo htmlspecialchars($pi_details['currency_type'] ?? 'N/A'); ?></p>
                        <p><strong>Total Gross Weight (KG):</strong> <?php echo htmlspecialchars(number_format((float)($pi_details['total_gross_weight_kg'] ?? 0), 2)); ?></p>
                        <p><strong>Bank:</strong>
                            <?php
                            if (!empty($pi_details['bank_account_name'])) {
                                echo htmlspecialchars($pi_details['bank_account_name'] . ' (A/C: ' . $pi_details['bank_account_number'] . ')');
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                        <p><strong>Freight Amount:</strong> <?php echo htmlspecialchars(number_format((float)($pi_details['freight_amount'] ?? 0), 2)); ?> <?php echo htmlspecialchars($pi_details['currency_type'] ?? ''); ?></p>
                        <p><strong>Discount Amount:</strong> <?php echo htmlspecialchars(number_format((float)($pi_details['discount_amount'] ?? 0), 2)); ?> <?php echo htmlspecialchars($pi_details['currency_type'] ?? ''); ?></p>
                    </div>
                </div>
                <hr>
                <h5>Notify Party</h5>
                <p><strong>Line 1:</strong> <?php echo nl2br(htmlspecialchars($pi_details['notify_party_line1'] ?? 'N/A')); ?></p>
                <p><strong>Line 2:</strong> <?php echo nl2br(htmlspecialchars($pi_details['notify_party_line2'] ?? 'N/A')); ?></p>
                <hr>
                <h5>Terms & Conditions</h5>
                <p><?php echo nl2br(htmlspecialchars($pi_details['terms_delivery_payment'] ?? 'N/A')); ?></p>
                <hr>
                <h5>Note</h5>
                <p><?php echo nl2br(htmlspecialchars($pi_details['note'] ?? 'N/A')); ?></p>
                <hr>
                <div class="mt-3">
                    <h5>Invoice Items</h5>
                    <?php if (!empty($pi_items)): ?>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Size</th>
                                <th>Product (Design Name)</th>
                                <th>Boxes</th>
                                <th>Rate/SQM</th>
                                <th>Comm. %</th>
                                <th>Qty (SQM)</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $item_counter = 1;
                            $total_boxes = 0;
                            $total_quantity_sqm = 0;
                            $total_amount = 0;
                            foreach($pi_items as $item):
                                $item_full_size = htmlspecialchars($item['size_prefix'] . " [" . $item['size_text'] . "]");
                                $total_boxes += (float)$item['boxes'];
                                $total_quantity_sqm += (float)$item['quantity_sqm'];
                                $total_amount += (float)$item['amount'];
                            ?>
                            <tr>
                                <td><?php echo $item_counter++; ?></td>
                                <td><?php echo $item_full_size; ?></td>
                                <td><?php echo htmlspecialchars($item['design_name']); ?></td>
                                <td><?php echo htmlspecialchars(number_format((float)$item['boxes'], 2)); ?></td>
                                <td><?php echo htmlspecialchars(number_format((float)$item['rate_per_sqm'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($item['commission_percentage'] !== null ? number_format((float)$item['commission_percentage'], 2).'%' : 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(number_format((float)$item['quantity_sqm'], 4)); ?></td>
                                <td><?php echo htmlspecialchars(number_format((float)$item['amount'], 2)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right">Totals:</th>
                                <th><?php echo htmlspecialchars(number_format($total_boxes, 2)); ?></th>
                                <th colspan="2"></th> <!-- Rate and Comm columns -->
                                <th><?php echo htmlspecialchars(number_format($total_quantity_sqm, 4)); ?></th>
                                <th><?php echo htmlspecialchars(number_format($total_amount, 2)); ?> <?php echo htmlspecialchars($pi_details['currency_type'] ?? ''); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                    <?php else: ?>
                    <p class="text-muted">No items found for this Performa Invoice.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer">
                <a href="performa_invoice_list.php" class="btn btn-primary">Back to List</a>
                <a href="performa_invoice_edit.php?id=<?php echo $pi_details['id']; ?>" class="btn btn-warning">Edit Invoice</a>
                <!-- PDF/PI buttons will go here -->
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Performa Invoice details could not be loaded. Please return to the <a href="performa_invoice_list.php">list</a>.</div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
