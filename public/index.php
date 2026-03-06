<?php

/**
 * Página Principal do Carômetro (Área do Professor).
 */
require_once __DIR__ . '/../config/config.php';

// Redireciona para o login se não estiver logado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['professor'])) {
    header("Location: login.php");
    exit;
}

header("Cache-Control: no-cache, must-revalidate");
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carômetro - Área do Professor</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <style>
        .camera-box { width: 100%; height: 240px; background: #000; position: relative; border-radius: 8px; overflow: hidden; margin-bottom: 1rem; }
        .camera-box video { width: 100%; height: 100%; object-fit: cover; }
        .camera-box canvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        #chamada-table-wrap { margin-top: 1rem; }
        .attendance-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .attendance-badge.presente { background-color: #d4edda; color: #155724; }
        .attendance-badge.falta { background-color: #f8d7da; color: #721c24; }
        .top-bar-actions { display: flex; gap: 10px; align-items: center; }
    </style>
</head>

<body>

    <!-- Toolbar Lateral de Navegação -->
    <nav>
        <div class="logo">Carômetro</div>
        <div style="padding: 0 20px; color: #888; font-size: 0.9rem; margin-bottom: 1rem;">
            <?= htmlspecialchars($_SESSION["professor"]["nome"]) ?>
        </div>
        <ul class="nav-links">
            <li class="active" onclick="showSection('chamada')" title="Chamada da Aula">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                    <polyline points="10 9 9 9 8 9" />
                </svg>
                Chamada da Aula
                <span id="nav-chamada-badge" class="badge hidden" style="background:var(--primary);color:#fff;padding:2px 6px;border-radius:10px;font-size:0.7rem;margin-left:auto;">0/0</span>
            </li>
            <li onclick="showSection('students')" title="Gerenciar Alunos">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                Alunos
            </li>
        </ul>
        <div style="margin-top:auto; padding: 20px;">
            <button onclick="doLogout()" class="btn-secondary" style="width:100%">Sair</button>
        </div>
    </nav>

    <main>
        <header>
            <h1 id="page-title">Chamada da Aula</h1>
            <div class="top-bar-actions">
                <button id="btn-start-class" class="btn-primary" onclick="toggleClass()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>
                    </svg>
                    Iniciar Aula
                </button>
                <a href="aluno.php" target="_blank" class="btn-secondary" title="Abrir interface do Tablet">
                    Abrir Tablet
                </a>
                <div id="clock" style="font-weight: bold; font-size: 1.2rem; margin-left:15px;">00:00:00</div>
            </div>
        </header>

        <!-- Seção 1: Chamada da Aula -->
        <section id="sec-chamada">
            <div id="active-class-card" class="card" style="display:none; margin-bottom: 1rem; background-color: #f8f9fa; border-left: 4px solid var(--primary);">
                <div style="display:flex; justify-content: space-between; align-items:center;">
                    <div>
                        <h3 style="margin:0; color: #333;">Aula em Andamento</h3>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9rem;" id="chamada-subtitle">Aguardando alunos...</p>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size: 0.8rem; color: #888; text-transform:uppercase;">Tempo Decorrido</span>
                        <div id="class-timer" style="font-size: 1.5rem; font-weight:bold; color:var(--primary);">00:00</div>
                    </div>
                </div>
            </div>

            <div class="card" id="chamada-table-wrap" class="hidden">
                <table style="width:100%">
                    <thead>
                        <tr>
                            <th style="text-align:left">Nome do Aluno</th>
                            <th style="text-align:left">Matrícula</th>
                            <th style="text-align:center">Registro</th>
                            <th style="text-align:center">Hora de Chegada</th>
                        </tr>
                    </thead>
                    <tbody id="chamada-tbody">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>

            <div id="chamada-empty" class="card" style="text-align:center; padding: 3rem;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2" style="margin-bottom:1rem;">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <line x1="3" y1="9" x2="21" y2="9"/>
                    <line x1="9" y1="21" x2="9" y2="9"/>
                </svg>
                <h3 style="color:#666;">Inicie a aula para ver a chamada</h3>
                <p style="color:#999;font-size:0.9rem;">Os alunos cadastrados aparecerão aqui.</p>
            </div>
        </section>

        <!-- Seção 2: Listagem e Gestão (Alunos) -->
        <section id="sec-students" class="hidden">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3>Base de Alunos Cadastrados</h3>
                    <button class="btn-primary" onclick="openRegistrationModal()">+ Cadastrar Novo Aluno</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th style="text-align:left">Nome do Aluno</th>
                            <th>Matrícula</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="student-table-body">
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Overlay de Cadastro (Modal) -->
    <div id="modal-register" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <h2>Cadastro Biométrico</h2>
            <p style="font-size:0.85rem; color:#666; margin-bottom: 1rem;">
                Posicione o rosto do aluno em frente à câmera deste computador para capturar a biometria.
            </p>
            
            <div class="camera-box" id="reg-camera-box">
                <video id="video-register" autoplay muted playsinline></video>
                <canvas id="overlay-register"></canvas>
                <div id="reg-camera-status" style="position:absolute; top:10px; left:10px; background:rgba(0,0,0,0.6); color:#fff; padding:4px 8px; border-radius:4px; font-size:0.8rem; z-index:10;">
                    Iniciando câmera...
                </div>
            </div>

            <form id="registration-form" onsubmit="event.preventDefault(); saveRegistration();">
                <div class="form-group">
                    <label for="reg-name">Nome Completo</label>
                    <input type="text" id="reg-name" placeholder="Ex: Ruan da Silva" required>
                </div>
                <div class="form-group">
                    <label for="reg-matricula">Matrícula</label>
                    <input type="text" id="reg-matricula" placeholder="Ex: 20231001" required>
                </div>
                
                <input type="hidden" id="reg-descriptor">

                <div style="display: flex; gap: 10px; margin-top: 1rem;">
                    <button type="submit" id="btn-save-reg" class="btn-primary" style="flex: 1;" disabled>Aguardando Rosto...</button>
                    <button type="button" class="btn-secondary" onclick="closeRegistrationModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Container para notificações (Toasts) -->
    <div id="toast-container"></div>

    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/professor-register.js?v=<?php echo time(); ?>"></script>
</body>

</html>
