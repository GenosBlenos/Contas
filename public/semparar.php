<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/public/login.php');
    exit;
}
require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../src/includes/faturas_helper.php'; // Inclui o novo helper
require_once __DIR__ . '/../src/includes/Database.php'; // Inclui a classe do banco de dados
require_once __DIR__ . '/../src/includes/PaginationHelper.php';

$pdo = Database::getInstance()->getConnection(); // Obtém a conexão PDO

// Define o título da página e o módulo para o menu de navegação
$pageTitle = 'Contas de Sem Parar';
$_GET['module'] = 'semparar';

// Processa a atualização de status antes de qualquer saída
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status_fatura') {
    $contaId = $_POST['conta_id'] ?? null;
    $novoStatus = $_POST['status'] ?? null;

    if ($contaId && $novoStatus && is_numeric($contaId)) {
        updateStatusFatura($pdo, (int)$contaId, $novoStatus, 'semparar');
        // Redireciona para evitar reenvio do formulário ao recarregar a página
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
        exit;
    }
}


// 1. Buscar os registros de Sem Parar da tabela
$filtros = ['categoria' => 'semparar'];
$registros_semparar_all = buscarFaturas($pdo, $filtros) ?? [];

// 2. Calcular os totais para os cartões do dashboard
$totais = calcularTotaisFaturas($registros_semparar_all, 'semparar');
extract($totais); // Extrai $totalPendente, etc.

// Configurar paginação
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10; // Itens por página

$total_registros = count($registros_semparar_all);
$pagination = new PaginationHelper($total_registros, $limit, $page);

// Paginar os registros
$offset = $pagination->getOffset();
$registros_semparar = array_slice($registros_semparar_all, $offset, $limit);

require_once __DIR__ . '/../src/includes/header.php';

require_once __DIR__ . '/../src/views/semparar/index.php';
?>
