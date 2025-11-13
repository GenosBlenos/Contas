<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/header.php';
require_once __DIR__ . '/../src/controllers/UnidadesController.php';
$pageTitle = 'Dashboard';

// 1. Ponto de Entrada e Autenticação
require_once __DIR__ . '/../src/includes/Logger.php';
require_once __DIR__ . '/../src/includes/SecurityManager.php';

$pageTitle = 'Documentos - Gerenciamento de Faturas';

// Obtém o módulo da URL, se existir
$module = $_GET['module'] ?? null;
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
    // 1. Obter parâmetros de pesquisa e página da URL
    $search = $_GET['search'] ?? '';
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = 10; // Define quantos itens por página

    // 2. Definir o título da página
    $pageTitle = 'Unidades';

    // 3. Chamar o método index do controlador com os novos parâmetros
    // (Você precisará atualizar o UnidadesController para aceitar isso, veja abaixo)
    $data = $controller->index($search, $page, $limit);

    // 4. Extrair as variáveis que a view (index.php) espera
    $registros = $data['registros'];
    $total_pages = $data['total_pages'];
}

?>
<?php include __DIR__ . '/../src/views/unidades/index.php'; ?>