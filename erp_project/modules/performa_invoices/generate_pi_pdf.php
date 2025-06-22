<?php
require_once '../../fpdf/fpdf.php'; // User confirmed path
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php'; // For numberToWords and other helpers

if (!isset($_GET['id']) || empty(trim($_GET['id'])) || !filter_var(trim($_GET['id']), FILTER_VALIDATE_INT)) {
    die("Invalid or missing Performa Invoice ID.");
}
$pi_id = (int)trim($_GET['id']);

// --- Data Fetching ---
$pi_header = null;
$exporter_details_data = null; // Renamed to avoid conflict with PDF class properties if any
$client_details_data = null;
$bank_details_data = null;
$pi_items = [];

// Fetch PI Header and linked Exporter, Client, Bank
$sql_header = "SELECT
                    pi.*,
                    e.company_name AS exporter_company_name, e.address AS exporter_address, e.city AS exporter_city,
                    e.state AS exporter_state, e.country AS exporter_country, e.pincode AS exporter_pincode,
                    e.iec_code AS exporter_iec_code, e.gst_number AS exporter_gst_number, /* Removed e.contact_number, e.email */
                    c.name AS client_company_name, c.address AS client_address, c.contact_person AS client_contact_person, c.phone AS client_phone,
                    b.bank_name, b.account_number, b.swift_code, b.ifsc_code, b.bank_address AS beneficiary_bank_address
                 FROM performa_invoices pi
                 JOIN exporters e ON pi.exporter_id = e.id
                 JOIN clients c ON pi.consignee_id = c.id
                 LEFT JOIN banks b ON pi.bank_id = b.id
                 WHERE pi.id = ?";

if ($stmt_header = $conn->prepare($sql_header)) {
    $stmt_header->bind_param("i", $pi_id);
    $stmt_header->execute();
    $result_header = $stmt_header->get_result();
    if ($result_header->num_rows == 1) {
        $pi_header = $result_header->fetch_assoc();
        $exporter_details_data = [
            'company_name' => $pi_header['exporter_company_name'],
            'address' => $pi_header['exporter_address'],
            'city' => $pi_header['exporter_city'],
            'state' => $pi_header['exporter_state'],
            'country' => $pi_header['exporter_country'],
            'pincode' => $pi_header['exporter_pincode'],
            'iec_code' => $pi_header['exporter_iec_code'],
            'gst_number' => $pi_header['exporter_gst_number']
            // phone & email removed as per request
        ];
        $client_details_data = [
            'company_name' => $pi_header['client_company_name'],
            'address' => $pi_header['client_address'],
            'contact_person' => $pi_header['client_contact_person'], // Added for potential use
            'phone' => $pi_header['client_phone'] // Added for potential use
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
        die("Performa Invoice not found.");
    }
    $stmt_header->close();
} else {
    die("Database error fetching PI header: " . $conn->error);
}

// Fetch PI Items (same as before)
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
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while($item_row = $result_items->fetch_assoc()){
        $pi_items[] = $item_row;
    }
    $stmt_items->close();
} else {
    die("Database error fetching PI items: " . $conn->error);
}
$conn->close();


// --- PDF Generation using FPDF ---
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Helvetica','B',18);
        $this->Cell(0,10,'PROFORMA INVOICE',0,1,'C');
        $this->Ln(5);
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
            $l += $cw[$c];
            if($l > $wmax) {
                if($sep == -1) { if($i == $j) $i++; } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }

    function DrawSectionHeader($title, $width, $height = 7, $border = 1, $align = 'C') {
        $this->SetFont('Helvetica','B',10);
        $this->SetFillColor(173,216,230); // Light blue
        $this->Cell($width, $height, strtoupper($title), $border, 1, $align, true); // Use $border, $align
        $this->SetFillColor(255,255,255); // Reset fill color
    }
}

$pdf = new PDF('P','mm','A4');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20); // Increased bottom margin for auto page break
$pdf->AddPage();

// --- PDF Styling Variables ---
$light_blue_R = 173; $light_blue_G = 216; $light_blue_B = 230;
$box_border = 1; // Use 1 for border, 0 for no border on sections like Notes, Bank, Declaration
$line_height = 5; // mm for text lines in MultiCells
$padding = 1; // mm for internal padding simulation in some areas
$section_title_height = 7; // mm for section title cells

