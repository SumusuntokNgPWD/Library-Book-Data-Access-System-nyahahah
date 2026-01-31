<?php
$dbError = false;

require_once "../config/db.php";

try {
    $conn = DbConnection::connect();
} catch (Exception $e) {
    $dbError = true;
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Distributed Library Search</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <h1>📚 Find Your Book</h1>

    <?php if ($dbError): ?>
        <div class="db-warning">
            ⚠ Database connection failed. Search is temporarily unavailable.
        </div>
    <?php endif; ?>

    <div class="search-box" onchange="toggleInputType()">
        <select id="type">
            <option>Title</option>
            <option>Author</option>
            <option>Genre</option>
            <option>Year<option>
        </select>

        <input type="text" id="query" placeholder="Enter search term">

        <button id="searchBtn" <?php if ($dbError) echo "disabled"; ?>>Search</button>
    </div>

    <div id="result"></div>
</div>

<!-- Separate container for comparison table below the main search container -->
<div class="container" id="comparisonContainer">
    <div id="comparison" class="comparison-card">
        <h3>📊 Linear vs Hybrid</h3>
        <table class="comparison-table">
            <thead>
                <tr>
                    <th>Algorithm</th>
                    <th>Algo Time (s)</th>
                    <th>Total Time (s)</th>
                    <th>Memory (bytes)</th>
                    <th>Results</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Linear</td>
                    <td>0.00000</td>
                    <td>0.00000</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
                <tr>
                    <td>Hybrid</td>
                    <td>0.00000</td>
                    <td>0.00000</td>
                    <td>0</td>
                    <td>0</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<div id="stats" class="stats-below">
    ⏱ Retrieval time: 0s | 💾 Memory used: 0 bytes
</div>
<!-- Modal for book details -->
<div id="bookModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modalTitle"></h2>
        <p><strong>Author:</strong> <span id="modalAuthor"></span></p>
        <p><strong>Genre:</strong> <span id="modalGenre"></span></p>
        <p><strong>Year Published:</strong> <span id="modalYear"></span></p>
        <p class="description">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.</p>
    </div>
    
</div>
<script src="js/app.js"></script>
</body>
</html>
