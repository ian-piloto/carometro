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

    /**
     * Inicia uma nova sessão de aula para o professor.
     * Pré-popula a chamada com FALTA para todos os alunos da turma.
     */
    public function startSession(int $professorId, string $turma): array
    {
        // Verifica se já há uma aula ativa para este professor
        $check = $this->db->prepare(
            "SELECT id FROM class_sessions WHERE professor_id = ? AND status = 'ativa' LIMIT 1"
        );
        $check->bind_param("i", $professorId);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            return [
                'success' => false,
                'message' => 'Já existe uma aula ativa. Encerre-a antes de iniciar outra.',
                'session_id' => $existing['id'],
            ];
        }

        $today = date('Y-m-d');
        $horaInicio = date('H:i:s');

        $stmt = $this->db->prepare(
            "INSERT INTO class_sessions (professor_id, turma, data_aula, hora_inicio) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("isss", $professorId, $turma, $today, $horaInicio);
        $result = $stmt->execute();
        $sessionId = $this->db->insert_id;
        $stmt->close();

        if (!$result) {
            return ['success' => false, 'message' => 'Erro ao criar sessão de aula.'];
        }

        // Pré-popula chamada: todos os alunos da turma recebem FALTA
        $students = $this->db->prepare(
            "SELECT id FROM students WHERE turma = ? AND status = 'ativo'"
        );
        $students->bind_param("s", $turma);
        $students->execute();
        $rows = $students->get_result()->fetch_all(MYSQLI_ASSOC);
        $students->close();

        if (!empty($rows)) {
            $insert = $this->db->prepare(
                "INSERT INTO attendance (session_id, student_id, status) VALUES (?, ?, 'falta')"
            );
            foreach ($rows as $s) {
                $insert->bind_param("ii", $sessionId, $s['id']);
                $insert->execute();
            }
            $insert->close();
        }

        return [
            'success' => true,
            'session_id' => $sessionId,
            'message' => 'Aula iniciada! Chamada aberta com ' . count($rows) . ' aluno(s).',
        ];
    }

    /**
     * Busca a sessão de aula ativa do professor.
     */
    public function getActiveSession(int $professorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, turma, data_aula, hora_inicio
             FROM class_sessions
             WHERE professor_id = ? AND status = 'ativa'
             LIMIT 1"
        );
        $stmt->bind_param("i", $professorId);
        $stmt->execute();
        $session = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$session) {
            return ['success' => false, 'active' => false];
        }

        return ['success' => true, 'active' => true, 'session' => $session];
    }

    /**
     * Retorna a lista completa de chamada de uma sessão.
     */
    public function getAttendance(int $sessionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id AS student_id, s.nome, s.matricula,
                    a.status, a.horario_entrada
             FROM attendance a
             JOIN students s ON s.id = a.student_id
             WHERE a.session_id = ?
             ORDER BY s.nome ASC"
        );
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return ['success' => true, 'attendance' => $rows];
    }

    /**
     * Marca o aluno como PRESENTE na sessão ativa.
     */
    public function markPresent(int $sessionId, int $studentId): array
    {
        $hora = date('H:i:s');

        $stmt = $this->db->prepare(
            "UPDATE attendance
             SET status = 'presente', horario_entrada = ?
             WHERE session_id = ? AND student_id = ? AND status = 'falta'"
        );
        $stmt->bind_param("sii", $hora, $sessionId, $studentId);
        $stmt->execute();
        $affected = $this->db->affected_rows;
        $stmt->close();

        return [
            'success' => true,
            'updated' => $affected > 0,
            'message' => $affected > 0 ? 'Presença registrada!' : 'Aluno já marcado como presente.',
        ];
    }

    /**
     * Encerra a sessão de aula ativa.
     */
    public function closeSession(int $sessionId, int $professorId): array
    {
        $horaFim = date('H:i:s');

        $stmt = $this->db->prepare(
            "UPDATE class_sessions
             SET status = 'encerrada', hora_fim = ?
             WHERE id = ? AND professor_id = ? AND status = 'ativa'"
        );
        $stmt->bind_param("sii", $horaFim, $sessionId, $professorId);
        $stmt->execute();
        $affected = $this->db->affected_rows;
        $stmt->close();

        return [
            'success' => $affected > 0,
            'message' => $affected > 0 ? 'Aula encerrada com sucesso!' : 'Sessão não encontrada ou já encerrada.',
        ];
    }
}
