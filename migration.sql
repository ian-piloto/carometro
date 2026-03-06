-- ============================================================
-- MIGRAÇÃO — de course_id (int) para turma (varchar)
-- Execute este script no phpMyAdmin ou via MySQL CLI
-- se já tiver o banco criado com a versão antiga.
-- ============================================================

USE sistema_presenca_sala;

-- 1. Adicionar coluna turma em students
ALTER TABLE students ADD COLUMN turma VARCHAR(100) NOT NULL DEFAULT '' AFTER matricula;

-- 2. Remover FK e coluna course_id de students
ALTER TABLE students DROP FOREIGN KEY students_ibfk_1;
ALTER TABLE students DROP COLUMN course_id;
ALTER TABLE students ADD INDEX idx_turma (turma);

-- 3. Adicionar coluna turma em class_sessions
ALTER TABLE class_sessions ADD COLUMN turma VARCHAR(100) NOT NULL DEFAULT '' AFTER professor_id;

-- 4. Remover FK e colunas antigas de class_sessions
ALTER TABLE class_sessions DROP FOREIGN KEY class_sessions_ibfk_1;
ALTER TABLE class_sessions DROP FOREIGN KEY class_sessions_ibfk_2;
ALTER TABLE class_sessions DROP COLUMN course_id;

-- 5. Remover tabelas que não são mais necessárias (opcional)
-- DROP TABLE IF EXISTS professors;
-- DROP TABLE IF EXISTS courses;
