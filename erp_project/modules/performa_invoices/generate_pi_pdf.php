<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../fpdf/fpdf.php'; // User confirmed path
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php'; // For numberToWords and other helpers

if (!isset($_GET['id']) || empty(trim($_GET['id'])) || !filter_var(trim($_GET['id']), FILTER_VALIDATE_INT)) {
    die("Invalid or missing Performa Invoice ID.");
}
$pi_id = (int)trim($_GET['id']);

// --- Data Fetching ---
$pi_header = null;
$exporter_details_data = null;
$client_details_data = null;
$bank_details_data = null;
$pi_items = [];

$sql_header = "SELECT
                    pi.*,
                    e.company_name AS exporter_company_name, e.address AS exporter_address,
                    e.iec_code AS exporter_iec_code,
                    c.name AS client_company_name, c.address AS client_address,
                    b.bank_name, b.account_number, b.swift_code, b.ifsc_code, b.bank_address AS beneficiary_bank_address
                 FROM performa_invoices pi
                 JOIN exporters e ON pi.exporter_id = e.id
                 JOIN clients c ON pi.consignee_id = c.id
                 LEFT JOIN banks b ON pi.bank_id = b.id
                 WHERE pi.id = ?";

if ($stmt_header = $conn->prepare($sql_header)) {
    $stmt_header->bind_param("i", $pi_id);
    if (!$stmt_header->execute()) {
        die("Database error executing PI header query: " . $stmt_header->error);
    }
    $result_header = $stmt_header->get_result();
    if ($result_header->num_rows == 1) {
        $pi_header = $result_header->fetch_assoc();
        $exporter_details_data = [
            'company_name' => $pi_header['exporter_company_name'],
            'address' => $pi_header['exporter_address'],
            'iec_code' => $pi_header['exporter_iec_code']
        ];
        $client_details_data = [
            'company_name' => $pi_header['client_company_name'],
            'address' => $pi_header['client_address']
        ];
        if ($pi_header['bank_id']) {
            $bank_details_data = [
                'beneficiary_name' => $exporter_details_data['company_name'],
                'bank_name' => $pi_header['bank_name'],
                'bank_address' => $pi_header['beneficiary_bank_address'],
                'account_number' => $pi_header['account_number'],
                'swift_code' => $pi_header['swift_code'],
                'ifsc_code' => $pi_header['ifsc_code']
            ];
        }
    } else {
        die("Performa Invoice header not found for ID: " . htmlspecialchars($pi_id));
    }
    $stmt_header->close();
} else {
    die("Database error preparing PI header query: " . $conn->error);
}

$sql_items = "SELECT
                pii.*,
                s.size_text, s.size_prefix, s.hsn_code AS item_hsn_code, s.sqm_per_box,
                p.design_name, p.product_type
              FROM performa_invoice_items pii
              JOIN sizes s ON pii.size_id = s.id
              JOIN products p ON pii.product_id = p.id
              WHERE pii.performa_invoice_id = ?
              ORDER BY pii.id ASC";
if ($stmt_items = $conn->prepare($sql_items)) {
    $stmt_items->bind_param("i", $pi_id);
    if (!$stmt_items->execute()) {
        die("Database error executing PI items query: " . $stmt_items->error);
    }
    $result_items = $stmt_items->get_result();
    while($item_row = $result_items->fetch_assoc()){
        $pi_items[] = $item_row;
    }
    $stmt_items->close();
} else {
    die("Database error preparing PI items query: " . $conn->error);
}
$conn->close();


class PDF extends FPDF {
    public function GetLMargin() { return $this->lMargin; }
    public function GetRMargin() { return $this->rMargin; }
    public function GetTMargin() { return $this->tMargin; }
    public function GetBMargin() { return $this->bMargin; }
    public function GetCMargin() { return $this->cMargin; }

