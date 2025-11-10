<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /compras/login.php');
    exit;
}

require_once __DIR__ . '/../src/controllers/InternetController.php';
require_once __DIR__ . '/../src/includes/helpers.php';

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

require_once __DIR__ . '/../src/includes/header.php';
require_once __DIR__ . '/../src/views/internet/index.php';