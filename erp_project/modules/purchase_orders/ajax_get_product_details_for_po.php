<?php
header('Content-Type: application/json');
require_once '../../includes/db_connect.php';

$response = ['success' => false, 'data' => null, 'error' => ''];

if (!isset($_GET['product_id']) || empty(trim($_GET['product_id'])) ||
    !isset($_GET['size_id']) || empty(trim($_GET['size_id']))) {
    $response['error'] = 'Product ID or Size ID is missing.';
    echo json_encode($response);
    exit;
}

$product_id = trim($_GET['product_id']);
$size_id = trim($_GET['size_id']);

if (!filter_var($product_id, FILTER_VALIDATE_INT) || !filter_var($size_id, FILTER_VALIDATE_INT)) {
    $response['error'] = 'Invalid Product ID or Size ID format.';
    echo json_encode($response);
    exit;
}

// Fetch effective box_weight for the product, considering its size context
// The product itself might have an override, otherwise, it comes from the size record.
$sql = "SELECT
            COALESCE(p.box_weight_override, s.box_weight) AS effective_box_weight,
            s.sqm_per_box -- Also send sqm_per_box of the size, might be useful for other calculations client-side
        FROM products p
        JOIN sizes s ON p.size_id = s.id
        WHERE p.id = ? AND p.size_id = ?";
        // Ensure product is actually linked to this size for consistency, though size_id on product row is the truth.
        // The join condition p.size_id = s.id and WHERE p.size_id = ? is somewhat redundant
        // if the product's size_id is already correctly set.
        // A simpler query if we trust products.size_id:
        // SELECT COALESCE(p.box_weight_override, s.box_weight) AS effective_box_weight, s.sqm_per_box
        // FROM products p JOIN sizes s ON p.size_id = s.id WHERE p.id = ? AND s.id = ?

// Let's use a query that directly uses the product's linked size_id and the provided size_id for the lookup.
// This ensures we get the specific size record's details.
$sql_final = "SELECT
                COALESCE(p.box_weight_override, s_lookup.box_weight) AS effective_box_weight,
                s_lookup.sqm_per_box
             FROM products p
             INNER JOIN sizes s_product_link ON p.size_id = s_product_link.id -- product's actual size link
             INNER JOIN sizes s_lookup ON s_lookup.id = ?                    -- the size context we are interested in
             WHERE p.id = ?";


if ($stmt = $conn->prepare($sql_final)) {
    // Parameters for $sql_final: first 's_lookup.id = ?' is $size_id, second 'p.id = ?' is $product_id
    $stmt->bind_param("ii", $size_id, $product_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $details = $result->fetch_assoc();
            $response['success'] = true;
            $response['data'] = [
                'weight_per_box' => $details['effective_box_weight'] !== null ? (float)$details['effective_box_weight'] : null,
                'sqm_per_box'    => $details['sqm_per_box'] !== null ? (float)$details['sqm_per_box'] : null
            ];
        } else {
            // This might happen if the product_id is not associated with the passed size_id,
            // or product/size not found.
            $response['error'] = 'Product or Size details not found, or product not matching given size.';
        }
    } else {
        $response['error'] = 'Failed to execute query: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['error'] = 'Failed to prepare statement: ' . $conn->error;
}

echo json_encode($response);
?>
