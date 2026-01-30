<?php
class LibraryBook {
    public function __construct(
        public int $id,
        public string $title,
        public string $author,
        public int $year,
        public string $genre
    ) {}

    public function __toString(): string {
        return "{$this->id} | {$this->title} | {$this->author} | {$this->genre} | {$this->year}";
    }
}
