<?php
require_once __DIR__ . "/LibraryBook.php";

class LibraryData {
    public function __construct(private PDO $conn) {}

    public function fetchAllBooks(): array {
        $stmt = $this->conn->query(
            "SELECT id, title, author, year, genre FROM books"
        );

        $books = [];
        while ($row = $stmt->fetch()) {
            $books[] = new LibraryBook(
                $row['id'],
                $row['title'],
                $row['author'],
                $row['year'],
                $row['genre']
            );
        }
        return $books;
    }
}