// --- Exporter and Consignee Sections (New Table Layout) ---
// $light_blue_R, $light_blue_G, $light_blue_B are already defined above
// $line_height, $padding, $section_title_height are also defined above

$page_width = $pdf->GetPageWidth() - 20; // Usable width (A4 210mm - 2*10mm margin)
$col_width = ($page_width / 2) - ($padding * 2); // Approx half, adjust for gap later if needed

// Store current Y to align tops of multicells later
$y_start_boxes = $pdf->GetY();

// EXPORTER
$pdf->SetX(10);
$pdf->DrawSectionHeader('EXPORTER', $col_width, $section_title_height, 1, 'L');
$current_x_exporter = $pdf->GetX();
$current_y_exporter = $pdf->GetY();

$pdf->SetFont('Helvetica','B',11); // As per spec: "Company names in bold 12pt" FPDF pt vs mm
$pdf->MultiCell($col_width, $line_height, $exporter_details_data['company_name'] ?? '', 0, 'L');
$pdf->SetFont('Helvetica','',9);
$exporter_address_text = ($exporter_details_data['address'] ?? '') .
                         (!empty($exporter_details_data['city']) ? "\n".$exporter_details_data['city'] : '') .
                         (!empty($exporter_details_data['pincode']) ? " - ".$exporter_details_data['pincode'] : '') .
                         (!empty($exporter_details_data['state']) ? "\n".$exporter_details_data['state'] : '') .
                         (!empty($exporter_details_data['country']) ? "\n".$exporter_details_data['country'] : '');
$pdf->MultiCell($col_width, $line_height, $exporter_address_text, 0, 'L');
if(!empty($exporter_details_data['iec_code'])) $pdf->MultiCell($col_width, $line_height, "IEC Code: " . $exporter_details_data['iec_code'], 0, 'L');
if(!empty($exporter_details_data['gst_number'])) $pdf->MultiCell($col_width, $line_height, "GSTIN: " . $exporter_details_data['gst_number'], 0, 'L');
$y_after_exporter = $pdf->GetY();


// CONSIGNEE
$pdf->SetXY(10 + $col_width + $padding, $y_start_boxes); // Position next to exporter with a small gap
$pdf->DrawSectionHeader('CONSIGNEE', $col_width, $section_title_height, 1, 'L');
$current_x_consignee = $pdf->GetX();
$current_y_consignee = $pdf->GetY();

$pdf->SetFont('Helvetica','B',11);
$pdf->MultiCell($col_width, $line_height, $client_details_data['company_name'] ?? '', 0, 'L');
$pdf->SetFont('Helvetica','',9);
$pdf->MultiCell($col_width, $line_height, $client_details_data['address'] ?? '', 0, 'L');
// If you add separate city/state/zip to clients table, list them here
if(!empty($client_details_data['contact_person'])) $pdf->MultiCell($col_width, $line_height, "Attn: " . $client_details_data['contact_person'], 0, 'L');
if(!empty($client_details_data['phone'])) $pdf->MultiCell($col_width, $line_height, "Phone: " . $client_details_data['phone'], 0, 'L');
$y_after_consignee = $pdf->GetY();

// Set Y to below the taller of the two boxes
$pdf->SetY(max($y_after_exporter, $y_after_consignee) + 3); // Add a small gap after the boxes
$pdf->Ln(5);


// --- Invoice Details Table ---
$pdf->SetFont('Helvetica','B',9);
$pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
$cell_width_invoice_details = ($page_width / 2) - $padding; // Each cell takes half

$details_pairs = [
    "Proforma Invoice No." => $pi_header['invoice_number'] ?? 'N/A',
    "Date" => !empty($pi_header['invoice_date']) ? date("d-M-Y", strtotime($pi_header['invoice_date'])) : 'N/A',
    "Final Destination" => $pi_header['final_destination'] ?? 'N/A',
    // "Payment Terms" will be handled with MultiCell below due to potential length
    "Total Container(s)" => ($pi_header['total_container'] ?? 'N/A') . " x " . ($pi_header['container_size'] ?? 'N/A'), // Combined as per new field request
    "Currency" => "Currency: " . ($pi_header['currency_type'] ?? 'N/A'), // As per new field request
    "Total Gross Wt. (Kg)" => !empty($pi_header['total_gross_weight_kg']) ? number_format((float)$pi_header['total_gross_weight_kg'],2) : 'N/A'
];

