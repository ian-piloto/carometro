<?php
require_once __DIR__ . '/src/Config/Database.php';

use App\Config\Database;

try {
    $database = new Database();
    // Temporariamente conecta sem selecionar o banco para criá-lo
    $conn = new mysqli("localhost", "root", "", "", 3306);

    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    echo "Criando banco de dados...\n";
    $conn->query("CREATE DATABASE IF NOT EXISTS library_vision CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db("library_vision");

    $sqlFile = __DIR__ . '/database_setup.sql';
    if (file_exists($sqlFile)) {
        echo "Executando $sqlFile...\n";
        $sql = file_get_contents($sqlFile);

        // Executa múltiplas queries
        if ($conn->multi_query($sql)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
            echo "Banco de dados inicializado com sucesso!\n";
        } else {
            echo "Erro ao executar SQL: " . $conn->error . "\n";
        }
    } else {
        echo "Arquivo SQL não encontrado: $sqlFile\n";
    }

    $conn->close();
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
