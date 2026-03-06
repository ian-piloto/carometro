<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/autoload.php';

use App\Config\Database;

try {
    $db = (new Database())->getConnection();
    $result = $db->query("DESCRIBE students");
    if ($result) {
        echo "Columns in 'students' table:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Error: Could not DESCRIBE table students. " . $db->error . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
