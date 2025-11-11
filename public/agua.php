<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/login.php');
    exit;
}

require_once __DIR__ . '/../src/controllers/AguaController.php';
require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../src/includes/faturas_helper.php';

$controller = new AguaController();

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
        case 'update_status_fatura':
            $contaId = $_POST['conta_id'] ?? null;
            $novoStatus = $_POST['status'] ?? null;
            if ($contaId && $novoStatus) {
                updateStatusFatura(Database::getInstance()->getConnection(), 'agua', $contaId, $novoStatus);
                header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
                exit;
            }
            break;
    }
}

$pageTitle = 'Contas de Água';
$_GET['module'] = 'agua';

$data = $controller->index();

require_once __DIR__ . '/../src/includes/header.php';
require_once __DIR__ . '/../src/views/agua/index.php';
