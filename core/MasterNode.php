<?php
require_once "WorkerNode.php";
require_once "Utils.php";

class MasterNode {
    private array $workers = [];
    private array $bookMap = [];

    private array $invertedIndex = [
        'title'  => [],
        'author' => [],
        'genre'  => []
    ];

    // For year search (sorted array)
    private array $yearSorted = [];

    // SINGLE constructor
    public function __construct(int $numWorkers) {
        for ($i = 0; $i < $numWorkers; $i++)
            $this->workers[] = new WorkerNode();
    }

    // PREPROCESS ONCE
    public function preprocess(array $books): void {
        foreach ($books as $book) {
            $this->bookMap[$book->id] = $book;

            $idx = crc32(Utils::normalize($book->title)) % count($this->workers);
            $this->workers[$idx]->loadBook($book);

            foreach (['title','author','genre'] as $field) {
                $key = Utils::normalize($book->$field);
                $this->invertedIndex[$field][$key][] = $book->id;
            }
        }

        // Preprocess workers (sort titles/authors/genres)
        foreach ($this->workers as $worker)
            $worker->preprocess();

        // Prepare global year-sorted array for binary search
        $this->yearSorted = $books;
        usort($this->yearSorted, fn($a, $b) => $a->year <=> $b->year);
    }

    // -------------------------------
    // SEARCH FUNCTIONS
    // -------------------------------

    public function searchTitle(string $title): array {
        $key = Utils::normalize($title);
        $results = [];

        foreach ($this->workers as $worker) {
            foreach ($worker->titleSorted as $book) {
                $normalizedTitle = Utils::normalize($book->title);
                if (str_contains($normalizedTitle, $key)) {
                    // Calculate similarity score
                    $score = 0;
                    if ($normalizedTitle === $key) {
                        $score = 3; // exact match
                    } elseif (str_starts_with($normalizedTitle, $key)) {
                        $score = 2; // starts with query
                    } else {
                        $score = 1; // contains query somewhere
                    }

                    $results[] = [
                        'book' => $book,
                        'score' => $score
                    ];
                }
            }
        }

        // Sort descending by score, then alphabetically
        usort($results, function($a, $b) {
            if ($b['score'] === $a['score']) {
                return strcmp($a['book']->title, $b['book']->title);
            }
            return $b['score'] - $a['score'];
        });

        return array_map(fn($r) => $r['book'], $results);
    }

    public function searchDistributed(string $field, string $value): array {
        $key = Utils::normalize($value);
        $results = [];

        foreach ($this->workers as $worker) {
            $sortedArray = match($field) {
                'author' => $worker->authorSorted,
                'genre'  => $worker->genreSorted,
                default  => []
            };

            foreach ($sortedArray as $book) {
                if (str_contains(Utils::normalize($book->$field), $key)) {
                    $results[$book->id] = $book;
                }
            }
        }

        return array_values($results);
    }

    // Hybrid text search: binary prefix range + linear contains fallback
    public function searchHybrid(string $field, string $value): array {
        $key = Utils::normalize($value);
        if ($key === '') return [];

        $scored = []; // id => ['book'=>Book,'score'=>int]

        foreach ($this->workers as $worker) {
            // Choose normalized sorted list for prefix-binary search
            $normList = match($field) {
                'title'  => $worker->titleNormSorted,
                'author' => $worker->authorNormSorted,
                'genre'  => $worker->genreNormSorted,
                default  => []
            };

            // 1) Binary search lower bound for prefix range
            $low = 0; $high = count($normList);
            while ($low < $high) {
                $mid = intdiv($low + $high, 2);
                if (strcmp($normList[$mid]['norm'], $key) < 0) $low = $mid + 1; else $high = $mid;
            }
            $idx = $low;
            // Walk forward collecting prefix matches
            while ($idx < count($normList)) {
                $norm = $normList[$idx]['norm'];
                if (strncmp($norm, $key, strlen($key)) !== 0) break;
                $book = $normList[$idx]['book'];
                $score = ($norm === $key) ? 3 : 2; // exact or starts-with
                $prev = $scored[$book->id] ?? null;
                if (!$prev || $score > $prev['score']) {
                    $scored[$book->id] = ['book'=>$book,'score'=>$score];
                }
                $idx++;
            }

            // 2) Linear fallback for substring contains not covered by prefix
            $linearList = match($field) {
                'title'  => $worker->titleSorted,
                'author' => $worker->authorSorted,
                'genre'  => $worker->genreSorted,
                default  => []
            };
            foreach ($linearList as $book) {
                $normVal = Utils::normalize($book->{$field});
                if (str_contains($normVal, $key)) {
                    // If not already with higher score, add as contains
                    $prev = $scored[$book->id] ?? null;
                    if (!$prev) {
                        $scored[$book->id] = ['book'=>$book,'score'=>1];
                    }
                }
            }
        }

        // Sort by score desc, then alphabetical title
        $arr = array_values($scored);
        usort($arr, function($a,$b){
            if ($b['score'] === $a['score']) return strcmp($a['book']->title, $b['book']->title);
            return $b['score'] - $a['score'];
        });
        return array_map(fn($r)=>$r['book'], $arr);
    }

    public function searchYear(int $year): array {
        $low = 0;
        $high = count($this->yearSorted) - 1;
        $results = [];

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $midYear = $this->yearSorted[$mid]->year;

            if ($midYear === $year) {
                // Collect all books with the same year around mid
                $i = $mid;
                while ($i >= 0 && $this->yearSorted[$i]->year === $year) {
                    $results[] = $this->yearSorted[$i];
                    $i--;
                }
                $i = $mid + 1;
                while ($i < count($this->yearSorted) && $this->yearSorted[$i]->year === $year) {
                    $results[] = $this->yearSorted[$i];
                    $i++;
                }
                break;
            } elseif ($midYear < $year) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $results;
    }
}
