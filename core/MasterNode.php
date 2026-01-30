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
