<?php
$configs = [
    ['host' => 'localhost', 'port' => 3306],
    ['host' => '127.0.0.1', 'port' => 3306],
    ['host' => 'localhost', 'port' => 3308],
    ['host' => '127.0.0.1', 'port' => 3308],
];

foreach ($configs as $config) {
    echo "Testing {$config['host']}:{$config['port']}... ";
    $conn = @new mysqli($config['host'], 'root', '', '', $config['port']);
    if ($conn->connect_error) {
        echo "FAIL: " . $conn->connect_error . "\n";
    } else {
        echo "SUCCESS!\n";
        $conn->close();
    }
}
