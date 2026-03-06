<?php

namespace App\Controllers;

use mysqli;

/**
 * Controller de autenticação de professores via sessão PHP.
 */
class AuthController
{
    /**
     * Lista de professores cadastrados manualmente (Login Local).
     * Siga o formato abaixo para adicionar novos professores.
     */
    private array $professoresLocais = [
        [
            'nome' => 'Professor Administrador',
            'email' => 'admin@escola.com',
            'senha' => 'admin123',
            'course_id' => 1,
            'course_nome' => 'Administração'
        ],
        // Adicione mais professores aqui
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

        if (empty($email) || empty($senha)) {
            return ['success' => false, 'message' => 'E-mail e senha são obrigatórios.'];
        }

        // 1. Verificar se é um login local (hardcoded)
        foreach ($this->professoresLocais as $prodLocal) {
            if ($prodLocal['email'] === $email && $prodLocal['senha'] === $senha) {
                $_SESSION['professor'] = [
                    'id' => 0, // ID 0 para logins locais
                    'nome' => $prodLocal['nome'],
                    'email' => $prodLocal['email'],
                    'course_id' => $prodLocal['course_id'],
                    'course_nome' => $prodLocal['course_nome'],
                ];

                return [
                    'success' => true,
                    'professor' => $_SESSION['professor'],
                    'message' => 'Login realizado com sucesso (Acesso Local)!'
                ];
            }
        }

        return ['success' => false, 'message' => 'E-mail ou senha incorretos.'];
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
