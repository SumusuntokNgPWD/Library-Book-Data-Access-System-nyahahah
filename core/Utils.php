<?php
class Utils {
    public static function normalize(string $s): string {
        // Lowercase, remove all non-alphanumeric characters except spaces
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9\s]/', '', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return $s;
    }
}

