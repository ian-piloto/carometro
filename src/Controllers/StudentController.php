<?php

namespace App\Controllers;

use App\Models\Student;
use mysqli;

/**
 * Controller de alunos — filtrado por curso do professor logado.
 */
class StudentController
{
    private Student $studentModel;

    public function __construct(private mysqli $db)
    {
        $this->studentModel = new Student($db);
    }

    /**
     * Lista alunos de um curso específico (isolamento por professor).
     */
    public function listByCourse(int $courseId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nome, matricula, course_id, status, data_cadastro, face_descriptor
             FROM students
             WHERE course_id = ? AND status = 'ativo'
             ORDER BY nome ASC"
        );
        $stmt->bind_param("i", $courseId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Cadastra novo aluno vinculado ao curso.
     */
    public function register(array $data): array
    {
        $nome = trim($data['name'] ?? '');
        $matricula = trim($data['registration'] ?? '');
        $courseId = (int)($data['course_id'] ?? 0);
        $faceDescriptor = trim($data['face_descriptor'] ?? '');

        if (empty($nome) || empty($matricula) || empty($faceDescriptor) || $courseId === 0) {
            return ['success' => false, 'message' => 'Campos obrigatórios ausentes.'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO students (nome, matricula, course_id, face_descriptor)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssis", $nome, $matricula, $courseId, $faceDescriptor);
        $result = $stmt->execute();
        $stmt->close();

        return [
            'success' => $result,
            'message' => $result ? 'Aluno cadastrado com sucesso!' : 'Erro: matrícula já cadastrada.'
        ];
    }

    /**
     * Remove permanentemente um aluno.
     */
    public function delete(int $id): array
    {
        $stmt = $this->db->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        $stmt->close();

        return [
            'success' => $result,
            'message' => $result ? 'Aluno excluído.' : 'Erro ao excluir aluno.'
        ];
    }

    /**
     * Estatísticas para o dashboard (reutilizado do sistema anterior).
     */
    public function getDashboardStats(): array
    {
        $total = 0;
        $res = $this->db->query("SELECT COUNT(*) AS total FROM students WHERE status = 'ativo'");
        if ($res) {
            $total = (int)($res->fetch_assoc()['total'] ?? 0);
        }

        return [
            'total_alunos' => $total,
            'flow_data' => array_fill(0, 10, 0)
        ];
    }
}