$is_first_row_pair = true;
$keys = array_keys($details_pairs);
for ($i = 0; $i < count($keys); $i += 2) {
    $pdf->SetFont('Helvetica','B',9);
    $pdf->SetFillColor($is_first_row_pair ? $light_blue_R : 255, $is_first_row_pair ? $light_blue_G : 255, $is_first_row_pair ? $light_blue_B : 255);
    $pdf->Cell($cell_width_invoice_details / 2, 6, $keys[$i], 1, 0, 'L', true);
    $pdf->SetFont('Helvetica','',9);
    $pdf->SetFillColor(!$is_first_row_pair ? $light_blue_R : 255, !$is_first_row_pair ? $light_blue_G : 255, !$is_first_row_pair ? $light_blue_B : 255);
    $pdf->Cell($cell_width_invoice_details / 2, 6, $details_pairs[$keys[$i]], 1, 0, 'L', true);

    if (isset($keys[$i+1])) {
        $pdf->SetFont('Helvetica','B',9);
        $pdf->SetFillColor($is_first_row_pair ? $light_blue_R : 255, $is_first_row_pair ? $light_blue_G : 255, $is_first_row_pair ? $light_blue_B : 255);
        $pdf->Cell($cell_width_invoice_details / 2, 6, $keys[$i+1], 1, 0, 'L', true);
        $pdf->SetFont('Helvetica','',9);
        $pdf->SetFillColor(!$is_first_row_pair ? $light_blue_R : 255, !$is_first_row_pair ? $light_blue_G : 255, !$is_first_row_pair ? $light_blue_B : 255);
        $pdf->Cell($cell_width_invoice_details / 2, 6, $details_pairs[$keys[$i+1]], 1, 1, 'L', true);
    } else {
        $pdf->Cell($cell_width_invoice_details, 6, '', 1, 1);
    }
    $is_first_row_pair = !$is_first_row_pair;
}

// Payment Terms with MultiCell
$pdf->SetFont('Helvetica','B',9);
$pdf->SetFillColor($is_first_row_pair ? $light_blue_R : 255, $is_first_row_pair ? $light_blue_G : 255, $is_first_row_pair ? $light_blue_B : 255);
$pdf->Cell($cell_width_invoice_details / 2, 6, "Payment Terms", 1, 0, 'L', true);
$pdf->SetFont('Helvetica','',9);
$pdf->SetFillColor(!$is_first_row_pair ? $light_blue_R : 255, !$is_first_row_pair ? $light_blue_G : 255, !$is_first_row_pair ? $light_blue_B : 255);
$x_before_multicell = $pdf->GetX();
$y_before_multicell = $pdf->GetY();
$payment_terms_text = $pi_header['terms_delivery_payment'] ?? 'N/A';
// Calculate height for payment terms
$payment_terms_height = $pdf->NbLines($cell_width_invoice_details / 2 - 2*$padding, $payment_terms_text) * $line_height;
if ($payment_terms_height < 6) $payment_terms_height = 6; // Min height of a normal cell

$pdf->Rect($x_before_multicell, $y_before_multicell, $cell_width_invoice_details / 2, $payment_terms_height, 'DF'); // Draw cell and fill
$pdf->MultiCell($cell_width_invoice_details / 2, $line_height, $payment_terms_text, 0, 'L'); // Border set by Rect
$pdf->SetXY($x_before_multicell + $cell_width_invoice_details/2, $y_before_multicell); // Reset position for next part of row
$pdf->Cell($cell_width_invoice_details, $payment_terms_height, '', 1, 1); // Empty part of row, ensure border and newline, match height
$pdf->Ln(5);


