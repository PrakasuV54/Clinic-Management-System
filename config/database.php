<?php
/**
 * Database Config loader supporting .env
 */

$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
$dbSubPath = 'database' . DIRECTORY_SEPARATOR . 'sqlite' . DIRECTORY_SEPARATOR . 'hospital_portal.db';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === 'DB_DATABASE') {
                $dbSubPath = str_replace('/', DIRECTORY_SEPARATOR, $value);
            }
        }
    }
}

define('DB_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . $dbSubPath);
