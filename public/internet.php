<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/public/login.php');
    exit;
}

require_once __DIR__ . '/../src/controllers/InternetController.php';
require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../src/includes/PaginationHelper.php';

$controller = new InternetController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    switch ($action) {
        case 'store':
            $controller->store();
            break;
        case 'update':
            $controller->update();
            break;
        case 'destroy':
            $controller->destroy();
            break;
    }
}

$pageTitle = 'Internet Predial';
$_GET['module'] = 'internet';

$data = $controller->index();

// Configurar paginação
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10; // Itens por página

$total_registros = count($data['registros'] ?? []);
$pagination = new PaginationHelper($total_registros, $limit, $page);

// Paginar os registros
$offset = $pagination->getOffset();
$registros = array_slice($data['registros'] ?? [], $offset, $limit);

require_once __DIR__ . '/../src/includes/header.php';
require_once __DIR__ . '/../src/views/internet/index.php';
?>
