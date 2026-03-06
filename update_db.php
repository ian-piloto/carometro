<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/autoload.php';

use App\Config\Database;

$sql = "
SET FOREIGN_KEY_CHECKS=0;

-- Tabelas existentes com IF NOT EXISTS
CREATE TABLE IF NOT EXISTS students (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  class_name VARCHAR(255) NULL DEFAULT NULL,
  face_descriptor LONGTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (id),
  UNIQUE INDEX registration_number (class_name ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 4
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_logs (
  id INT(11) NOT NULL AUTO_INCREMENT,
  student_id INT(11) NOT NULL,
  type ENUM('entry', 'exit') NULL DEFAULT 'entry',
  access_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (id),
  INDEX student_id (student_id ASC),
  CONSTRAINT access_logs_ibfk_1
    FOREIGN KEY (student_id)
    REFERENCES students (id)
    ON DELETE CASCADE)
ENGINE = InnoDB
AUTO_INCREMENT = 603
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

-- Novas tabelas
CREATE TABLE IF NOT EXISTS attendance_logs (
  id INT(11) NOT NULL AUTO_INCREMENT,
  student_id INT(11) NOT NULL,
  data DATE NOT NULL,
  status ENUM('presente', 'falta') NULL DEFAULT 'falta',
  horario_registro TIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX student_id (student_id ASC),
  CONSTRAINT attendance_logs_ibfk_1
    FOREIGN KEY (student_id)
    REFERENCES students (id)
    ON DELETE CASCADE)
ENGINE = InnoDB
AUTO_INCREMENT = 4
DEFAULT CHARACTER SET = utf8mb4;

CREATE TABLE IF NOT EXISTS books (
  id INT(11) NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(255) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (id))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_classroom (
  id INT(11) NOT NULL AUTO_INCREMENT,
  student_id INT(11) NOT NULL,
  classe VARCHAR(100) NOT NULL,
  horario_entrada DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX fk_sala_estudante (student_id ASC),
  CONSTRAINT fk_sala_estudante
    FOREIGN KEY (student_id)
    REFERENCES students (id)
    ON DELETE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
";

try {
    $db = (new Database())->getConnection();

    // Executa múltiplas queries
    if ($db->multi_query($sql)) {
        do {
            // Limpa os resultados das queries
            if ($result = $db->store_result()) {
                $result->free();
            }
        } while ($db->more_results() && $db->next_result());

        echo "Banco de dados atualizado com sucesso!\n";

        // Verifica as tabelas criadas
        $result = $db->query("SHOW TABLES");
        echo "Tabelas no banco de dados:\n";
        while ($row = $result->fetch_array()) {
            echo "- " . $row[0] . "\n";
        }
    } else {
        echo "Erro ao executar queries: " . $db->error . "\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
