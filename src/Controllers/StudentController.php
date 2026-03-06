<?php

namespace App\Controllers;

use mysqli;

/**
 * Controller de alunos — filtrado por turma do professor logado.
 */
class StudentController
{
    public function __construct(private mysqli $db)
    {
    }

    /**
     * Lista alunos de uma turma específica (isolamento por professor).
     */
    public function listByTurma(string $turma): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nome, matricula, turma, status, data_cadastro, face_descriptor
             FROM students
             WHERE turma = ? AND status = 'ativo'
             ORDER BY nome ASC"
        );
        $stmt->bind_param("s", $turma);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Cadastra novo aluno vinculado à turma.
     */
    public function register(array $data): array
    {
        $nome = trim($data['name'] ?? '');
        $matricula = trim($data['registration'] ?? '');
        $turma = trim($data['turma'] ?? '');
        $faceDescriptor = trim($data['face_descriptor'] ?? '');

        if (empty($nome) || empty($matricula) || empty($faceDescriptor) || empty($turma)) {
            return ['success' => false, 'message' => 'Campos obrigatórios ausentes.'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO students (nome, matricula, turma, face_descriptor)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $nome, $matricula, $turma, $faceDescriptor);
        $result = $stmt->execute();
        $stmt->close();

        return [
            'success' => $result,
            'message' => $result ? 'Aluno cadastrado com sucesso!' : 'Erro: matrícula já cadastrada.',
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
            'message' => $result ? 'Aluno excluído.' : 'Erro ao excluir aluno.',
        ];
    }
}