// --- Notify Party Section (Optional) ---
if (!empty($pi_header['notify_party_line1']) || !empty($pi_header['notify_party_line2'])) {
    $pdf->DrawSectionHeader('NOTIFY PARTY', $page_width);
    $pdf->SetFont('Helvetica','',9);
    if(!empty($pi_header['notify_party_line1'])) $pdf->MultiCell($page_width - (2*$padding), $line_height, $pi_header['notify_party_line1'], $box_border, 'L');
    else $pdf->MultiCell($page_width - (2*$padding), $line_height, '', $box_border, 'L'); // Ensure border even if empty

    if(!empty($pi_header['notify_party_line2'])) $pdf->MultiCell($page_width - (2*$padding), $line_height, $pi_header['notify_party_line2'], $box_border, 'L');
    else $pdf->MultiCell($page_width - (2*$padding), $line_height, '', $box_border, 'L');
    $pdf->Ln(5);
}


// --- Product Items Table ---
// Column widths: 10mm (Sr), 75mm (Desc), 20mm (HSN), 25mm (Qty SQM), 25mm (Rate), 35mm (Amount) -> Total 190mm
$pdf->SetFont('Helvetica','B',9);
$pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
$colWidths = [10, 75, 20, 25, 25, 35];
$pdf->Cell($colWidths[0], 7, 'Sr.', 1, 0, 'C', true);
$pdf->Cell($colWidths[1], 7, 'Description of Goods', 1, 0, 'C', true);
$pdf->Cell($colWidths[2], 7, 'HSN Code', 1, 0, 'C', true);
$pdf->Cell($colWidths[3], 7, 'Qty (SQM)', 1, 0, 'C', true);
$pdf->Cell($colWidths[4], 7, 'Rate/' . ($pi_header['currency_type'] ?? 'SQM'), 1, 0, 'C', true);
$pdf->Cell($colWidths[5], 7, 'Amount (' . ($pi_header['currency_type'] ?? '') . ')', 1, 1, 'C', true);

$pdf->SetFont('Helvetica','',8);
$item_count = 0;
$sub_total = 0;
$total_sqm_items = 0; // Renamed to avoid conflict

foreach ($pi_items as $item) {
    $item_count++;
    $description = ($item['size_prefix'] ?? '') . " [" . ($item['size_text'] ?? '') . "] - " . ($item['design_name'] ?? '');
    if (!empty($item['product_type'])) {
        $description .= " (" . $item['product_type'] . ")";
    }
    if (!empty($item['boxes'])) {
         $description .= "\n(Boxes: " . number_format((float)$item['boxes'], 2) . ")";
    }

    $hsn = $item['item_hsn_code'] ?? 'N/A';
    $qty_sqm_val = !empty($item['quantity_sqm']) ? (float)$item['quantity_sqm'] : 0;
    $rate_val = !empty($item['rate_per_sqm']) ? (float)$item['rate_per_sqm'] : 0;
    $amount_val = !empty($item['amount']) ? (float)$item['amount'] : 0;

    $sub_total += $amount_val;
    $total_sqm_items += $qty_sqm_val;

    $numLinesDesc = $pdf->NbLines($colWidths[1] - 2, $description);
    $rowHeight = $numLinesDesc * $line_height;
    if ($rowHeight < 7) $rowHeight = 7;

     if ($pdf->GetY() + $rowHeight > ($pdf->GetPageHeight() - 35)) { // Margin for totals + footer
        $pdf->AddPage();
        $pdf->SetFont('Helvetica','B',9);
        $pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);
        $pdf->Cell($colWidths[0], 7, 'Sr.', 1, 0, 'C', true);
        $pdf->Cell($colWidths[1], 7, 'Description of Goods', 1, 0, 'C', true);
        $pdf->Cell($colWidths[2], 7, 'HSN Code', 1, 0, 'C', true);
        $pdf->Cell($colWidths[3], 7, 'Qty (SQM)', 1, 0, 'C', true);
        $pdf->Cell($colWidths[4], 7, 'Rate/' . ($pi_header['currency_type'] ?? 'SQM'), 1, 0, 'C', true);
        $pdf->Cell($colWidths[5], 7, 'Amount (' . ($pi_header['currency_type'] ?? '') . ')', 1, 1, 'C', true);
        $pdf->SetFont('Helvetica','',8);
    }

    $x_start_row = $pdf->GetX();
    $y_start_row = $pdf->GetY();

    $pdf->MultiCell($colWidths[0], $rowHeight, $item_count, 1, 'C', false, 0, $x_start_row, $y_start_row);
    $pdf->MultiCell($colWidths[1], $line_height, $description, 1, 'L', false, 0, $x_start_row + $colWidths[0], $y_start_row);
    $pdf->SetXY($x_start_row + $colWidths[0] + $colWidths[1], $y_start_row);
    $pdf->MultiCell($colWidths[2], $rowHeight, $hsn, 1, 'C', false, 0, $pdf->GetX(), $y_start_row);
    $pdf->MultiCell($colWidths[3], $rowHeight, number_format($qty_sqm_val, 4), 1, 'R', false, 0, $pdf->GetX(), $y_start_row);
    $pdf->MultiCell($colWidths[4], $rowHeight, number_format($rate_val, 2), 1, 'R', false, 0, $pdf->GetX(), $y_start_row);
    $pdf->MultiCell($colWidths[5], $rowHeight, number_format($amount_val, 2), 1, 'R', false, 1, $pdf->GetX(), $y_start_row); // Last cell, move to next line
    $pdf->SetY($y_start_row + $rowHeight); // Ensure Y is set correctly after all multicells in the row
}

