<?php
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/header.php';
require_once __DIR__ . '/../src/includes/helpers.php';

$module = $_GET['module'] ?? null;
$id = $_GET['id'] ?? null;

if (!$module) {
    die('Módulo não especificado.');
}

// Carrega o controller específico do módulo
$controllerName = ucfirst($module) . 'Controller';
$controllerFile = __DIR__ . '/../src/controllers/' . $controllerName . '.php';

if (!file_exists($controllerFile)) {
    die("Controller '{$controllerName}' não encontrado.");
}

require_once $controllerFile;
$controller = new $controllerName();

$data = [];
if ($id) {
    $data = $controller->edit($id);
}

// Buscar unidades para o dropdown
require_once __DIR__ . '/../src/includes/Database.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query("SELECT id, nome FROM unidades ORDER BY nome");
$unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $id ? 'Editar Fatura' : 'Nova Fatura';

ob_start();

$fields = $controller->getFields();

?>

<form action="<?= $module ?>.php" method="POST" class="space-y-4">
    <input type="hidden" name="id" value="<?= htmlspecialchars($id ?? '') ?>">
    <input type="hidden" name="action" value="<?= $id ? 'update' : 'store' ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarCSRFToken()); ?>">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        
        <div class="col-span-1 md:col-span-2">
            <label for="unidade_id" class="block text-sm font-medium text-gray-700">Unidade</label>
            <select name="unidade_id" id="unidade_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Selecione uma Unidade</option>
                <?php foreach ($unidades as $unidade): ?>
                    <option value="<?= $unidade['id'] ?>" <?= (isset($data['unidade_id']) && $data['unidade_id'] == $unidade['id']) ? 'selected' : '' ?>><?= htmlspecialchars($unidade['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php foreach ($fields as $field => $defaultValue): ?>
            <div>
                <label for="<?= $field ?>" class="block text-sm font-medium text-gray-700"><?= ucfirst(str_replace('_', ' ', $field)) ?></label>
                <input type="text" name="<?= $field ?>" id="<?= $field ?>" value="<?= htmlspecialchars($data[$field] ?? $defaultValue) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        <?php endforeach; ?>
    </div>

    <div class="flex justify-end space-x-2">
        <a href="<?= $module ?>.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">Cancelar</a>
        <button type="submit" class="bg-[#147cac] hover:bg-[#106191] text-white font-bold py-2 px-4 rounded">Salvar</button>
    </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../src/includes/template.php';
?>
