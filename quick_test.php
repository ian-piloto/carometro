<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = "127.0.0.1";
$port = 3308;
$user = "root";
$pass = "";
$db = "library_vision";

echo "--- Teste Rápido de Porta 3308 ---\n";

// Testar se a porta está aberta antes de tentar o mysqli
$connection = @fsockopen($host, $port, $errno, $errstr, 2);

if (!$connection) {
    echo "❌ PORTA FECHADA: A porta $port no host $host não está aceitando conexões.\n";
    echo "Erro ($errno): $errstr\n";
    echo "\nVERIFIQUE O XAMPP:\n";
    echo "1. O MySQL está iniciado?\n";
    echo "2. A porta listada no painel é REALMENTE 3308?\n";
    exit;
} else {
    fclose($connection);
    echo "✅ PORTA ABERTA: A porta $port está respondendo.\n";
}

echo "Tentando conexão MySQLi...\n";
mysqli_report(MYSQLI_REPORT_OFF);
$link = @new mysqli($host, $user, $pass, $db, $port);

if ($link->connect_error) {
    echo "❌ ERRO MYSQLI: " . $link->connect_error . "\n";
} else {
    echo "✅ CONEXÃO MYSQLI SUCESSO!\n";
    $res = $link->query("SHOW TABLES");
    if ($res) {
        $tables = [];
        while ($row = $res->fetch_array()) $tables[] = $row[0];
        echo "Tabelas: " . implode(", ", $tables) . "\n";
    }
    $link->close();
}
