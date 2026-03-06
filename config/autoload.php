<?php

/**
 * Autoloader manual simplificado para o projeto.
 * 
 * Este arquivo substitui a funcionalidade do Autoload do Composer,
 * mapeando o namespace 'App\' para o diretório 'src/'.
 */
spl_autoload_register(function ($class) {
    // Prefixo do namespace do projeto
    $prefix = 'App\\';

    // Diretório base onde os arquivos fonte estão localizados
    $base_dir = __DIR__ . '/../src/';

    // Verifica se a classe utiliza o prefixo esperado
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Obtém o nome relativo da classe (sem o prefixo)
    $relative_class = substr($class, $len);

    // Substitui separadores de namespace por separadores de diretório e adiciona .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Se o arquivo existir, realiza o carregamento
    if (file_exists($file)) {
        require $file;
    }
});
