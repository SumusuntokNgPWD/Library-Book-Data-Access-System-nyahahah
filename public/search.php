<?php
header('Content-Type: application/json');

require_once "../config/db.php";
require_once "../models/LibraryData.php";
require_once "../core/MasterNode.php";
require_once "../core/Utils.php";

try {
    
    $totalStart = microtime(true);
    $startMemory = memory_get_peak_usage(true);

    $category = $_GET['type'] ?? '';
    $key = trim($_GET['query'] ?? '');
    $keyNorm = Utils::normalize($key);

    if (!$category || $keyNorm === '') {
        echo json_encode([]);
        exit;
    }

    
    $conn = DbConnection::connect();
    $data = new LibraryData($conn);
    $books = $data->fetchAllBooks();

    $results = [];

    
    static $master = null;
    if ($master === null && $category !== 'Year') {
        $master = new MasterNode(10);
        $master->preprocess($books);
    }

    
    $algoStart = microtime(true);

    if ($category === 'Year') {
    if (!is_numeric($key)) {
        echo json_encode([]);
        exit;
    }

    $year = (int)$key;

    // Use master node binary search
    if ($master === null) {
        $master = new MasterNode(10);
        $master->preprocess($books);
    }

    $results = $master->searchYear($year);
      }
      else {

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

    
      // ALGORITHM TIMER END
      
    $algoTime = microtime(true) - $algoStart;

    //TOTAL TIMER END
    $totalTime = microtime(true) - $totalStart;
    $memoryUsed = memory_get_peak_usage(true) - $startMemory;

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
