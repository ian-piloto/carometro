<?php
/**
 * Tela de Login — Sistema de Presença Facial
 * Se já logado, redireciona direto para o painel.
 */
if (session_status() === PHP_SESSION_NONE)
    session_start();
if (isset($_SESSION['professor'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso ao Sistema — Presença Facial</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<div class="login-card">

    <div class="login-header">
        <div class="login-logo">
            <!-- Ícone de câmera/reconhecimento facial -->
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
            </svg>
        </div>
        <h1>Presença Facial</h1>
        <p>Acesso exclusivo para professores cadastrados</p>
    </div>

    <div id="login-error"></div>

    <form id="login-form" onsubmit="handleLogin(event)">
        <div class="form-group">
            <label for="email">E-mail institucional</label>
            <div class="input-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                <input type="email" id="email" placeholder="professor@senai.br" required autocomplete="email">
            </div>
        </div>

        <div class="form-group">
            <label for="senha">Senha</label>
            <div class="input-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                <input type="password" id="senha" placeholder="••••••••" required autocomplete="current-password">
            </div>
        </div>

        <button type="submit" class="btn-login" id="btn-login">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                <polyline points="10 17 15 12 10 7"/>
                <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            Entrar no Sistema
        </button>
    </form>

    <div class="login-footer">
        Sistema de Presença por Reconhecimento Facial &nbsp;·&nbsp; SENAI
    </div>
</div>

<script>
async function handleLogin(e) {
    e.preventDefault();

    const btn   = document.getElementById('btn-login');
    const error = document.getElementById('login-error');

    btn.classList.add('loading');
    btn.textContent = 'Verificando...';
    error.style.display = 'none';

    const payload = {
        email: document.getElementById('email').value.trim(),
        senha: document.getElementById('senha').value
    };

    try {
        const res  = await fetch('api.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            window.location.href = 'index.php';
        } else {
            error.textContent    = data.message || 'Credenciais inválidas.';
            error.style.display  = 'block';
            btn.classList.remove('loading');
            btn.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
                Entrar no Sistema`;
        }
    } catch (err) {
        error.textContent   = 'Erro de conexão com o servidor.';
        error.style.display = 'block';
        btn.classList.remove('loading');
    }
}
</script>
</body>
</html>
