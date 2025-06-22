<?php
header('Content-Type: application/json');
require_once '../../includes/db_connect.php';

$response = ['success' => false, 'products' => [], 'sqm_per_box' => null, 'error' => ''];

if (!isset($_GET['size_id']) || empty(trim($_GET['size_id']))) {
    $response['error'] = 'Size ID is missing.';
    echo json_encode($response);
    exit;
}

$size_id = trim($_GET['size_id']);

if (!filter_var($size_id, FILTER_VALIDATE_INT)) {
    $response['error'] = 'Invalid Size ID format.';
    echo json_encode($response);
    exit;
}

// First, get sqm_per_box for the selected size
$sql_size_info = "SELECT sqm_per_box FROM sizes WHERE id = ? LIMIT 1";
if ($stmt_size_info = $conn->prepare($sql_size_info)) {
    $stmt_size_info->bind_param("i", $size_id);
    if ($stmt_size_info->execute()) {
        $result_size_info = $stmt_size_info->get_result();
        if ($result_size_info->num_rows == 1) {
            $size_data = $result_size_info->fetch_assoc();
            $response['sqm_per_box'] = (float)$size_data['sqm_per_box'];
        } else {
            $response['error'] = 'Size details (sqm_per_box) not found for the given Size ID.';
            // Do not exit yet, try to fetch products anyway, or decide if this is critical
        }
    } else {
        $response['error'] = 'Failed to execute query for size details: ' . $stmt_size_info->error;
        // Do not exit, attempt to fetch products
    }
    $stmt_size_info->close();
} else {
    $response['error'] = 'Failed to prepare statement for size details: ' . $conn->error;
    // Do not exit, attempt to fetch products
}


// Fetch products associated with the given size_id
// Also fetch the effective price_per_sqm for each product
$sql_products = "SELECT
                    p.id,
                    p.design_name,
                    COALESCE(p.price_per_sqm_override, s.price_per_sqm) AS effective_price_per_sqm
                 FROM products p
                 JOIN sizes s ON p.size_id = s.id
                 WHERE p.size_id = ?
                 ORDER BY p.design_name ASC";

if ($stmt_products = $conn->prepare($sql_products)) {
    $stmt_products->bind_param("i", $size_id);
    if ($stmt_products->execute()) {
        $result_products = $stmt_products->get_result();
        if ($result_products->num_rows > 0) {
            while ($row = $result_products->fetch_assoc()) {
                // Ensure numeric fields are correctly typed for JSON
                $row['id'] = (int)$row['id'];
                $row['effective_price_per_sqm'] = $row['effective_price_per_sqm'] !== null ? (float)$row['effective_price_per_sqm'] : null;
                $response['products'][] = $row;
            }
            $response['success'] = true; // Set success to true if products are found
        } else {
            // If no products found for the size, it's not necessarily an error for the whole request
            // if sqm_per_box was found. The products array will just be empty.
            // If sqm_per_box was also not found, the error for that would take precedence.
            if(empty($response['error'])) { // only set this if no prior error
                 $response['success'] = true; // Still a successful response, just no products
                 // $response['message'] = 'No products found for this size.'; // Optional message
            }
        }
    } else {
        $response['error'] = (!empty($response['error']) ? $response['error'] . ' | ' : '') . 'Failed to execute query for products: ' . $stmt_products->error;
        $response['success'] = false;
    }
    $stmt_products->close();
} else {
    $response['error'] = (!empty($response['error']) ? $response['error'] . ' | ' : '') . 'Failed to prepare statement for products: ' . $conn->error;
    $response['success'] = false;
}

// $conn->close(); // Connection closed at script end
echo json_encode($response);
?>
