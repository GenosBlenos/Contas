<?php
// Configurações de sessão seguras
// Este arquivo deve ser incluído no topo de todos os entrypoints, ANTES de session_start().

if (session_status() === PHP_SESSION_ACTIVE) {
    // Não faz nada se uma sessão já estiver ativa, para evitar o erro.
    // Apenas loga uma advertência para futura depuração.
    error_log('Aviso: session_config.php foi incluído depois que uma sessão já estava ativa.');
    return;
}

// Define o nome do cookie da sessão para algo específico do aplicativo
ini_set('session.name', 'COMPRAS_SESSID');

// Força a sessão a usar apenas cookies e não IDs na URL
ini_set('session.use_only_cookies', 1);

// Garante que o cookie da sessão seja enviado apenas por HTTPS
// Em ambiente de desenvolvimento (localhost), pode ser necessário comentar a linha abaixo se não usar SSL
// ini_set('session.cookie_secure', 1);

// Impede que o JavaScript acesse o cookie da sessão
ini_set('session.cookie_httponly', 1);

// Define o SameSite para Strict para mitigar ataques CSRF
ini_set('session.cookie_samesite', 'Strict');

// Usa o modo estrito para sessões, o que impede o uso de IDs de sessão não inicializados
ini_set('session.use_strict_mode', 1);

// Define um tempo de vida mais longo para o cookie da sessão (ex: 1 hora)
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 0); // O cookie expira quando o navegador é fechado

// Impede que o PHP inclua o ID da sessão em URLs
ini_set('session.use_trans_sid', 0);
?>