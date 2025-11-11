<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/login.php');
    exit;
}

require_once __DIR__ . '/../src/controllers/EnergiaController.php';
require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../src/includes/faturas_helper.php';

$controller = new EnergiaController();

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
                updateStatusFatura(Database::getInstance()->getConnection(), (int)$contaId, $novoStatus, 'energia');
                header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
                exit;
            }
            break;
    }
}

$pageTitle = 'Contas de Energia Elétrica';
$_GET['module'] = 'energia';

$data = $controller->index();

require_once __DIR__ . '/../src/includes/header.php';
require_once __DIR__ . '/../src/views/energia/index.php';