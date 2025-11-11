<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/login.php');
    exit;
}
require_once __DIR__ . '/../src/includes/helpers.php';

$_GET['module'] = 'unidades';
require_once __DIR__ . '/../src/includes/header.php';
require_once __DIR__ . '/../src/controllers/UnidadesController.php';

$controller = new UnidadesController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'store':
            $controller->store($_POST);
            break;
        case 'update':
            $controller->update($_POST['id'], $_POST);
            break;
        case 'destroy':
            $controller->destroy($_POST['id']);
            break;
    }
} else {
    $pageTitle = 'Unidades';
    $registros = $controller->index();
    require_once __DIR__ . '/../src/views/unidades/index.php';
}
