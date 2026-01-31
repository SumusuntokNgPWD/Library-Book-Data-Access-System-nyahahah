<?php
class WorkerNode {
    public array $assignedBooks = [];

    public array $titleSorted = [];
    public array $authorSorted = [];
    public array $genreSorted = [];

    // Normalized, sorted lists for hybrid prefix-binary search
    public array $titleNormSorted = [];
    public array $authorNormSorted = [];
    public array $genreNormSorted = [];

    // 1️⃣ Load / assign books
    public function loadBook(LibraryBook $book): void {
        $this->assignedBooks[] = $book;
    }

    // 2️⃣ Preprocess once (SORT HERE)
    public function preprocess(): void {
        $this->titleSorted  = $this->assignedBooks;
        $this->authorSorted = $this->assignedBooks;
        $this->genreSorted  = $this->assignedBooks;

        usort($this->titleSorted, fn($a,$b)=>strcmp($a->title,$b->title));
        usort($this->authorSorted, fn($a,$b)=>strcmp($a->author,$b->author));
        usort($this->genreSorted, fn($a,$b)=>strcmp($a->genre,$b->genre));

        // Build normalized sorted arrays for hybrid prefix range search
        $this->titleNormSorted = array_map(function($b){
            return ['norm' => Utils::normalize($b->title), 'book' => $b];
        }, $this->assignedBooks);
        $this->authorNormSorted = array_map(function($b){
            return ['norm' => Utils::normalize($b->author), 'book' => $b];
        }, $this->assignedBooks);
        $this->genreNormSorted = array_map(function($b){
            return ['norm' => Utils::normalize($b->genre), 'book' => $b];
        }, $this->assignedBooks);

        usort($this->titleNormSorted, fn($a,$b)=>strcmp($a['norm'],$b['norm']));
        usort($this->authorNormSorted, fn($a,$b)=>strcmp($a['norm'],$b['norm']));
        usort($this->genreNormSorted, fn($a,$b)=>strcmp($a['norm'],$b['norm']));
    }

    // 3️⃣ Binary search (USED during search)
    public function binarySearch(string $field, string $key): ?LibraryBook {
        $list = match ($field) {
            'title'  => $this->titleSorted,
            'author' => $this->authorSorted,
            'genre'  => $this->genreSorted,
            default  => []
        };

        $key = strtolower($key);
        $low = 0;
        $high = count($list) - 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $val = strtolower($list[$mid]->{$field});

            if ($val === $key) return $list[$mid];
            ($val < $key) ? $low = $mid + 1 : $high = $mid - 1;
        }

        return null;
    }
}
