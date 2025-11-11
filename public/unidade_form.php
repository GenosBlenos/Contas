<?php
require_once __DIR__ . '/../src/includes/config.php';
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/session_config.php';
require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../src/includes/flash_helpers.php';
require_once __DIR__ . '/../src/controllers/UnidadesController.php';
require_once __DIR__ . '/../src/includes/header.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/login.php');
    exit;
}

$pageTitle = 'Cadastro de Unidade';
$controller = new UnidadesController();
$module = $_GET['module'] ?? $_POST['module'] ?? null;

// Processamento do formulário (Criação/Atualização)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nome' => $_POST['nome'] ?? '',
        'endereco' => $_POST['endereco'] ?? '',
        'responsavel' => $_POST['responsavel'] ?? '',
        'identificador_unidade' => $_POST['identificador_unidade'] ?? null,
        // Se o filtro por módulo for implementado no DB, adicione aqui:
        // 'modulo' => $module,
    ];

    // Sanitiza os dados
    $data = sanitizeInput($data);

    $id = $_POST['id'] ?? null;

    if ($id) {
        // Atualização
        $success = $controller->update($id, $data);
    } else {
        // Criação
        $success = $controller->store($data);
    }

    if ($success) {
        flashMessage('success', 'Unidade salva com sucesso!');
        header('Location: unidades.php?module=' . urlencode($module ?? ''));
        exit;
    } else {
        $error_message = 'Ocorreu um erro ao salvar a unidade.';
    }
}

// Carregamento dos dados para edição ou formulário em branco para criação
$unidade = null;
$pageTitle = 'Nova Unidade';
if (isset($_GET['id'])) {
    $unidade = $controller->show($_GET['id']);
    $pageTitle = 'Editar Unidade';
}

ob_start();
?>

<div class="space-y-6">
    <?php
    // Exibe a mensagem da sessão, se houver
    if (isset($_SESSION['msg'])) {
        echo $_SESSION['msg'];
        unset($_SESSION['msg']);
    }
    ?>

    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <form action="unidade_form.php" method="POST" class="space-y-6 bg-white p-6 rounded-lg shadow-lg">
        <input type="hidden" name="id" value="<?= htmlspecialchars($unidade['id'] ?? '') ?>">
        <input type="hidden" name="module" value="<?= htmlspecialchars($module ?? '') ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-1">
                <label for="nome" class="block text-sm font-medium text-gray-700">Nome da Unidade</label>
                <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($unidade['nome'] ?? '') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="md:col-span-1">
                <label for="responsavel" class="block text-sm font-medium text-gray-700">Responsável</label>
                <input type="text" name="responsavel" id="responsavel" value="<?= htmlspecialchars($unidade['responsavel'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="md:col-span-2">
                <label for="endereco" class="block text-sm font-medium text-gray-700">Endereço</label>
                <input type="text" name="endereco" id="endereco" value="<?= htmlspecialchars($unidade['endereco'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="md:col-span-2">
                <label for="identificador_unidade" class="block text-sm font-medium text-gray-700">Identificador da Unidade (para Faturas)</label>
                <input type="text" name="identificador_unidade" id="identificador_unidade" value="<?= htmlspecialchars($unidade['identificador_unidade'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-2 text-sm text-gray-500">Ex: Código de Instalação, CNPJ, etc. Usado para associar faturas PDF automaticamente.</p>
            </div>
        </div>

        <div class="flex justify-end space-x-2 pt-4">
            <a href="unidades.php?module=<?= htmlspecialchars($module ?? '') ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md shadow-sm">Cancelar</a>
            <button type="submit" class="bg-[#4a90e2] hover:bg-[#2563eb] text-white font-bold py-2 px-4 rounded-md shadow-sm">Salvar</button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../src/includes/template.php';
?>