    function Header() {
        $this->SetFont('Helvetica','B',18);
        $this->Cell(0,10,'PROFORMA INVOICE',0,1,'C');
        $this->Ln(1);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Helvetica','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if($nb > 0 && $s[$nb-1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while($i < $nb) {
            $c = $s[$i];
            if($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if($c == ' ') $sep = $i;
            $l += ($cw[$c] ?? 0);
            if($l > $wmax) {
                if($sep == -1) { if($i == $j) $i++; } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }

    function DrawSectionHeader($title, $width, $height = 7, $border = 1, $align = 'C') {
        $this->SetFont('Helvetica','B',10);
        $this->SetFillColor(173,216,230);
        $this->Cell($width, $height, strtoupper($title), $border, 1, $align, true);
        $this->SetFillColor(255,255,255);
    }
}

$pdf = new PDF('P','mm','A4');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 25); // Increased bottom margin for auto page break slightly for signature area
$pdf->AddPage();

$light_blue_R = 173; $light_blue_G = 216; $light_blue_B = 230;
$box_border = 1;
$line_height = 5;
$padding = 1;
$section_title_height = 7;

$page_width = $pdf->GetPageWidth() - ($pdf->GetLMargin() + $pdf->GetRMargin());
$col_width_half = ($page_width / 2) - ($padding / 2);

$y_start_boxes = $pdf->GetY();

// EXPORTER
$pdf->SetX($pdf->GetLMargin());
$pdf->SetFont('Helvetica','B',10);
$pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
$pdf->Cell($col_width_half, $section_title_height, 'EXPORTER', $box_border, 0, 'L', true);
$exporter_y_after_header = $y_start_boxes + $section_title_height;
$pdf->SetXY($pdf->GetLMargin(), $exporter_y_after_header);
$pdf->SetFont('Helvetica','B',11);
$pdf->MultiCell($col_width_half, $line_height, $exporter_details_data['company_name'] ?? '', $box_border, 'L');
$pdf->SetX($pdf->GetLMargin());
$pdf->SetFont('Helvetica','',9);
$exporter_address_text = ($exporter_details_data['address'] ?? '');
$pdf->MultiCell($col_width_half, $line_height, $exporter_address_text, $box_border, 'L');
$pdf->SetX($pdf->GetLMargin());
if(!empty($exporter_details_data['iec_code'])) $pdf->MultiCell($col_width_half, $line_height, "IEC Code: " . $exporter_details_data['iec_code'], $box_border, 'L');
$y_after_exporter_content = $pdf->GetY();

// CONSIGNEE
$consignee_x_start = $pdf->GetLMargin() + $col_width_half + $padding;
$pdf->SetXY($consignee_x_start, $y_start_boxes);
$pdf->SetFont('Helvetica','B',10);
$pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
$pdf->Cell($col_width_half, $section_title_height, 'CONSIGNEE', $box_border, 0, 'L', true);
$consignee_y_after_header = $y_start_boxes + $section_title_height;
$pdf->SetXY($consignee_x_start, $consignee_y_after_header);
$pdf->SetFont('Helvetica','B',11);
$pdf->MultiCell($col_width_half, $line_height, $client_details_data['company_name'] ?? '', $box_border, 'L');
$pdf->SetX($consignee_x_start);
$pdf->SetFont('Helvetica','',9);
$pdf->MultiCell($col_width_half, $line_height, $client_details_data['address'] ?? '', $box_border, 'L');
$pdf->SetX($consignee_x_start);
$pdf->MultiCell($col_width_half, $line_height, "Country: _________________", $box_border, 'L');
$y_after_consignee_content = $pdf->GetY();

$pdf->SetY(max($y_after_exporter_content, $y_after_consignee_content) + 1);
$pdf->Ln(0);

// --- Invoice Details Table ---
$pdf->SetFont('Helvetica','B',9);
$pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
$cell_width_invoice_details_label = $page_width * 0.25;
$cell_width_invoice_details_value = $page_width * 0.25;
$details_pairs = [
    "Proforma Invoice No." => $pi_header['invoice_number'] ?? 'N/A',
    "Date" => !empty($pi_header['invoice_date']) ? date("d-M-Y", strtotime($pi_header['invoice_date'])) : 'N/A',
    "Final Destination" => $pi_header['final_destination'] ?? 'N/A',
    "Container(s)" => ($pi_header['total_container'] ?? 'N/A') . " x " . ($pi_header['container_size'] ?? 'N/A'),
    "Currency" => "Currency: " . ($pi_header['currency_type'] ?? 'N/A'),
    "Total Gross Wt. (Kg)" => !empty($pi_header['total_gross_weight_kg']) ? number_format((float)$pi_header['total_gross_weight_kg'],2) : 'N/A'
];
$is_first_cell_in_row = true;
foreach ($details_pairs as $label => $value) {
    if ($is_first_cell_in_row) $pdf->SetX($pdf->GetLMargin());
    $pdf->SetFont('Helvetica','B',9); $pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
    $pdf->Cell($cell_width_invoice_details_label, 6, $label, 1, 0, 'L', true);
    $pdf->SetFont('Helvetica','',9); $pdf->SetFillColor(255,255,255);
    $pdf->Cell($cell_width_invoice_details_value, 6, $value, 1, ($is_first_cell_in_row ? 0 : 1), 'L', true);
    $is_first_cell_in_row = !$is_first_cell_in_row;
}
if (!$is_first_cell_in_row) $pdf->Ln();
$pdf->SetX($pdf->GetLMargin());
$pdf->SetFont('Helvetica','B',9); $pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
$pdf->Cell($cell_width_invoice_details_label, 6, "Payment Terms", 1, 0, 'L', true);
$pdf->SetFont('Helvetica','',9); $pdf->SetFillColor(255,255,255);
$payment_terms_text = $pi_header['terms_delivery_payment'] ?? 'N/A';
$payment_terms_value_width = $page_width - $cell_width_invoice_details_label - $pdf->GetLMargin() - $pdf->GetRMargin();
if($payment_terms_value_width <=0) $payment_terms_value_width = $page_width * 0.75;
$current_x_for_payment_terms_value = $pdf->GetLMargin() + $cell_width_invoice_details_label;
$pdf->SetXY($current_x_for_payment_terms_value, $pdf->GetY());
$pdf->MultiCell($payment_terms_value_width, 6, $payment_terms_text, 1, 'L', true);
$pdf->Ln(0);

if (!empty($pi_header['notify_party_line1']) || !empty($pi_header['notify_party_line2'])) {
    $pdf->SetX($pdf->GetLMargin());
    $pdf->DrawSectionHeader('NOTIFY PARTY', $page_width, $section_title_height, $box_border, 'L');
    $pdf->SetFont('Helvetica','',9);
    $notify_text = trim(($pi_header['notify_party_line1'] ?? '') . "\n" . ($pi_header['notify_party_line2'] ?? ''));
    if (!empty(trim($notify_text))) {
      $pdf->MultiCell($page_width - (2*$padding) , $line_height, $notify_text, $box_border, 'L');
    } else {
      $pdf->Cell($page_width - (2*$padding), $line_height * 2, '', $box_border, 1);
    }
    $pdf->Ln(0);
}

$pdf->SetX($pdf->GetLMargin());
$pdf->SetFont('Helvetica','B',9); $pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
$colWidthsItems = [10, 20, 65, 20, 25, 25, 25];
$pdf->Cell($colWidthsItems[0], 7, 'Sr.', 1, 0, 'C', true);
$pdf->Cell($colWidthsItems[1], 7, 'HSN Code', 1, 0, 'C', true);
$pdf->Cell($colWidthsItems[2], 7, 'Description of Goods', 1, 0, 'C', true);
$pdf->Cell($colWidthsItems[3], 7, 'Boxes', 1, 0, 'C', true);
$pdf->Cell($colWidthsItems[4], 7, 'Qty (SQM)', 1, 0, 'C', true);
$pdf->Cell($colWidthsItems[5], 7, 'Rate/' . ($pi_header['currency_type'] ?? 'SQM'), 1, 0, 'C', true);
$pdf->Cell($colWidthsItems[6], 7, 'Amount (' . ($pi_header['currency_type'] ?? '') . ')', 1, 1, 'C', true);

$pdf->SetFont('Helvetica','',8); $item_count = 0; $sub_total = 0; $total_sqm_items = 0; $total_boxes_items = 0;
foreach ($pi_items as $item) {
    $item_count++;
    $description_parts = [];
    if (!empty($item['size_prefix'])) $description_parts[] = trim($item['size_prefix']);
    if (!empty($item['size_text'])) $description_parts[] = "[" . trim($item['size_text']) . "]";
    if (!empty($item['design_name'])) $description_parts[] = trim($item['design_name']);
    if (!empty($item['product_type'])) $description_parts[] = "(" . trim($item['product_type']) . ")";
    $description = implode(' ', $description_parts);
    $hsn = $item['item_hsn_code'] ?? 'N/A';
    $boxes_val = !empty($item['boxes']) ? (float)$item['boxes'] : 0;
    $qty_sqm_val = !empty($item['quantity_sqm']) ? (float)$item['quantity_sqm'] : 0;
    $rate_val = !empty($item['rate_per_sqm']) ? (float)$item['rate_per_sqm'] : 0;
    $amount_val = !empty($item['amount']) ? (float)$item['amount'] : 0;
    $sub_total += $amount_val; $total_sqm_items += $qty_sqm_val; $total_boxes_items += $boxes_val;
    $current_y_item = $pdf->GetY();
    $numLinesDesc = $pdf->NbLines($colWidthsItems[2] - (2*$padding), $description);
    $rowHeight = $numLinesDesc * $line_height;
    if ($rowHeight < 7) $rowHeight = 7;
    if ($pdf->GetY() + $rowHeight > ($pdf->GetPageHeight() - ($pdf->GetBMargin() + 20))) {
        $pdf->AddPage();
        $pdf->SetFont('Helvetica','B',9); $pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
        $pdf->Cell($colWidthsItems[0], 7, 'Sr.', 1, 0, 'C', true); $pdf->Cell($colWidthsItems[1], 7, 'HSN Code', 1, 0, 'C', true);
        $pdf->Cell($colWidthsItems[2], 7, 'Description of Goods', 1, 0, 'C', true); $pdf->Cell($colWidthsItems[3], 7, 'Boxes', 1, 0, 'C', true);
        $pdf->Cell($colWidthsItems[4], 7, 'Qty (SQM)', 1, 0, 'C', true); $pdf->Cell($colWidthsItems[5], 7, 'Rate/' . ($pi_header['currency_type'] ?? 'SQM'), 1, 0, 'C', true);
        $pdf->Cell($colWidthsItems[6], 7, 'Amount (' . ($pi_header['currency_type'] ?? '') . ')', 1, 1, 'C', true);
        $pdf->SetFont('Helvetica','',8);
        $current_y_item = $pdf->GetY();
    }
    $x_start_row = $pdf->GetLMargin();
    $pdf->SetXY($x_start_row, $current_y_item);
    $pdf->Rect($x_start_row, $current_y_item, $colWidthsItems[0], $rowHeight); $pdf->MultiCell($colWidthsItems[0], $line_height, $item_count, 0, 'C', false); $pdf->SetXY($x_start_row + $colWidthsItems[0], $current_y_item);
    $pdf->Rect($pdf->GetX(), $current_y_item, $colWidthsItems[1], $rowHeight); $pdf->MultiCell($colWidthsItems[1]-$padding, $line_height, $hsn, 0, 'C', false,0, $pdf->GetX()+$padding/2, $current_y_item+$padding); $pdf->SetXY($x_start_row + $colWidthsItems[0] + $colWidthsItems[1], $current_y_item);
    $pdf->Rect($pdf->GetX(), $current_y_item, $colWidthsItems[2], $rowHeight); $pdf->MultiCell($colWidthsItems[2]-$padding, $line_height, $description, 0, 'L', false, 0, $pdf->GetX()+$padding, $current_y_item+$padding); $pdf->SetXY($x_start_row + array_sum(array_slice($colWidthsItems,0,3)) , $current_y_item);
    $pdf->Rect($pdf->GetX(), $current_y_item, $colWidthsItems[3], $rowHeight); $pdf->MultiCell($colWidthsItems[3], $line_height, number_format($boxes_val, 2), 0, 'R'); $pdf->SetXY($x_start_row + array_sum(array_slice($colWidthsItems,0,4)) , $current_y_item);
    $pdf->Rect($pdf->GetX(), $current_y_item, $colWidthsItems[4], $rowHeight); $pdf->MultiCell($colWidthsItems[4], $line_height, number_format($qty_sqm_val, 4), 0, 'R'); $pdf->SetXY($x_start_row + array_sum(array_slice($colWidthsItems,0,5)) , $current_y_item);
    $pdf->Rect($pdf->GetX(), $current_y_item, $colWidthsItems[5], $rowHeight); $pdf->MultiCell($colWidthsItems[5], $line_height, number_format($rate_val, 2), 0, 'R'); $pdf->SetXY($x_start_row + array_sum(array_slice($colWidthsItems,0,6)) , $current_y_item);
    $pdf->Rect($pdf->GetX(), $current_y_item, $colWidthsItems[6], $rowHeight); $pdf->MultiCell($colWidthsItems[6], $line_height, number_format($amount_val, 2), 0, 'R');
    $pdf->SetY($current_y_item + $rowHeight);
}
$min_rows = 5;
for ($i = $item_count; $i < $min_rows; $i++) {
    $pdf->SetX($pdf->GetLMargin());
    $pdf->Cell($colWidthsItems[0], 7, '', 1, 0); $pdf->Cell($colWidthsItems[1], 7, '', 1, 0); $pdf->Cell($colWidthsItems[2], 7, '', 1, 0);
    $pdf->Cell($colWidthsItems[3], 7, '', 1, 0); $pdf->Cell($colWidthsItems[4], 7, '', 1, 0); $pdf->Cell($colWidthsItems[5], 7, '', 1, 0);
    $pdf->Cell($colWidthsItems[6], 7, '', 1, 1);
}
$pdf->Ln(0);

$pdf->SetX($pdf->GetLMargin());
$totals_label_width1 = $colWidthsItems[0] + $colWidthsItems[1] + $colWidthsItems[2];
$totals_boxes_width = $colWidthsItems[3]; $totals_qty_width = $colWidthsItems[4];
$totals_rate_width = $colWidthsItems[5]; $totals_amount_width = $colWidthsItems[6];
$pdf->SetFont('Helvetica','B',9); $pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
$pdf->Cell($totals_label_width1, 7, 'SUB TOTAL', 1, 0, 'R', true);
$pdf->Cell($totals_boxes_width, 7, number_format($total_boxes_items,2), 1, 0, 'R', true);
$pdf->Cell($totals_qty_width, 7, number_format($total_sqm_items, 4), 1, 0, 'R', true);
$pdf->Cell($totals_rate_width, 7, '', 1, 0, 'C', true);
$pdf->Cell($totals_amount_width, 7, number_format($sub_total, 2), 1, 1, 'R', true);
$grand_total = $sub_total;
if (isset($pi_header['freight_amount']) && is_numeric($pi_header['freight_amount']) && (float)$pi_header['freight_amount'] != 0) {
    $pdf->SetX($pdf->GetLMargin());
    $pdf->Cell($totals_label_width1 + $totals_boxes_width + $totals_qty_width + $totals_rate_width, 7, 'FREIGHT (+)', 1, 0, 'R', true);
    $pdf->Cell($totals_amount_width, 7, number_format((float)$pi_header['freight_amount'], 2), 1, 1, 'R', true);
    $grand_total += (float)$pi_header['freight_amount'];
}
if (isset($pi_header['discount_amount']) && is_numeric($pi_header['discount_amount']) && (float)$pi_header['discount_amount'] != 0) {
    $pdf->SetX($pdf->GetLMargin());
    $pdf->Cell($totals_label_width1 + $totals_boxes_width + $totals_qty_width + $totals_rate_width, 7, 'DISCOUNT (-)', 1, 0, 'R', true);
    $pdf->Cell($totals_amount_width, 7, number_format((float)$pi_header['discount_amount'], 2), 1, 1, 'R', true);
    $grand_total -= (float)$pi_header['discount_amount'];
}
$pdf->SetX($pdf->GetLMargin());
$pdf->SetFont('Helvetica','B',10);
$pdf->Cell($totals_label_width1 + $totals_boxes_width + $totals_qty_width + $totals_rate_width, 7, 'GRAND TOTAL', 1, 0, 'R', true);
$pdf->Cell($totals_amount_width, 7, number_format($grand_total, 2), 1, 1, 'R', true);
$pdf->Ln(0);

$pdf->SetX($pdf->GetLMargin());
$currency_major = strtoupper($pi_header['currency_type'] ?? 'UNITS');
$currency_minor = 'CENTS';
if ($currency_major == 'INR') $currency_minor = 'PAISE';
$amount_in_words_text = numberToWords($grand_total, $currency_major, $currency_minor, true);
$pdf->SetFont('Helvetica','B',9); $pdf->SetLineWidth(0.2);
$pdf->Cell(55, 6, "Total Invoice amount in words: ", "LTB", 0, 'L');
$pdf->SetFont('Helvetica','',9);
$pdf->MultiCell($page_width - 55, 6, $amount_in_words_text, "TRB", 'L', false);
$pdf->SetLineWidth(0.2);
$pdf->Ln(0);

if(!empty($pi_header['note'])){
    $pdf->SetX($pdf->GetLMargin());
    $pdf->DrawSectionHeader('NOTE', $page_width, $section_title_height, $box_border, 'L');
    $pdf->SetFont('Helvetica','',8);
    $pdf->MultiCell($page_width, $line_height, $pi_header['note'], $box_border, 'L');
    $pdf->Ln(0);
}

if($bank_details_data){
    $pdf->SetX($pdf->GetLMargin());
    $pdf->DrawSectionHeader('BANK DETAILS FOR T.T. REMITTANCE', $page_width, $section_title_height, $box_border, 'L');
    $pdf->SetFont('Helvetica','',8);
    $bank_content_width = $page_width - (2*$padding);
    $pdf->SetX($pdf->GetLMargin() + $padding);
    $pdf->MultiCell($bank_content_width, $line_height, "BENEFICIARY NAME: " . ($bank_details_data['beneficiary_name'] ?? ''), $box_border == 1 ? 'LTR' : 0, 'L');
    $pdf->SetX($pdf->GetLMargin() + $padding);
    $pdf->MultiCell($bank_content_width, $line_height, "BANK NAME: " . ($bank_details_data['bank_name'] ?? ''), $box_border == 1 ? 'LR' : 0, 'L');
    $pdf->SetX($pdf->GetLMargin() + $padding);
    $pdf->MultiCell($bank_content_width, $line_height, "BANK ADDRESS: " . ($bank_details_data['bank_address'] ?? ''), $box_border == 1 ? 'LR' : 0, 'L');
    $pdf->SetX($pdf->GetLMargin() + $padding);
    $acc_no_text = "ACCOUNT NO: " . ($bank_details_data['account_number'] ?? '');
    $swift_text = !empty($bank_details_data['swift_code']) ? "SWIFT CODE: " . $bank_details_data['swift_code'] : '';
    $ifsc_text = !empty($bank_details_data['ifsc_code']) ? "IFSC CODE: " . $bank_details_data['ifsc_code'] : '';
    $col_bank_ac = $bank_content_width * 0.40;
    $col_bank_swift = $bank_content_width * 0.30;
    $col_bank_ifsc = $bank_content_width * 0.30;
    $pdf->Cell($col_bank_ac, $line_height, $acc_no_text, $box_border == 1 ? 'L' : 0, 0, 'L');
    $pdf->Cell($col_bank_swift, $line_height, $swift_text, 0, 0, 'L');
    $pdf->Cell($col_bank_ifsc, $line_height, $ifsc_text, $box_border == 1 ? 'R' : 0, 1, 'L');
    $pdf->SetX($pdf->GetLMargin() + $padding);
    $pdf->Cell($bank_content_width, 0.1, '', $box_border == 1 ? 'T' : 0, 1, 'L');
    $pdf->Ln(0);
}

$pdf->SetX($pdf->GetLMargin());
$pdf->DrawSectionHeader('DECLARATION', $page_width, $section_title_height, $box_border, 'L');
$pdf->SetFont('Helvetica','',8);
$declaration = "We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.";
$pdf->MultiCell($page_width, $line_height, $declaration, $box_border, 'L');
$pdf->Ln(10); // Space before Round Stamp

// --- Signature Block with Images and Text ---
$y_signature_area_start = $pdf->GetY();
$round_stamp_width = 30; $round_stamp_height_actual = 0;
$signature_img_width = 40; $signature_img_height_actual = 0;

// Round Stamp (Left)
$round_stamp_x = $pdf->GetLMargin() + $padding;
if (file_exists('Round_stamp.png')) {
    $img_size_stamp = getimagesize('Round_stamp.png');
    if ($img_size_stamp) $round_stamp_height_actual = ($img_size_stamp[1] / $img_size_stamp[0]) * $round_stamp_width;
    else $round_stamp_height_actual = $round_stamp_width;
    $pdf->Image('Round_stamp.png', $round_stamp_x, $y_signature_area_start, $round_stamp_width, $round_stamp_height_actual);
} else {
    $pdf->SetXY($round_stamp_x, $y_signature_area_start);
    $pdf->Cell($round_stamp_width, 10, '[No Stamp]', $box_border);
    $round_stamp_height_actual = 10;
}

// Right side: For Company Name, then Dhruv_sign.png, then AUTHORISED SIGNATURE
$right_block_x_start = $pdf->GetLMargin() + $page_width / 1.8; // Start further to the right
$right_block_width = $page_width - ($right_block_x_start - $pdf->GetLMargin());

// "For Company Name"
$pdf->SetXY($right_block_x_start, $y_signature_area_start);
$pdf->SetFont('Helvetica','B',10);
$pdf->Cell($right_block_width, $line_height, "For " . ($exporter_details_data['company_name'] ?? ''), 0, 1, 'R');

// Dhruv_sign.png (Signature Image)
$y_after_for_company = $pdf->GetY() + 1; // Small gap after "For..."
if (file_exists('Dhruv_sign.png')) {
    $img_size_sign = getimagesize('Dhruv_sign.png');
    if ($img_size_sign) $signature_img_height_actual = ($img_size_sign[1] / $img_size_sign[0]) * $signature_img_width;
    else $signature_img_height_actual = 15;

    $signature_img_x_aligned = $pdf->GetLMargin() + $page_width - $signature_img_width; // Right align image
    $pdf->Image('Dhruv_sign.png', $signature_img_x_aligned, $y_after_for_company, $signature_img_width, $signature_img_height_actual);
    $y_after_signature_image = $y_after_for_company + $signature_img_height_actual;
} else {
    $pdf->SetXY($right_block_x_start, $y_after_for_company);
    $pdf->Cell($right_block_width, 15, '[Signature Image]', 0, 1, 'R'); // Placeholder for image space
    $y_after_signature_image = $pdf->GetY();
}

// "AUTHORISED SIGNATURE"
$pdf->SetY($y_after_signature_image + 1); // Small gap after image
$pdf->SetX($right_block_x_start);
$pdf->Cell($right_block_width, $line_height, 'AUTHORISED SIGNATURE', 0, 1, 'R');

// Ensure page Y is below the taller of the round stamp or the entire signature block on the right
$final_y_signature_block = $pdf->GetY();
$pdf->SetY(max($y_signature_area_start + $round_stamp_height_actual, $final_y_signature_block));


$pdf_filename = 'PI_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $pi_header['invoice_number'] ?? 'XXXX') . '.pdf';
$pdf->Output('D', $pdf_filename);

?>
