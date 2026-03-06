<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "--- DEEP DATABASE DIAGNOSTIC ---\n";

$tests = [
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3308, 'user' => 'root', 'pass' => ''],
];

foreach ($tests as $t) {
    echo "Testing {$t['host']}:{$t['port']} (user: {$t['user']})... ";
    try {
        $start = microtime(true);
        $conn = @new mysqli($t['host'], $t['user'], $t['pass'], '', $t['port']);
        $end = microtime(true);

        if ($conn->connect_error) {
            echo "FAIL: " . $conn->connect_error . " (Time: " . round($end - $start, 3) . "s)\n";
        } else {
            echo "SUCCESS!\n";
            echo " - MySQL Version: " . $conn->server_info . "\n";
            $res = $conn->query("SHOW DATABASES");
            $dbs = [];
            while ($row = $res->fetch_row()) $dbs[] = $row[0];
            echo " - Databases: " . implode(", ", $dbs) . "\n";

            if (in_array('library_vision', $dbs)) {
                echo " - 'library_vision' database FOUND.\n";
                $conn->select_db('library_vision');
                $resTables = $conn->query("SHOW TABLES");
                $tables = [];
                while ($row = $resTables->fetch_row()) $tables[] = $row[0];
                echo " - Tables: " . (empty($tables) ? "NONE" : implode(", ", $tables)) . "\n";
            } else {
                echo " - 'library_vision' database NOT FOUND.\n";
            }
            $conn->close();
        }
    } catch (Exception $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
    }
}
