<?php
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/header.php';
require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../src/controllers/DocumentosController.php';

$id = $_GET['id'] ?? null;
$controller = new DocumentosController();
$data = [];

if ($id) {
    $data = $controller->show($id);
    if (!$data) {
        die('Documento não encontrado.');
    }
}

$pageTitle = $id ? 'Editar Documento' : 'Novo Documento';

ob_start();

?>

<form action="documentos.php" method="POST" enctype="multipart/form-data" class="space-y-4">
    <input type="hidden" name="id" value="<?= htmlspecialchars($id ?? '') ?>">
    <input type="hidden" name="action" value="<?= $id ? 'update' : 'store' ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarCSRFToken()); ?>">

    <div class="bg-white rounded-lg shadow-md p-4 mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        
        <!-- Título do Documento -->
        <div class="col-span-1 md:col-span-2">
            <label for="titulo" class="block text-sm font-medium text-gray-700">Título</label>
            <input type="text" name="titulo" id="titulo" value="<?= htmlspecialchars($data['titulo'] ?? '') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <!-- Módulo -->
        <div>
            <label for="modulo" class="block text-sm font-medium text-gray-700">Módulo</label>
            <select name="modulo" id="modulo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Selecione um Módulo</option>
                <option value="energia" <?= (isset($data['modulo']) && $data['modulo'] == 'energia') ? 'selected' : '' ?>>Energia</option>
                <option value="agua" <?= (isset($data['modulo']) && $data['modulo'] == 'agua') ? 'selected' : '' ?>>Água</option>
                <option value="internet" <?= (isset($data['modulo']) && $data['modulo'] == 'internet') ? 'selected' : '' ?>>Internet</option>
                <option value="telefone" <?= (isset($data['modulo']) && $data['modulo'] == 'telefone') ? 'selected' : '' ?>>Telefone</option>
                <option value="geral" <?= (isset($data['modulo']) && $data['modulo'] == 'geral') ? 'selected' : '' ?>>Geral</option>
            </select>
        </div>

        <!-- Código de Instalação -->
        <div>
            <label for="codigo_instalacao" class="block text-sm font-medium text-gray-700">Código de Instalação</label>
            <input type="text" name="codigo_instalacao" id="codigo_instalacao" value="<?= htmlspecialchars($data['codigo_instalacao'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <!-- Número da Fatura -->
        <div>
            <label for="numero_fatura" class="block text-sm font-medium text-gray-700">Número da Fatura</label>
            <input type="text" name="numero_fatura" id="numero_fatura" value="<?= htmlspecialchars($data['numero_fatura'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <!-- Mês de Referência -->
        <div>
            <label for="mes_referencia" class="block text-sm font-medium text-gray-700">Mês de Referência</label>
            <input type="text" name="mes_referencia" id="mes_referencia" placeholder="Janeiro / 01" value="<?= htmlspecialchars($data['mes_referencia'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <!-- Ano de Referência -->
        <div>
            <label for="ano_referencia" class="block text-sm font-medium text-gray-700">Ano de Referência</label>
            <input type="number" name="ano_referencia" id="ano_referencia" value="<?= htmlspecialchars($data['ano_referencia'] ?? date('Y')) ?>" min="2020" max="2099" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <!-- Vencimento -->
        <div>
            <label for="vencimento" class="block text-sm font-medium text-gray-700">Vencimento</label>
            <input type="date" name="vencimento" id="vencimento" value="<?= htmlspecialchars($data['vencimento'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <!-- Total a Pagar -->
        <div>
            <label for="total_a_pagar" class="block text-sm font-medium text-gray-700">Total a Pagar</label>
            <input type="number" name="total_a_pagar" id="total_a_pagar" step="0.01" value="<?= htmlspecialchars($data['total_a_pagar'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div class="flex justify-end space-x-2">
        <a href="documentos.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">Cancelar</a>
        <button type="submit" class="bg-[#147cac] hover:bg-[#106191] text-white font-bold py-2 px-4 rounded">Salvar</button>
    </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../src/includes/template.php';
?>
