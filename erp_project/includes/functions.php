<?php

/**
 * Generates the next sequential invoice number for Performa Invoices.
 * Format: HEM/PI/YY-YY/XXXX
 * Example: HEM/PI/24-25/0001
 *
 * @param mysqli $conn The database connection object.
 * @return string The next invoice number.
 */
function generate_invoice_number($conn) {
    // Determine current Indian financial year (April 1 to March 31)
    $current_month = (int)date('m');
    $current_year = (int)date('y'); // Two digit year

    if ($current_month >= 4) { // April to December
        $financial_year_start_yy = $current_year;
        $financial_year_end_yy = $current_year + 1;
    } else { // January to March
        $financial_year_start_yy = $current_year - 1;
        $financial_year_end_yy = $current_year;
    }
    $financial_year_str = sprintf("%02d-%02d", $financial_year_start_yy, $financial_year_end_yy);

    $prefix = "HEM/PI/" . $financial_year_str . "/";

    // Find the highest sequence number for the current financial year prefix
    $sql = "SELECT invoice_number FROM performa_invoices WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Handle prepare error - maybe log it or return a default/error indicator
        error_log("Prepare failed for generate_invoice_number: (" . $conn->errno . ") " . $conn->error);
        return $prefix . "ERROR"; // Or throw an exception
    }

    $like_prefix = $prefix . "%";
    $stmt->bind_param("s", $like_prefix);
    $stmt->execute();
    $result = $stmt->get_result();

    $next_sequence_number = 1;
    if ($result && $result->num_rows > 0) {
        $last_invoice_row = $result->fetch_assoc();
        $last_invoice_number_str = $last_invoice_row['invoice_number'];

        // Extract the sequence part
        $parts = explode('/', $last_invoice_number_str);
        $last_sequence_str = end($parts);
        if (is_numeric($last_sequence_str)) {
            $next_sequence_number = (int)$last_sequence_str + 1;
        }
        // else, if not numeric (e.g. first time or manual entry was weird), it defaults to 1
    }
    $stmt->close();

    return $prefix . sprintf("%04d", $next_sequence_number);
}

/**
 * Gets the current date in YYYY-MM-DD format.
 * @return string Current date.
 */
function get_current_date_for_input() {
    return date('Y-m-d');
}

// You can add more utility functions here as needed.

?>
