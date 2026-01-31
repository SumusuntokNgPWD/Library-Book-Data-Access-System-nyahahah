<?php
header('Content-Type: application/json');

require_once "../config/db.php";
require_once "../models/LibraryData.php";
require_once "../core/MasterNode.php";
require_once "../core/Utils.php";

try {
    
    $totalStart = microtime(true);
    $startMemory = memory_get_peak_usage(true);

    // Optional CPU usage baseline (may not be available on all platforms)
    $ruStart = function_exists('getrusage') ? getrusage() : null;

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

    // Optional CPU usage measurement (user + system time in ms)
    $cpuMs = null;
    if ($ruStart !== null) {
        $ruEnd = getrusage();
        if (isset($ruStart["ru_utime.tv_sec"])) {
            $userStart = $ruStart["ru_utime.tv_sec"] * 1_000_000 + $ruStart["ru_utime.tv_usec"];
            $userEnd   = $ruEnd["ru_utime.tv_sec"]   * 1_000_000 + $ruEnd["ru_utime.tv_usec"];
            $sysStart  = $ruStart["ru_stime.tv_sec"] * 1_000_000 + $ruStart["ru_stime.tv_usec"];
            $sysEnd    = $ruEnd["ru_stime.tv_sec"]   * 1_000_000 + $ruEnd["ru_stime.tv_usec"];
            $cpuMs = (($userEnd - $userStart) + ($sysEnd - $sysStart)) / 1000.0;
        }
    }

    $memoryMb = $memoryUsed / (1024 * 1024);

    echo json_encode([
        'results' => array_values($results),
        'stats' => [
            'algorithm_time_seconds' => (float)$algoTime,
            'total_time_seconds'     => (float)$totalTime,
            'peak_memory_bytes'      => $memoryUsed
        ],
        'comparison' => $comparison,
        'resource_usage' => [
            [
                'metric' => 'Memory Usage (RAM)',
                'value'  => round($memoryMb, 2),
                'unit'   => 'MB',
                'tool'   => 'Windows Task Manager / System Monitor'
            ],
            [
                'metric' => 'CPU Time (user + system)',
                'value'  => $cpuMs !== null ? round($cpuMs, 2) : 'N/A',
                'unit'   => $cpuMs !== null ? 'ms' : '',
                'tool'   => 'Apache JMeter + Windows Task Manager / System Monitor'
            ],
        ]
    ]);

} catch (Exception $e) {
    error_log("search.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
