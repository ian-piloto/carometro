<?php

/**
 * Arquivo de configurações globais e constantes do sistema.
 */

// --- Configurações Gerais do Aplicativo ---
define('APP_NAME', 'SISTEMA_LIBRARY_VISION');
define('BASE_URL', 'http://localhost/sistema-biblioteca');

// --- Definição de Caminhos Absolutos para o Sistema ---
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('SRC_PATH', ROOT_PATH . '/src');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// --- Identidade Visual (Cores Institucionais) ---
define('COLOR_PRIMARY', '#BC0000'); // Vermelho Carmim (Destaque)
define('COLOR_SECONDARY', '#FFFFFF'); // Branco (Fundo/Texto)

// --- Configurações Regionais ---
// Define o fuso horário padrão para Brasileiro (Brasília)
date_default_timezone_set('America/Sao_Paulo');
