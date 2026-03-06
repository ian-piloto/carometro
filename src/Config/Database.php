<?php

namespace App\Config;

use mysqli;
use Exception;

/**
 * Conexão com o banco e criação automática das tabelas.
 */
class Database
{
    private string $host = "127.0.0.1";
    private int $port = 3308;
    private string $db_name = "sistema_presenca_sala";
    private string $username = "root";
    private string $password = "";

    public ?mysqli $conn = null;

    public function getConnection(): mysqli
    {
        $conn = new mysqli($this->host, $this->username, $this->password, "", $this->port);

        if ($conn->connect_error) {
            throw new Exception(
                "Falha ao conectar no MySQL (porta {$this->port}): " .
                $conn->connect_error
                );
        }

        $conn->set_charset("utf8mb4");

        $conn->query("CREATE DATABASE IF NOT EXISTS `{$this->db_name}`
                      DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        if (!$conn->select_db($this->db_name)) {
            throw new Exception("Não foi possível selecionar o banco '{$this->db_name}'.");
        }

        $this->createTables($conn);

        $this->conn = $conn;
        return $this->conn;
    }

    private function createTables(mysqli $conn): void
    {
        // 1. Cursos
        $conn->query("
            CREATE TABLE IF NOT EXISTS `courses` (
                `id`        INT AUTO_INCREMENT PRIMARY KEY,
                `nome`      VARCHAR(100) NOT NULL,
                `descricao` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 2. Professores
        $conn->query("
            CREATE TABLE IF NOT EXISTS `professors` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `nome`       VARCHAR(255) NOT NULL,
                `email`      VARCHAR(255) NOT NULL,
                `senha_hash` VARCHAR(255) NOT NULL,
                `course_id`  INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_email` (`email`),
                FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 3. Alunos
        $conn->query("
            CREATE TABLE IF NOT EXISTS `students` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `nome`            VARCHAR(255) NOT NULL,
                `matricula`       VARCHAR(50)  NOT NULL,
                `course_id`       INT NOT NULL,
                `face_descriptor` LONGTEXT NOT NULL,
                `status`          ENUM('ativo','inativo') DEFAULT 'ativo',
                `data_cadastro`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_matricula` (`matricula`),
                FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE RESTRICT,
                INDEX `idx_course` (`course_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 4. Sessões de aula
        $conn->query("
            CREATE TABLE IF NOT EXISTS `class_sessions` (
                `id`           INT AUTO_INCREMENT PRIMARY KEY,
                `professor_id` INT NOT NULL,
                `course_id`    INT NOT NULL,
                `data_aula`    DATE NOT NULL,
                `hora_inicio`  TIME NOT NULL,
                `hora_fim`     TIME NULL,
                `status`       ENUM('ativa','encerrada') DEFAULT 'ativa',
                `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`professor_id`) REFERENCES `professors`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`course_id`)    REFERENCES `courses`(`id`)    ON DELETE CASCADE,
                INDEX `idx_prof_status` (`professor_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 5. Chamada / Frequência
        $conn->query("
            CREATE TABLE IF NOT EXISTS `attendance` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `session_id`      INT NOT NULL,
                `student_id`      INT NOT NULL,
                `status`          ENUM('presente','falta') DEFAULT 'falta',
                `horario_entrada` TIME NULL,
                FOREIGN KEY (`session_id`) REFERENCES `class_sessions`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`student_id`) REFERENCES `students`(`id`)        ON DELETE CASCADE,
                UNIQUE KEY `uq_session_student` (`session_id`, `student_id`),
                INDEX `idx_session` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Dados iniciais: cursos e professores de exemplo
        $conn->query("INSERT IGNORE INTO `courses` (id, nome, descricao) VALUES
            (1, 'Informática',   'Técnico em Informática'),
            (2, 'Administração', 'Técnico em Administração'),
            (3, 'Mecânica',      'Técnico em Mecânica')
        ");

        // Senha padrão: "senha123"
        $hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $conn->query("INSERT IGNORE INTO `professors` (id, nome, email, senha_hash, course_id) VALUES
            (1, 'Prof. João Silva',    'joao@senai.br',  '$hash', 1),
            (2, 'Profa. Maria Santos', 'maria@senai.br', '$hash', 2),
            (3, 'Prof. Pedro Costa',   'pedro@senai.br', '$hash', 3)
        ");
    }
}
