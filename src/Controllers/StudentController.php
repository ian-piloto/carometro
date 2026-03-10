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
        $stmt = $this->db->prepare("SELECT * FROM students WHERE turma = ? AND status = 'ativo' ORDER BY nome ASC");
        $stmt->bind_param("s", $turma);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $res;
    }

    /**
     * Cadastra novo aluno vinculado à turma.
     */
    public function register(array $data): array
    {
        $fields = ['name', 'registration', 'turma', 'face_descriptor'];
        foreach ($fields as $f)
            if (empty(trim($data[$f] ?? '')))
                return ['success' => false, 'message' => "Campo $f ausente."];

        $stmt = $this->db->prepare("INSERT INTO students (nome, matricula, turma, face_descriptor) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $data['name'], $data['registration'], $data['turma'], $data['face_descriptor']);
        $ok = $stmt->execute();
        $stmt->close();

        return ['success' => $ok, 'message' => $ok ? 'Aluno cadastrado!' : 'Erro: matrícula já existe.'];
    }

    /**
     * Remove permanentemente um aluno.
     */
    public function delete(int $id): array
    {
        $stmt = $this->db->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        return ['success' => $ok, 'message' => $ok ? 'Excluído.' : 'Erro ao excluir.'];
    }
}
