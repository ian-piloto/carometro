<?php
require_once __DIR__ . '/config/autoload.php';

use App\Config\Database;

try {
    $db = (new Database())->getConnection();
    echo "Columns in 'students':\n";
    $res = $db->query("DESCRIBE students");
    while ($row = $res->fetch_assoc()) {
        echo " - {$row['Field']} ({$row['Type']})\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
