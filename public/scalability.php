<?php
// Allow heavier in-memory processing for scalability benchmarks
@ini_set('memory_limit', '1024M');
@set_time_limit(120);

header('Content-Type: application/json');

require_once "../config/db.php";
require_once "../models/LibraryData.php";
require_once "../core/MasterNode.php";
require_once "../core/Utils.php";

try {
    $category = $_GET['type'] ?? '';
    $key = trim($_GET['query'] ?? '');

    $keyNorm = Utils::normalize($key);
    if (!$category || $keyNorm === '') {
        echo json_encode(['error' => 'invalid_params']);
        exit;
    }

    // Fixed scalability dataset sizes: 10k, 50k, 100k, 200k
    $sizes = [10000, 50000, 100000, 200000];

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
