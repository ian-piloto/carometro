-- ============================================================
-- SISTEMA DE PRESENÇA FACIAL - Schema Completo
-- ============================================================
CREATE DATABASE IF NOT EXISTS sistema_presenca_sala
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_presenca_sala;

-- ------------------------------------------------------------
-- 1. CURSOS
-- Cada professor é vinculado a um curso. Alunos pertencem a um curso.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    descricao   VARCHAR(255) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. PROFESSORES
-- Login protegido por senha hashada (password_hash/PHP).
-- Cada professor administra um curso.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS professors (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    senha_hash  VARCHAR(255) NOT NULL,
    course_id   INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. ALUNOS
-- Vinculados a um curso. face_descriptor = JSON da face-api.js
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(255) NOT NULL,
    matricula       VARCHAR(50)  NOT NULL UNIQUE,
    course_id       INT NOT NULL,
    face_descriptor LONGTEXT NOT NULL,
    status          ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_cadastro   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
    INDEX idx_course (course_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. SESSÕES DE AULA
-- Gerada quando o professor clica em "Iniciar Aula".
-- É encerrada quando o professor clica em "Encerrar Aula".
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS class_sessions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL,
    course_id    INT NOT NULL,
    data_aula    DATE NOT NULL,
    hora_inicio  TIME NOT NULL,
    hora_fim     TIME NULL,
    status       ENUM('ativa', 'encerrada') DEFAULT 'ativa',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES professors(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)    REFERENCES courses(id)    ON DELETE CASCADE,
    INDEX idx_professor_status (professor_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. CHAMADA / FREQUÊNCIA
-- Criada com FALTA para todos ao iniciar aula.
-- Atualizada para PRESENTE quando o facial reconhece o aluno.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    session_id      INT NOT NULL,
    student_id      INT NOT NULL,
    status          ENUM('presente', 'falta') DEFAULT 'falta',
    horario_entrada TIME NULL,
    FOREIGN KEY (session_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id)        ON DELETE CASCADE,
    UNIQUE KEY uq_session_student (session_id, student_id),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DADOS INICIAIS DE EXEMPLO
-- Senha dos professores: "senha123" (altere em produção!)
-- ============================================================
INSERT IGNORE INTO courses (id, nome, descricao) VALUES
(1, 'Informática',   'Técnico em Informática'),
(2, 'Administração', 'Técnico em Administração'),
(3, 'Mecânica',      'Técnico em Mecânica');

-- Senha "senha123" hashada com password_hash($p, PASSWORD_BCRYPT)
INSERT IGNORE INTO professors (id, nome, email, senha_hash, course_id) VALUES
(1, 'Prof. João Silva',    'joao@senai.br',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(2, 'Profa. Maria Santos', 'maria@senai.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
(3, 'Prof. Pedro Costa',   'pedro@senai.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3);