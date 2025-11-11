<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
$pageTitle = 'Cadastro de Unidade';

require_once __DIR__ . '/../controllers/UnidadesController.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash_helpers.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/login.php');
    exit;
}

$controller = new UnidadesController();
$module = $_GET['module'] ?? $_POST['module'] ?? null;

// Processamento do formulário (Criação/Atualização)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nome' => $_POST['nome'] ?? '',
        'endereco' => $_POST['endereco'] ?? '',
        'responsavel' => $_POST['responsavel'] ?? '',
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
        header('Location: ../../public/unidades.php?module=' . urlencode($module ?? ''));
        exit;
    } else {
        // A mensagem de erro é definida no controller via flashMessage
        // Apenas redireciona de volta para o formulário
        header('Location: unidade_form.php?' . http_build_query($_GET));
        exit;
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

<div class="container mx-auto px-4 sm:px-8">
    <div class="py-8">
        <div>
            <h2 class="text-2xl font-semibold leading-tight"><?= htmlspecialchars($pageTitle) ?></h2>
        </div>
        <div class="-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto">
            <div class="inline-block min-w-full shadow rounded-lg overflow-hidden p-6">
                <?php displayFlashMessages(); ?>

                <form action="unidade_form.php" method="POST" class="space-y-4">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($unidade['id'] ?? '') ?>">
                    <input type="hidden" name="module" value="<?= htmlspecialchars($module ?? '') ?>">

                    <div>
                        <label for="nome" class="block text-sm font-medium text-gray-700">Nome da Unidade</label>
                        <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($unidade['nome'] ?? '') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="endereco" class="block text-sm font-medium text-gray-700">Endereço</label>
                        <input type="text" name="endereco" id="endereco" value="<?= htmlspecialchars($unidade['endereco'] ?? '') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="responsavel" class="block text-sm font-medium text-gray-700">Responsável</label>
                        <input type="text" name="responsavel" id="responsavel" value="<?= htmlspecialchars($unidade['responsavel'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex justify-end space-x-2">
                        <a href="../../public/unidades.php?module=<?= htmlspecialchars($module ?? '') ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">Cancelar</a>
                        <button type="submit" class="bg-[#147cac] hover:bg-[#106191] text-white font-bold py-2 px-4 rounded">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/template.php';
?>