$min_rows = 5;
for ($i = $item_count; $i < $min_rows; $i++) {
    $pdf->Cell($colWidths[0], 7, '', 1, 0); $pdf->Cell($colWidths[1], 7, '', 1, 0); $pdf->Cell($colWidths[2], 7, '', 1, 0);
    $pdf->Cell($colWidths[3], 7, '', 1, 0); $pdf->Cell($colWidths[4], 7, '', 1, 0); $pdf->Cell($colWidths[5], 7, '', 1, 1);
}
$pdf->Ln(1);

// --- Totals Section ---
$totals_label_width = $colWidths[0] + $colWidths[1] + $colWidths[2]; // Sr + Desc + HSN
$totals_qty_width = $colWidths[3];
$totals_rate_width = $colWidths[4];
$totals_amount_width = $colWidths[5];

$pdf->SetFont('Helvetica','B',9);
$pdf->SetFillColor($light_blue_R, $light_blue_G, $light_blue_B);

$pdf->Cell($totals_label_width, 7, 'SUB TOTAL', 1, 0, 'R', true);
$pdf->Cell($totals_qty_width, 7, number_format($total_sqm_items, 4), 1, 0, 'R', true);
$pdf->Cell($totals_rate_width, 7, '', 1, 0, 'C', true); // Empty cell under Rate
$pdf->Cell($totals_amount_width, 7, number_format($sub_total, 2), 1, 1, 'R', true);

$grand_total = $sub_total;
if (isset($pi_header['freight_amount']) && is_numeric($pi_header['freight_amount']) && (float)$pi_header['freight_amount'] != 0) {
    $pdf->Cell($totals_label_width + $totals_qty_width + $totals_rate_width, 7, 'FREIGHT (+)', 1, 0, 'R', true);
    $pdf->Cell($totals_amount_width, 7, number_format((float)$pi_header['freight_amount'], 2), 1, 1, 'R', true);
    $grand_total += (float)$pi_header['freight_amount'];
}
if (isset($pi_header['discount_amount']) && is_numeric($pi_header['discount_amount']) && (float)$pi_header['discount_amount'] != 0) {
    $pdf->Cell($totals_label_width + $totals_qty_width + $totals_rate_width, 7, 'DISCOUNT (-)', 1, 0, 'R', true);
    $pdf->Cell($totals_amount_width, 7, number_format((float)$pi_header['discount_amount'], 2), 1, 1, 'R', true);
    $grand_total -= (float)$pi_header['discount_amount'];
}

$pdf->SetFont('Helvetica','B',10);
$pdf->Cell($totals_label_width + $totals_qty_width + $totals_rate_width, 7, 'GRAND TOTAL', 1, 0, 'R', true);
$pdf->Cell($totals_amount_width, 7, number_format($grand_total, 2), 1, 1, 'R', true);
$pdf->Ln(2);

