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

/**
 * Converts a number to its word representation.
 * Example: 123.45 -> "One Hundred Twenty Three AND CENTS Forty Five ONLY" (for USD)
 * Supports up to billions.
 *
 * @param float $number The number to convert.
 * @param string $currency_major The name for the whole part of the currency (e.g., "DOLLARS", "RUPEES").
 * @param string $currency_minor The name for the decimal part of the currency (e.g., "CENTS", "PAISE").
 * @param bool $include_only_suffix Whether to append "ONLY" at the end.
 * @return string The number in words.
 */
function numberToWords($number, $currency_major = 'DOLLARS', $currency_minor = 'CENTS', $include_only_suffix = true) {
    $hyphen      = '-';
    $conjunction = ' AND ';
    $separator   = ', ';
    $negative    = 'NEGATIVE ';
    $decimal     = ' POINT '; // Not typically used for currency, decimal part handled separately
    $dictionary  = array(
        0                   => 'ZERO',
        1                   => 'ONE',
        2                   => 'TWO',
        3                   => 'THREE',
        4                   => 'FOUR',
        5                   => 'FIVE',
        6                   => 'SIX',
        7                   => 'SEVEN',
        8                   => 'EIGHT',
        9                   => 'NINE',
        10                  => 'TEN',
        11                  => 'ELEVEN',
        12                  => 'TWELVE',
        13                  => 'THIRTEEN',
        14                  => 'FOURTEEN',
        15                  => 'FIFTEEN',
        16                  => 'SIXTEEN',
        17                  => 'SEVENTEEN',
        18                  => 'EIGHTEEN',
        19                  => 'NINETEEN',
        20                  => 'TWENTY',
        30                  => 'THIRTY',
        40                  => 'FORTY',
        50                  => 'FIFTY',
        60                  => 'SIXTY',
        70                  => 'SEVENTY',
        80                  => 'EIGHTY',
        90                  => 'NINETY',
        100                 => 'HUNDRED',
        1000                => 'THOUSAND',
        1000000             => 'MILLION',
        1000000000          => 'BILLION',
        // For Indian system, you would add:
        // 100000              => 'LAKH',
        // 10000000            => 'CRORE'
    );

    $number = (float)$number;
    $string = '';

    if ($number < 0) {
        return $negative . numberToWords(abs($number), $currency_major, $currency_minor, $include_only_suffix);
    }

    $whole_part = floor($number);
    $fraction_part = round(($number - $whole_part) * 100); // Get cents/paise

    // Convert whole part
    if ($whole_part < 21) {
        $string = $dictionary[$whole_part] ?? '';
    } elseif ($whole_part < 100) {
        $tens   = ((int)($whole_part / 10)) * 10;
        $units  = $whole_part % 10;
        $string = $dictionary[$tens];
        if ($units) {
            $string .= $hyphen . $dictionary[$units];
        }
    } elseif ($whole_part < 1000) {
        $hundreds  = (int)($whole_part / 100);
        $remainder = $whole_part % 100;
        $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
        if ($remainder) {
            $string .= $conjunction . numberToWords($remainder, '', '', false); // No currency names for recursive part
        }
    } else { // Thousands, Millions, Billions
        $baseUnit = pow(1000, floor(log($whole_part, 1000)));
        $numBaseUnits = (int) ($whole_part / $baseUnit);
        $remainder = $whole_part % $baseUnit;
        $string = numberToWords($numBaseUnits, '', '', false) . ' ' . $dictionary[$baseUnit];
        if ($remainder) {
            $string .= ($remainder < 100 ? $conjunction : $separator) . numberToWords($remainder, '', '', false);
        }
    }

    $result_string = '';
    if (!empty($string)) {
        $result_string .= $string . ' ' . strtoupper($currency_major);
    }

    if ($fraction_part > 0) {
        if (!empty($result_string)) {
            $result_string .= $conjunction;
        }
        // Convert fraction part (cents/paise)
        if ($fraction_part < 21) {
            $result_string .= $dictionary[$fraction_part] ?? '';
        } else {
            $tens   = ((int)($fraction_part / 10)) * 10;
            $units  = $fraction_part % 10;
            $result_string .= $dictionary[$tens];
            if ($units) {
                $result_string .= $hyphen . $dictionary[$units];
            }
        }
        $result_string .= ' ' . strtoupper($currency_minor);
    }

    if ($include_only_suffix && !empty($result_string)) {
        $result_string .= ' ONLY';
    }

    return trim(strtoupper($result_string));
}


// You can add more utility functions here as needed.

?>
