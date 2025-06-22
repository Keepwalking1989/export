<?php
// Set content type to JSON
header('Content-Type: application/json');

require_once '../../includes/db_connect.php'; // Adjust path as necessary

$response = ['success' => false, 'data' => null, 'error' => ''];

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

$sql = "SELECT sqm_per_box, box_weight, purchase_price, price_per_sqm FROM sizes WHERE id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $size_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $size_details = $result->fetch_assoc();
            $response['success'] = true;
            $response['data'] = $size_details;
        } else {
            $response['error'] = 'Size details not found for the given ID.';
        }
    } else {
        $response['error'] = 'Failed to execute query: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['error'] = 'Failed to prepare statement: ' . $conn->error;
}

// $conn->close(); // Connection usually closed at script end

echo json_encode($response);
?>
