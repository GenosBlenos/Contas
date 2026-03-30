<?php
require_once __DIR__ . '/session_config.php';

// Verifica se o script está sendo acessado diretamente.
// Arquivos em 'includes' não devem ser acessíveis pela URL.
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    // Se for acesso direto, redireciona para a página de aviso.
    header('Location: ../includes/important.php');
    exit;
}

// Garante que as funções de helper estejam disponíveis.
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redireciona para login se não estiver logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se for uma requisição de API (definido no script que faz o include), retorna erro JSON
    if (defined('API_REQUEST') && API_REQUEST === true) {
        sendJsonResponse(false, 'Acesso não autorizado. A sua sessão pode ter expirado.', 401);
    } else {
        // Comportamento padrão para páginas normais: redirecionar para o login
        $loginPath = '/Contas/public/login.php';
        header('Location: ' . $loginPath);
        exit;
    }
}
