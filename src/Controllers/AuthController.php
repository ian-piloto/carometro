<?php

namespace App\Controllers;

/**
 * Controller de autenticação — apenas login local (sem banco de dados).
 *
 * Para adicionar professores, basta adicionar um item ao array $professores abaixo.
 * Campos obrigatórios: nome, email, senha, turma.
 */
class AuthController
{
    /**
     * Lista de professores cadastrados manualmente (Login Local).
     * Adicione novos professores aqui — sem banco de dados necessário.
     */
    private array $professores = [
        ['nome' => 'Professor Administrador', 'email' => 'admin@escola.com', 'senha' => 'admin123', 'turma' => 'Turma A'],
        ['nome' => 'Gideão dos Santos', 'email' => 'gideao@escola.com', 'senha' => 'gide123', 'turma' => 'Turma B'],
    ];

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Autentica o professor e inicia a sessão.
     */
    public function login(array $data): array
    {
        $email = trim($data['email'] ?? '');
        $senha = trim($data['senha'] ?? '');

        foreach ($this->professores as $p) {
            if ($p['email'] === $email && $p['senha'] === $senha) {
                return ['success' => true, 'professor' => $_SESSION['professor'] = ['id' => 0, 'nome' => $p['nome'], 'email' => $p['email'], 'turma' => $p['turma']]];
            }
        }
        return ['success' => false, 'message' => 'Credenciais inválidas.'];
    }

    /**
     * Encerra a sessão do professor.
     */
    public function logout(): array
    {
        $_SESSION = [];
        session_destroy();
        return ['success' => true, 'message' => 'Sessão encerrada.'];
    }

    /**
     * Retorna os dados do professor logado.
     */
    public function getSession(): array
    {
        if (!isset($_SESSION['professor'])) {
            return ['success' => false, 'logged_in' => false];
        }
        return ['success' => true, 'logged_in' => true, 'professor' => $_SESSION['professor']];
    }
}
