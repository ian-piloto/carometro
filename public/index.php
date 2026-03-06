<?php

/**
 * Página Principal do SISTEMA_LIBRARY_VISION.
 * Este arquivo contém a estrutura HTML base e inclui os módulos de CSS e JS.
 */
require_once __DIR__ . '/../config/config.php';

// Previne cache do navegador para garantir que as atualizações de JS/CSS sejam carregadas
header("Cache-Control: no-cache, must-revalidate");
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Controle de Acesso</title>

    <!-- Estilo Principal -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Bibliotecas de Terceiros (CDNs) -->
    <!-- Chart.js para geração de gráficos no Dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Face-api.js para os modelos de reconhecimento facial em tempo real no Browser -->
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script>
        if (typeof faceapi === 'undefined') {
            console.error('Falha ao carregar Face-API do CDN. Verifique sua conexão.');
            document.addEventListener('DOMContentLoaded', () => {
                const status = document.getElementById('vision-status');
                if (status) {
                    status.innerText = 'Erro: Falha na Internet';
                    status.style.backgroundColor = '#dc3545';
                }
            });
        }
    </script>
</head>

<body>

    <!-- Toolbar Lateral de Navegação -->
    <nav>
        <div class="logo">LIBRARY VISION</div>
        <ul class="nav-links">
            <li class="active" onclick="showSection('vision')" title="Scanner Facial">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                    <circle cx="12" cy="13" r="4" />
                </svg>
                Scanner Facial
            </li>
            <li onclick="showSection('dashboard')" title="Indicadores de Fluxo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                    <line x1="3" y1="9" x2="21" y2="9" />
                    <line x1="9" y1="21" x2="9" y2="9" />
                </svg>
                Dashboard
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
    </nav>

    <!-- Conteúdo Principal Dinâmico -->
    <main>
        <header>
            <h1 id="page-title">Scanner de Acesso</h1>
            <div id="clock" style="font-weight: bold; font-size: 1.2rem;">00:00:00</div>
        </header>

        <!-- Seção 1: Monitoramento e Scanner Facial (Visão) -->
        <section id="sec-vision">
            <div class="vision-container">
                <div class="card">
                    <div class="video-wrapper">
                        <!-- Feed de Vídeo em Tempo Real -->
                        <video id="video" autoplay muted></video>
                        <!-- Efeito Visual de Desfoque ( Portrait Mode ) -->
                        <div id="portrait-blur"></div>
                        <!-- Guia de Enquadramento para o Rosto -->
                        <div class="face-focus-guide"></div>
                        <!-- Canvas para Desenho de Marcadores Faciais -->
                        <canvas id="overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 5;"></canvas>
                        <!-- Feedback visual de inicialização -->
                        <div id="camera-placeholder" style="color: white; position: absolute; z-index: 10;">Iniciando Câmera...</div>
                    </div>
                </div>

                <div class="status-panel">
                    <div class="card">
                        <h3>Status do Sistema</h3>
                        <div id="vision-status" class="status-indicator">Iniciando Biometria...</div>
                        <p id="vision-msg" style="font-size: 0.8rem; margin-top: 0.5rem; color: #666;">
                            Posicione o rosto no centro da câmera para identificação automática.
                        </p>
                    </div>

                    <div class="card">
                        <h3>Controles Rápidos</h3>
                        <button id="btn-toggle-camera" class="btn-primary" style="width: 100%; margin-bottom: 0.5rem; background-color: #333;" onclick="toggleCamera()">Desligar Câmera</button>
                        <button class="btn-primary" style="width: 100%; margin-bottom: 0.5rem;" onclick="openRegistration()">Novo Cadastro</button>
                        <button class="btn-secondary" style="width: 100%;" onclick="retryCamera()">Reiniciar Câmera</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seção 2: Analíticos e Exportação (Dashboard) -->
        <section id="sec-dashboard" class="hidden">
            <div class="dashboard-grid" style="display: grid; grid-template-columns: 1fr 300px; gap: 1.5rem;">
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3>Fluxo de Acesso por Hora</h3>
                        <div class="chart-controls" style="display: flex; gap: 5px;">
                            <button class="btn-secondary btn-sm active" onclick="changeChartType('line')" id="btn-type-line" title="Gráfico de Linha">Linha</button>
                            <button class="btn-secondary btn-sm" onclick="changeChartType('bar')" id="btn-type-bar" title="Gráfico de Colunas">Coluna</button>
                            <button class="btn-secondary btn-sm" onclick="changeChartType('doughnut')" id="btn-type-doughnut" title="Gráfico Circular">Circular</button>
                        </div>
                    </div>
                    <div style="height: 300px; position: relative;">
                        <!-- Gráfico gerado pelo Chart.js -->
                        <canvas id="flowChart"></canvas>
                    </div>
                </div>
                <div class="card" style="text-align: center;">
                    <h3>Total de Acessos</h3>
                    <div style="font-size: 3rem; font-weight: bold; color: var(--primary);" id="total-acessos">0</div>
                    <p>Entradas e Saídas Hoje</p>
                    <hr style="margin: 1rem 0;">
                    <button class="btn-primary" onclick="exportExcel()">Exportar Relatório Excel</button>
                </div>
            </div>
        </section>

        <!-- Seção 3: Listagem e Gestão (Alunos) -->
        <section id="sec-students" class="hidden">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3>Base de Alunos Cadastrados</h3>
                    <button class="btn-primary" onclick="openRegistration()">+ Cadastrar Novo Aluno</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome do Aluno </th>
                            <th>Turma</th>
                            <th>Horário de Entrada</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="student-table-body">
                        <!-- Conteúdo preenchido dinamicamente via JS (app.js) -->
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Overlay de Cadastro (Modal) -->
    <div id="modal-register" class="modal-overlay">
        <div class="modal-content">
            <h2>Novo Registro Biométrico</h2>
            <form id="registration-form" onsubmit="event.preventDefault(); saveRegistration();">
                <div class="form-group">
                    <label for="reg-name">Nome Completo</label>
                    <input type="text" id="reg-name" placeholder="Ex: Ruan da Silva Gomes" required>
                </div>
                <div class="form-group">
                    <label for="reg-class">Turma</label>
                    <select id="reg-class" required style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;">
                        <option value="" disabled selected>Selecione a Turma</option>
                        <option value="Turma 1">Turma 1</option>
                        <option value="Turma 2">Turma 2</option>
                        <option value="Turma 3">Turma 3</option>
                        <option value="Turma 4">Turma 4</option>
                        <option value="Turma 5">Turma 5</option>
                    </select>
                </div>
                <!-- Status da captura facial no momento do cadastro -->
                <div id="face-capture-status" style="margin-bottom: 1.5rem; font-size: 0.9rem; color: var(--success); font-weight: bold;">
                    [Aguardando biometria...]
                </div>
                <!-- Descriptor facial (hidden) que será salvo no banco -->
                <input type="hidden" id="reg-descriptor">

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary" style="flex: 1;">Salvar Cadastro</button>
                    <button type="button" class="btn-secondary" onclick="closeRegistration()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Overlay Fullscreen de Acesso Liberado -->
    <div id="access-overlay">
        <div class="access-icon">
            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <div class="access-title">ACESSO LIBERADO</div>
        <div class="access-name" id="access-name-display">—</div>
        <div class="access-class-badge" id="access-class-display"></div>
        <div class="access-progress">
            <div class="access-progress-bar" id="access-progress-bar"></div>
        </div>
    </div>

    <!-- Container para notificações (Toasts) -->
    <div id="toast-container"></div>

    <!-- Módulo de Chat com IA Flutuante -->
    <div id="ai-chat-wrapper">
        <!-- Janela do Chat -->
        <div id="ai-chat-window" class="hidden">
            <div class="chat-header">
                <div class="chat-title">
                    <span class="online-indicator"></span>
                    LívIA - Assistente Virtual
                </div>
                <button class="btn-close-chat" onclick="toggleChat()" aria-label="Fechar Chat">×</button>
            </div>
            <div id="chat-messages" class="chat-body">
                <div class="message ai">
                    Olá! Eu sou a LívIA, sua assistente virtual. Em que posso ajudar com as informações do sistema?
                </div>
            </div>
            <div class="chat-footer">
                <input type="text" id="chat-input" placeholder="Escreva sua mensagem aqui..." onkeypress="handleChatKey(event)">
                <button id="btn-send-chat" onclick="sendMessage()" title="Enviar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
        <!-- Botão Flutuante -->
        <button id="btn-open-chat" onclick="toggleChat()" title="Conversar com a IA">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        </button>
    </div>

    <!-- Scripts de Lógica (Carregados após o HTML para evitar bloqueios) -->
    <!-- Módulo 1: Reconhecimento Facial -->
    <script src="assets/js/facialRecognition.js?v=<?php echo time(); ?>"></script>
    <!-- Módulo 2: Orquestração da UI e API -->
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
    <!-- Módulo 3: Lógica do Chat IA -->
    <script src="assets/js/chat.js?v=<?php echo time(); ?>"></script>
</body>

</html>