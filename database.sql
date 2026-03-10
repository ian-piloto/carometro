-- ============================================================
-- SISTEMA DE PRESENÇA FACIAL (Carômetro) - Schema Atualizado
-- Login local (sem tabela professors). Turma = string.
-- ============================================================
CREATE DATABASE IF NOT EXISTS sistema_presenca_sala
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_presenca_sala;

-- ------------------------------------------------------------
-- 1. ALUNOS
-- Vinculados a uma turma (string). face_descriptor = JSON face-api.js
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(255) NOT NULL,
    matricula       VARCHAR(50)  NOT NULL UNIQUE,
    turma           VARCHAR(100) NOT NULL,
    face_descriptor LONGTEXT NOT NULL,
    status          ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_cadastro   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_turma  (turma),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. SESSÕES DE AULA
-- Gerada quando o professor clica em "Iniciar Aula".
-- professor_id = 0 para professores locais (sem tabela professors).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS class_sessions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL DEFAULT 0,
    turma        VARCHAR(100) NOT NULL,
    nome_aula    VARCHAR(255) NOT NULL,
    data_aula    DATE NOT NULL,
    hora_inicio  TIME NOT NULL,
    hora_fim     TIME NULL,
    status       ENUM('ativa', 'encerrada') DEFAULT 'ativa',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_professor_status (professor_id, status),
    INDEX idx_data_aula (data_aula),
    INDEX idx_nome_aula (nome_aula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. CHAMADA / FREQUÊNCIA
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
    FOREIGN KEY (student_id) REFERENCES students(id)       ON DELETE CASCADE,
    UNIQUE KEY uq_session_student (session_id, student_id),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;