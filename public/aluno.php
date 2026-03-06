<?php
session_start();
// Requires auth to be safe, or could be open with a token. Assuming token/session is active.
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carômetro - Autoatendimento (Tablet)</title>
    <!-- Módulos e Estilos -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        body, html { margin: 0; padding: 0; width: 100vw; height: 100vh; overflow: hidden; background: #000; }
        .fullscreen-video-container { position: relative; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; }
        video { min-width: 100%; min-height: 100%; object-fit: cover; }
        .vision-status-overlay { position: absolute; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.6); color: #fff; padding: 10px 20px; border-radius: 20px; font-weight: bold; z-index: 10; font-size: 1.2rem; }
        #portrait-blur { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 2; transition: all 0.2s; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); mask-image: radial-gradient(circle at var(--face-x, 50%) var(--face-y, 50%), transparent var(--face-r, 0%), black calc(var(--face-r, 0%) + 15%)); -webkit-mask-image: radial-gradient(circle at var(--face-x, 50%) var(--face-y, 50%), transparent var(--face-r, 0%), black calc(var(--face-r, 0%) + 15%)); }
        
        #access-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: var(--success);
            z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: #fff; opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
        }
        #access-overlay.show { opacity: 1; pointer-events: all; }
        .access-title { font-size: 3rem; font-weight: 800; line-height: 1; margin: 1rem 0; text-transform: uppercase; letter-spacing: 2px; }
        .access-name { font-size: 4rem; font-weight: 600; text-align: center; }
        .access-icon { background: rgba(255, 255, 255, 0.2); padding: 30px; border-radius: 50%; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
</head>
<body>
    <div class="fullscreen-video-container">
        <video id="video" autoplay muted playsinline></video>
        <div id="portrait-blur"></div>
        <canvas id="overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 5;"></canvas>
        <div id="vision-status" class="vision-status-overlay">Carregando IA...</div>
    </div>

    <!-- Overlay Fullscreen de Acesso Liberado -->
    <div id="access-overlay">
        <div class="access-icon">
            <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <div class="access-title">PRESENÇA REGISTRADA</div>
        <div class="access-name" id="access-name-display">—</div>
    </div>

    <script src="assets/js/aluno-facial.js?v=<?php echo time(); ?>"></script>
</body>
</html>
