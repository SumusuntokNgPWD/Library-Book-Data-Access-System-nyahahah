// When search button is clicked
document.getElementById('searchBtn').addEventListener('click', search);

function toggleInputType() {
    const type = document.getElementById('type').value;
    const queryInput = document.getElementById('query');

    if (type === "Year") {
        queryInput.type = "number";
        queryInput.min = 1900;
        queryInput.max = 2026;
        queryInput.placeholder = "Enter year (1900-2026)";
    } else {
        queryInput.type = "text";
        queryInput.removeAttribute("min");
        queryInput.removeAttribute("max");
        queryInput.placeholder = "Enter search term";
    }
}

function escapeHTML(str) {
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}


function search() {
    const type = document.getElementById("type").value;
    const queryInput = document.getElementById("query"); // use same input for all categories
    const resultArea = document.getElementById("result");

    const query = queryInput.value.trim(); // always get value from #query

    if (!query) {
        resultArea.textContent = "⚠ Please enter a search value.";
        return;
    }

    resultArea.textContent = "Searching...";

const statsDiv = document.getElementById("stats");

fetch(`search.php?type=${encodeURIComponent(type)}&query=${encodeURIComponent(query)}`)
    .then(response => response.json())
    .then(data => {
        const books = data.results || [];
        const stats = data.stats || {};
        const comparison = data.comparison || null;

        // Display books (same as before)...
        let out = "";
        if (books.length === 0) {
            out = "⚠ Book not found.";
        } else {
            const regex = new RegExp(`(${escapeRegExp(query)})`, "gi");
            books.forEach(b => {
                let title = b.title, author = b.author, genre = b.genre || "N/A", year = b.year;

                switch(type) {
                    case "Title": title = title.replace(regex, '<mark>$1</mark>'); break;
                    case "Author": author = author.replace(regex, '<mark>$1</mark>'); break;
                    case "Genre": genre = genre.replace(regex, '<mark>$1</mark>'); break;
                    case "Year": year = year.toString().replace(regex, '<mark>$1</mark>'); break;
                }

                let teaser = "";
                if (type === "Title") teaser = `<h2>${title}</h2>`;
                else if (type === "Author") teaser = `<h2>${title}</h2><p><strong>Author:</strong> ${author}</p>`;
                else if (type === "Genre") teaser = `<h2>${title}</h2><p><strong>Genre:</strong> ${genre}</p>`;
                else if (type === "Year") teaser = `<h2>${title}</h2><p><strong>Year:</strong> ${year}</p>`;

                out += `
                        <div class="book-card"
                        onclick="showDetails(
                        '${b.id}',
                        '${escapeHTML(b.title)}',
                        '${escapeHTML(b.author)}',
                        '${escapeHTML(b.genre || "N/A")}',
                        '${b.year}'
                         )">
                        ${teaser}
                        </div>`;

            });
        }

        document.getElementById("result").innerHTML = out;

        const algoTime = stats.algorithm_time_seconds || 0;
        const totalTime = stats.total_time_seconds || 0;
        const memoryUsed = stats.peak_memory_bytes || 0;


        statsDiv.innerHTML = `⏱ Algorithm time: ${algoTime.toFixed(5)}s | ⏱ Total time: ${totalTime.toFixed(5)}s | 💾 Memory used: ${memoryUsed} bytes`;

        // Render Linear vs Hybrid comparison if available
        const cmpDiv = document.getElementById("comparison");
        if (comparison) {
            const lin = comparison.linear || {};
            const hyb = comparison.hybrid || {};
            const linAlgo = (lin.algorithm_time_seconds || 0).toFixed(5);
            const linTotal = (lin.total_time_seconds || 0).toFixed(5);
            const linMem = lin.peak_memory_bytes || 0;
            const linCount = lin.count || 0;

            const hyAlgo = (hyb.algorithm_time_seconds || 0).toFixed(5);
            const hyTotal = (hyb.total_time_seconds || 0).toFixed(5);
            const hyMem = hyb.peak_memory_bytes || 0;
            const hyCount = hyb.count || 0;

            cmpDiv.innerHTML = `
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
                            <td>${linAlgo}</td>
                            <td>${linTotal}</td>
                            <td>${linMem}</td>
                            <td>${linCount}</td>
                        </tr>
                        <tr>
                            <td>Hybrid</td>
                            <td>${hyAlgo}</td>
                            <td>${hyTotal}</td>
                            <td>${hyMem}</td>
                            <td>${hyCount}</td>
                        </tr>
                    </tbody>
                </table>
            `;
        } else {
            cmpDiv.innerHTML = `<h3>📊 Linear vs Hybrid</h3><p>Unavailable</p>`;
        }

    })
    .catch(() => {
        document.getElementById("result").textContent = "⚠ Search service unavailable.";
        statsDiv.innerHTML = `⏱ Retrieval time: 0s | 💾 Memory used: 0 bytes`;
    });
}

// Escape regex special characters
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Show book details in modal
function showDetails(id, title, author, genre, year) {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalAuthor').textContent = author;
    document.getElementById('modalGenre').textContent = genre;
    document.getElementById('modalYear').textContent = year;

    const modal = document.getElementById('bookModal');
    modal.style.display = "block";

    // Close modal with X
    modal.querySelector(".close").onclick = () => { modal.style.display = "none"; };

    // Close modal by clicking outside
    window.onclick = (event) => { if (event.target === modal) modal.style.display = "none"; };
}
