<?php

namespace App\Controllers;

use mysqli;

/**
 * Controller para gerenciar sessões de aula e chamada de presença.
 */
class ClassSessionController
{
    public function __construct(private mysqli $db)
    {
    }

    public function startSession(int $professorId, string $turma, string $nomeAula): array
    {
        $existing = $this->db->query("SELECT id FROM class_sessions WHERE professor_id = $professorId AND status = 'ativa' LIMIT 1")->fetch_assoc();
        if ($existing)
            return ['success' => false, 'message' => 'Aula já ativa.', 'session_id' => $existing['id']];

        $today = date('Y-m-d');
        $hora = date('H:i:s');
        $stmt = $this->db->prepare("INSERT INTO class_sessions (professor_id, turma, nome_aula, data_aula, hora_inicio) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $professorId, $turma, $nomeAula, $today, $hora);
        if (!$stmt->execute())
            return ['success' => false, 'message' => 'Erro ao criar aula.'];
        $sessionId = $this->db->insert_id;
        $stmt->close();

        $students = $this->db->query("SELECT id FROM students WHERE turma = '$turma' AND status = 'ativo'")->fetch_all(MYSQLI_ASSOC);
        if ($students) {
            $stmt = $this->db->prepare("INSERT INTO attendance (session_id, student_id, status) VALUES (?, ?, 'falta')");
            foreach ($students as $s) {
                $stmt->bind_param("ii", $sessionId, $s['id']);
                $stmt->execute();
            }
            $stmt->close();
        }

        return ['success' => true, 'session_id' => $sessionId, 'message' => 'Aula iniciada com ' . count($students) . ' alunos.'];
    }

    public function getActiveSession(int $professorId): array
    {
        $res = $this->db->query("SELECT * FROM class_sessions WHERE professor_id = $professorId AND status = 'ativa' LIMIT 1")->fetch_assoc();
        return ['success' => !!$res, 'active' => !!$res, 'session' => $res];
    }

    public function getHistory(int $professorId): array
    {
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM attendance a WHERE a.session_id = c.id) as total_alunos,
                (SELECT COUNT(*) FROM attendance a WHERE a.session_id = c.id AND a.status = 'presente') as presentes
                FROM class_sessions c WHERE c.professor_id = $professorId ORDER BY c.data_aula DESC, c.hora_inicio DESC";
        return ['success' => true, 'history' => $this->db->query($sql)->fetch_all(MYSQLI_ASSOC)];
    }

    public function getAttendance(int $sessionId): array
    {
        $sql = "SELECT s.id as student_id, s.nome, s.matricula, a.status, a.horario_entrada 
                FROM attendance a JOIN students s ON s.id = a.student_id 
                WHERE a.session_id = $sessionId ORDER BY s.nome ASC";
        return ['success' => true, 'attendance' => $this->db->query($sql)->fetch_all(MYSQLI_ASSOC)];
    }

    public function markPresent(int $sessionId, int $studentId): array
    {
        $hora = date('H:i:s');
        $this->db->query("UPDATE attendance SET status = 'presente', horario_entrada = '$hora' WHERE session_id = $sessionId AND student_id = $studentId AND status = 'falta'");
        $ok = $this->db->affected_rows > 0;
        return ['success' => true, 'updated' => $ok, 'message' => $ok ? 'Presente!' : 'Já marcado.'];
    }

    public function closeSession(int $sessionId, int $professorId): array
    {
        $hora = date('H:i:s');
        $this->db->query("UPDATE class_sessions SET status = 'encerrada', hora_fim = '$hora' WHERE id = $sessionId AND professor_id = $professorId AND status = 'ativa'");
        return ['success' => $this->db->affected_rows > 0, 'message' => 'Aula encerrada.'];
    }
}
