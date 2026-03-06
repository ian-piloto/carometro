<?php

namespace App\Config;

use mysqli;
use Exception;

/**
 * Conexão com o banco e criação automática das tabelas (Carômetro).
 * Login é local (AuthController) — sem tabelas de cursos/professores.
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
        $this->runMigrations($conn);

        $this->conn = $conn;
        return $this->conn;
    }

    private function createTables(mysqli $conn): void
    {
        // 1. Alunos — turma como string (sem FK para cursos)
        $conn->query("
            CREATE TABLE IF NOT EXISTS `students` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `nome`            VARCHAR(255) NOT NULL,
                `matricula`       VARCHAR(50)  NOT NULL,
                `turma`           VARCHAR(100) NOT NULL DEFAULT '',
                `face_descriptor` LONGTEXT NOT NULL,
                `status`          ENUM('ativo','inativo') DEFAULT 'ativo',
                `data_cadastro`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_matricula` (`matricula`),
                INDEX `idx_turma`  (`turma`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 2. Sessões de aula — turma como string (professor_id=0 para login local)
        $conn->query("
            CREATE TABLE IF NOT EXISTS `class_sessions` (
                `id`           INT AUTO_INCREMENT PRIMARY KEY,
                `professor_id` INT NOT NULL DEFAULT 0,
                `turma`        VARCHAR(100) NOT NULL DEFAULT '',
                `data_aula`    DATE NOT NULL,
                `hora_inicio`  TIME NOT NULL,
                `hora_fim`     TIME NULL,
                `status`       ENUM('ativa','encerrada') DEFAULT 'ativa',
                `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_prof_status` (`professor_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 3. Chamada / Frequência
        $conn->query("
            CREATE TABLE IF NOT EXISTS `attendance` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `session_id`      INT NOT NULL,
                `student_id`      INT NOT NULL,
                `status`          ENUM('presente','falta') DEFAULT 'falta',
                `horario_entrada` TIME NULL,
                FOREIGN KEY (`session_id`) REFERENCES `class_sessions`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`student_id`) REFERENCES `students`(`id`)       ON DELETE CASCADE,
                UNIQUE KEY `uq_session_student` (`session_id`, `student_id`),
                INDEX `idx_session` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * Migra tabelas existentes que ainda usam course_id para turma.
     * Seguro para chamar múltiplas vezes (verifica se já foi feito).
     */
    private function runMigrations(mysqli $conn): void
    {
        // Verifica se students ainda tem course_id
        $res = $conn->query("SHOW COLUMNS FROM `students` LIKE 'course_id'");
        if ($res && $res->num_rows > 0) {
            // Adiciona turma se não existir
            $hasTurma = $conn->query("SHOW COLUMNS FROM `students` LIKE 'turma'");
            if ($hasTurma && $hasTurma->num_rows === 0) {
                $conn->query("ALTER TABLE `students` ADD COLUMN `turma` VARCHAR(100) NOT NULL DEFAULT '' AFTER `matricula`");
                $conn->query("ALTER TABLE `students` ADD INDEX `idx_turma` (`turma`)");
            }
            // Tenta remover FKs antes de dropar a coluna
            $fkRes = $conn->query(
                "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students'
                 AND COLUMN_NAME = 'course_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            if ($fkRes) {
                while ($fk = $fkRes->fetch_assoc()) {
                    $conn->query("ALTER TABLE `students` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
                }
            }
            $conn->query("ALTER TABLE `students` DROP COLUMN `course_id`");
        }

        // Verifica se class_sessions ainda tem course_id
        $res2 = $conn->query("SHOW COLUMNS FROM `class_sessions` LIKE 'course_id'");
        if ($res2 && $res2->num_rows > 0) {
            // Adiciona turma se não existir
            $hasTurma2 = $conn->query("SHOW COLUMNS FROM `class_sessions` LIKE 'turma'");
            if ($hasTurma2 && $hasTurma2->num_rows === 0) {
                $conn->query("ALTER TABLE `class_sessions` ADD COLUMN `turma` VARCHAR(100) NOT NULL DEFAULT '' AFTER `professor_id`");
            }
            // Remove FKs de course_id
            $fkRes2 = $conn->query(
                "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_sessions'
                 AND COLUMN_NAME = 'course_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            if ($fkRes2) {
                while ($fk = $fkRes2->fetch_assoc()) {
                    $conn->query("ALTER TABLE `class_sessions` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
                }
            }
            // Remove FK de professor_id também (tabela professors não existe mais)
            $fkRes3 = $conn->query(
                "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_sessions'
                 AND COLUMN_NAME = 'professor_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            if ($fkRes3) {
                while ($fk = $fkRes3->fetch_assoc()) {
                    $conn->query("ALTER TABLE `class_sessions` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
                }
            }
            $conn->query("ALTER TABLE `class_sessions` DROP COLUMN `course_id`");
        }
    }
}
