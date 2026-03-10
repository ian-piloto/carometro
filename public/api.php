<?php

/**
 * Roteador Central da API — Carômetro
 * Rotas protegidas exigem sessão PHP válida, exceto /login e /logout.
 */

require_once __DIR__ . '/../config/config.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php'))
    require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/autoload.php';

use App\Config\Database;
use App\Controllers\{StudentController, AuthController, ClassSessionController};

// Inicia sessão antes de qualquer output
if (session_status() === PHP_SESSION_NONE)
    session_start();

header('Content-Type: application/json');

function res($data)
{
    echo json_encode($data);
    exit;
}

$action = $_GET['action'] ?? '';

// ── Rotas de autenticação (não precisam de DB) ────────────────────
$auth = new AuthController();

if ($action === 'login')
    res($auth->login(json_decode(file_get_contents('php://input'), true) ?? []));
if ($action === 'logout')
    res($auth->logout());
if ($action === 'get_session')
    res($auth->getSession());

// ── Guard: todas as demais rotas exigem autenticação ────────────
if (!isset($_SESSION['professor'])) {
    http_response_code(401);
    res(['success' => false, 'message' => 'Não autenticado.']);
}

$prof = $_SESSION['professor'];

// ── Rotas que precisam de DB ─────────────────────────────────────
try {
    $db = (new Database())->getConnection();

    $student = new StudentController($db);
    $session = new ClassSessionController($db);

    switch ($action) {

        // ── Alunos ─────────────────────────────────────────────
        case 'list_students':
            res($student->listByTurma($prof['turma']));
            break;

        case 'register_student':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            // Força a turma do professor logado (não aceita do cliente)
            $data['turma'] = $prof['turma'];
            res($student->register($data));
            break;

        case 'delete_student':
            res($student->delete((int)($_GET['id'] ?? 0)));
            break;

        // ── Sessão de Aula ─────────────────────────────────────
        case 'start_class':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($data['nome_aula']))
                res(['success' => false, 'message' => 'Nome obrigatório.']);
            res($session->startSession($prof['id'], $prof['turma'], $data['nome_aula']));
            break;

        case 'get_active_session':
            res($session->getActiveSession($prof['id']));
            break;

        case 'get_attendance':
            res($session->getAttendance((int)($_GET['session_id'] ?? 0)));
            break;

        case 'mark_present':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            res($session->markPresent((int)($data['session_id'] ?? 0), (int)($data['student_id'] ?? 0)));
            break;

        case 'end_class':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            res($session->closeSession((int)($data['session_id'] ?? 0), $prof['id']));
            break;

        case 'get_history':
            res($session->getHistory($prof['id']));
            break;

        default:
            http_response_code(400);
            res(['error' => 'Ação inválida.']);
            break;
    }
}
catch (Throwable $e) {
    http_response_code(500);
    res(['success' => false, 'message' => $e->getMessage()]);
}
