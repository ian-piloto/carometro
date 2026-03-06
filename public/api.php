<?php

/**
 * Roteador Central da API — sistema_presenca_sala
 * Todas as rotas protegidas exigem sessão PHP válida, exceto /login.
 */

require_once __DIR__ . '/../config/config.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/../config/autoload.php';

use App\Config\Database;
use App\Controllers\StudentController;
use App\Controllers\AuthController;
use App\Controllers\ClassSessionController;
use App\Controllers\ExportController;

// Inicia sessão antes de qualquer output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    $db = (new Database())->getConnection();

    $authController = new AuthController();
    $studentController = new StudentController($db);
    $sessionController = new ClassSessionController($db);
    $exportController = new ExportController($db);

    // ── Rota pública: login ──────────────────────────────────────
    if ($action === 'login') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        echo json_encode($authController->login($data));
        exit;
    }

    if ($action === 'logout') {
        echo json_encode($authController->logout());
        exit;
    }

    if ($action === 'get_session') {
        echo json_encode($authController->getSession());
        exit;
    }

    // ── Guard: todas as demais rotas exigem autenticação ────────
    if (!isset($_SESSION['professor'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
        exit;
    }

    $professor = $_SESSION['professor'];

    switch ($action) {

        // ── Alunos ─────────────────────────────────────────────
        case 'list_students':
            echo json_encode($studentController->listByCourse($professor['course_id']));
            break;

        case 'register_student':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            // Força o course_id do professor logado (não aceita do cliente)
            $data['course_id'] = $professor['course_id'];
            echo json_encode($studentController->register($data));
            break;

        case 'delete_student':
            $id = (int)($_GET['id'] ?? 0);
            echo json_encode($studentController->delete($id));
            break;

        case 'dashboard_stats':
            echo json_encode($studentController->getDashboardStats());
            break;

        // ── Sessão de Aula ─────────────────────────────────────
        case 'start_class':
            echo json_encode($sessionController->startSession(
                $professor['id'],
                $professor['course_id']
            ));
            break;

        case 'get_active_session':
            echo json_encode($sessionController->getActiveSession($professor['id']));
            break;

        case 'get_attendance':
            $sessionId = (int)($_GET['session_id'] ?? 0);
            echo json_encode($sessionController->getAttendance($sessionId));
            break;

        case 'mark_present':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $sessionId = (int)($data['session_id'] ?? 0);
            $studentId = (int)($data['student_id'] ?? 0);
            echo json_encode($sessionController->markPresent($sessionId, $studentId));
            break;

        case 'end_class':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $sessionId = (int)($data['session_id'] ?? 0);
            echo json_encode($sessionController->closeSession($sessionId, $professor['id']));
            break;

        case 'export_excel':
            $exportController->exportExcel();
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Ação inválida.']);
            break;
    }
}
catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
