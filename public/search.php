<?php
header('Content-Type: application/json');

require_once "../config/db.php";
require_once "../models/LibraryData.php";
require_once "../core/MasterNode.php";
require_once "../core/Utils.php";

try {
    /* =======================
       TOTAL TIMER & MEMORY
       ======================= */
    $totalStart = microtime(true);
    $startMemory = memory_get_peak_usage(true);

    $category = $_GET['type'] ?? '';
    $key = trim($_GET['query'] ?? '');
    $keyNorm = Utils::normalize($key);

    if (!$category || $keyNorm === '') {
        echo json_encode([]);
        exit;
    }

    /* =======================
       DB + DATA FETCH
       ======================= */
    $conn = DbConnection::connect();
    $data = new LibraryData($conn);
    $books = $data->fetchAllBooks();

    $results = [];

    /* =======================
       PREPROCESS ONCE
       ======================= */
    static $master = null;
    if ($master === null && $category !== 'Year') {
        $master = new MasterNode(10);
        $master->preprocess($books);
    }

    /* =======================
       ALGORITHM TIMER START
       ======================= */
    $algoStart = microtime(true);

    // YEAR search (linear)
    if ($category === 'Year') {
        if (!is_numeric($key)) {
            echo json_encode([]);
            exit;
        }

        $year = (int)$key;
        $results = array_filter($books, fn($b) => $b->year == $year);

    } else {

        switch (strtolower($category)) {
            case 'title':
                $results = $master->searchTitle($key); // substring (linear)
                break;

            case 'author':
            case 'genre':
                $results = $master->searchDistributed(strtolower($category), $key);
                break;

            default:
                $results = [];
                break;
        }
    }

    /* =======================
       ALGORITHM TIMER END
       ======================= */
    $algoTime = microtime(true) - $algoStart;

    /* =======================
       TOTAL TIMER END
       ======================= */
    $totalTime = microtime(true) - $totalStart;
    $memoryUsed = memory_get_peak_usage(true) - $startMemory;

    /* =======================
       RESPONSE
       ======================= */
    echo json_encode([
        'results' => array_values($results),
        'stats' => [
            'algorithm_time_seconds' =>(float)$algoTime,
            'total_time_seconds'     =>(float)$totalTime,
            'peak_memory_bytes'      => $memoryUsed
        ]
    ]);

} catch (Exception $e) {
    error_log("search.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
