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
    if ($master === null) {
        $master = new MasterNode(10);
        $master->preprocess($books);
    }

    
    $algoStart = microtime(true);

    $comparison = [
        'linear' => [ 'algorithm_time_seconds' => 0.0, 'total_time_seconds' => 0.0, 'peak_memory_bytes' => 0, 'count' => 0 ],
        'hybrid' => [ 'algorithm_time_seconds' => 0.0, 'total_time_seconds' => 0.0, 'peak_memory_bytes' => 0, 'count' => 0 ],
    ];

    if ($category === 'Year') {
        if (!is_numeric($key)) {
            echo json_encode([]);
            exit;
        }
        $year = (int)$key;

        // Hybrid: binary search + expansion
        $hyStart = microtime(true);
        $resultsHybrid = $master->searchYear($year);
        $hyAlgo = microtime(true) - $hyStart;
        $comparison['hybrid']['algorithm_time_seconds'] = (float)$hyAlgo;
        $comparison['hybrid']['total_time_seconds'] = (float)(microtime(true) - $totalStart);
        $comparison['hybrid']['peak_memory_bytes'] = memory_get_peak_usage(true) - $startMemory;
        $comparison['hybrid']['count'] = count($resultsHybrid);

        // Linear baseline: scan all books
        $linStart = microtime(true);
        $resultsLinear = array_values(array_filter($books, fn($b)=>$b->year === $year));
        $linAlgo = microtime(true) - $linStart;
        $comparison['linear']['algorithm_time_seconds'] = (float)$linAlgo;
        $comparison['linear']['total_time_seconds'] = (float)(microtime(true) - $totalStart);
        $comparison['linear']['peak_memory_bytes'] = memory_get_peak_usage(true) - $startMemory;
        $comparison['linear']['count'] = count($resultsLinear);

        // Default results to hybrid
        $results = $resultsHybrid;
        $algoTime = $hyAlgo;
    } else {
        $field = strtolower($category);

        // Hybrid for text: prefix-binary + linear contains
        $hyStart = microtime(true);
        $resultsHybrid = $master->searchHybrid($field, $key);
        $hyAlgo = microtime(true) - $hyStart;
        $comparison['hybrid']['algorithm_time_seconds'] = (float)$hyAlgo;
        $comparison['hybrid']['total_time_seconds'] = (float)(microtime(true) - $totalStart);
        $comparison['hybrid']['peak_memory_bytes'] = memory_get_peak_usage(true) - $startMemory;
        $comparison['hybrid']['count'] = count($resultsHybrid);

        // Linear baseline according to field
        $linStart = microtime(true);
        switch ($field) {
            case 'title':
                $resultsLinear = $master->searchTitle($key);
                break;
            case 'author':
            case 'genre':
                $resultsLinear = $master->searchDistributed($field, $key);
                break;
            default:
                $resultsLinear = [];
        }
        $linAlgo = microtime(true) - $linStart;
        $comparison['linear']['algorithm_time_seconds'] = (float)$linAlgo;
        $comparison['linear']['total_time_seconds'] = (float)(microtime(true) - $totalStart);
        $comparison['linear']['peak_memory_bytes'] = memory_get_peak_usage(true) - $startMemory;
        $comparison['linear']['count'] = count($resultsLinear);

        // Default results to hybrid
        $results = $resultsHybrid;
        $algoTime = $hyAlgo;
    }

    
      // ALGORITHM TIMER END
      
    $algoTime = microtime(true) - $algoStart;

    //TOTAL TIMER END
    $totalTime = microtime(true) - $totalStart;
    $memoryUsed = memory_get_peak_usage(true) - $startMemory;

    echo json_encode([
        'results' => array_values($results),
        'stats' => [
            'algorithm_time_seconds' => (float)$algoTime,
            'total_time_seconds'     => (float)$totalTime,
            'peak_memory_bytes'      => $memoryUsed
        ],
        'comparison' => $comparison
    ]);

} catch (Exception $e) {
    error_log("search.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