// Amount in Words Box
$currency_major = strtoupper($pi_header['currency_type'] ?? 'UNITS');
$currency_minor = 'CENTS'; // Default
if ($currency_major == 'INR') $currency_minor = 'PAISE';
if ($currency_major == 'USD') $currency_minor = 'CENTS';
if ($currency_major == 'EUR') $currency_minor = 'CENTS';

$amount_in_words_text = "Total Invoice amount in words: " . $currency_major . ' ' . numberToWords($grand_total, '', $currency_minor, true); // Pass empty for major/minor inside function if prefixed here
$pdf->SetFont('Helvetica','B',9);
$pdf->SetLineWidth(0.2); // Border for the box
$pdf->MultiCell($page_width, 6, $amount_in_words_text, 1, 'L', false); // Bordered MultiCell
$pdf->SetLineWidth(0.2); // Reset default line width if FPDF changes it
$pdf->Ln(5);


// --- Footer Sections ---
if(!empty($pi_header['terms_delivery_payment']) && $pi_header['terms_delivery_payment'] !== ($GLOBALS['terms_default'] ?? "30 % advance and 70% against BL ( against scan copy of BL)")){ // Show if not default
    $pdf->DrawSectionHeader('TERMS & CONDITIONS OF DELIVERY AND PAYMENT', $page_width);
    $pdf->SetFont('Helvetica','',8);
    $pdf->MultiCell($page_width - 2*$padding, $line_height, $pi_header['terms_delivery_payment'], $box_border, 'L');
    $pdf->Ln(3);
}

if(!empty($pi_header['note'])){
    $pdf->DrawSectionHeader('NOTE', $page_width);
    $pdf->SetFont('Helvetica','',8);
    $pdf->MultiCell($page_width - 2*$padding, $line_height, $pi_header['note'], $box_border, 'L');
    $pdf->Ln(3);
}

if($bank_details_data){
    $pdf->DrawSectionHeader('BANK DETAILS FOR T.T. REMITTANCE', $page_width);
    $pdf->SetFont('Helvetica','',8);
    $bank_str = "BENEFICIARY NAME: " . ($bank_details_data['beneficiary_name'] ?? '') . "\n";
    $bank_str .= "BANK NAME: " . ($bank_details_data['bank_name'] ?? '') . "\n";
    // $bank_str .= "BRANCH NAME: " . ($bank_details_data['branch_name'] ?? '') . "\n"; // Removed as per data mapping
    $bank_str .= "BANK ADDRESS: " . ($bank_details_data['bank_address'] ?? '') . "\n";
    $bank_str .= "ACCOUNT NO: " . ($bank_details_data['account_number'] ?? '') . "\n";
    if(!empty($bank_details_data['swift_code'])) $bank_str .= "SWIFT CODE: " . ($bank_details_data['swift_code'] ?? '') . "\n";
    if(!empty($bank_details_data['ifsc_code']))  $bank_str .= "IFSC CODE: " . ($bank_details_data['ifsc_code'] ?? '');
    $pdf->MultiCell($page_width - 2*$padding, $line_height, trim($bank_str), $box_border, 'L');
    $pdf->Ln(3);
}

$pdf->DrawSectionHeader('DECLARATION', $page_width);
$pdf->SetFont('Helvetica','',8);
$declaration = "We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.";
$pdf->MultiCell($page_width - 2*$padding, $line_height, $declaration, $box_border, 'L');
$pdf->Ln(10);

// Signature
$pdf->SetFont('Helvetica','B',10);
$pdf->Cell($page_width / 2, $line_height, "", 0, 0); // Empty cell for spacing
$pdf->Cell($page_width / 2, $line_height, "For " . ($exporter_details_data['company_name'] ?? ''), 0, 1, 'R');
$pdf->Ln(10); // Space for signature

$pdf->Cell($page_width / 2, $line_height, "Date: " . date("d-M-Y"), 0, 0, 'L'); // Signature Date
$pdf->Cell($page_width / 2, $line_height, 'AUTHORISED SIGNATURE', 0, 1, 'R');

$pdf_filename = 'PI_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $pi_header['invoice_number'] ?? 'XXXX') . '.pdf';
$pdf->Output('D', $pdf_filename);

?>
