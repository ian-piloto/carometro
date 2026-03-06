<?php
require_once __DIR__ . '/config/autoload.php';

use App\Config\Database;

try {
    $db = (new Database())->getConnection();
    $res = $db->query("SELECT COUNT(*) as total FROM students");
    echo "COUNT: " . $res->fetch_assoc()['total'];
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
