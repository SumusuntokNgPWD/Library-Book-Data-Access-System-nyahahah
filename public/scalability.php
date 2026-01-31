<?php
header('Content-Type: application/json');

require_once "../config/db.php";
require_once "../models/LibraryData.php";
require_once "../core/MasterNode.php";
require_once "../core/Utils.php";

try {
    $category = $_GET['type'] ?? '';
    $key = trim($_GET['query'] ?? '');
    $sizesParam = $_GET['sizes'] ?? '100,500,1000';

    $keyNorm = Utils::normalize($key);
    if (!$category || $keyNorm === '') {
        echo json_encode(['error' => 'invalid_params']);
        exit;
    }

    // Parse sizes
    $sizes = array_values(array_filter(array_map('intval', explode(',', $sizesParam)), function($n){ return $n > 0; }));
    if (empty($sizes)) $sizes = [100,500,1000];

    $conn = DbConnection::connect();
    $data = new LibraryData($conn);
    $books = $data->fetchAllBooks();

    // Use MasterNode static benchmarking
    $metrics = MasterNode::benchmarkScalability($books, $category, $key, $sizes);

    echo json_encode([
        'type' => $category,
        'query' => $key,
        'sizes' => $sizes,
        'metrics' => $metrics
    ]);

} catch (Exception $e) {
    error_log("scalability.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
