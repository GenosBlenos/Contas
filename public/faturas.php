<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/public/login.php');
    exit;
}

require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../src/includes/PaginationHelper.php';
require_once __DIR__ . '/../src/includes/config.php';
require_once __DIR__ . '/../src/controllers/ApiIntegrationController.php';
require_once __DIR__ . '/../src/controllers/DocumentosController.php';
require_once __DIR__ . '/../src/models/Documento.php';
require_once __DIR__ . '/../src/includes/Database.php';

// Page setup
$pageTitle = 'Faturas - Todas as Fontes';
$module = $_GET['module'] ?? 'agua';

$docController = new DocumentosController();
$apiController = new ApiIntegrationController();
$db = Database::getInstance()->getConnection();

$message = null;
// simple admin guard for sync actions
$isAdmin = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    try {
        if ($action === 'sync_api') {
            if (!$isAdmin) {
                $message = 'Ação restrita: apenas administradores podem sincronizar via API.';
            } else {
                $apiUrl = trim($_POST['api_url'] ?? '');
                $apiKey = trim($_POST['api_key'] ?? '') ?: null;
                if ($apiUrl) {
                    $report = $apiController->syncFromApi($apiUrl, $apiKey);
                    $message = "Sincronizados: {$report['success_count']} - Erros: {$report['error_count']}";
                } else {
                    $message = 'Informe a URL da API.';
                }
            }
        } elseif ($action === 'sync_provider') {
            if (!$isAdmin) {
                $message = 'Ação restrita: apenas administradores podem sincronizar providers.';
            } else {
                $provider = trim($_POST['provider'] ?? '');
                $credJson = trim($_POST['credentials_json'] ?? '');
                $credentials = [];
                if ($credJson) {
                    $decoded = json_decode($credJson, true);
                    if (json_last_error() === JSON_ERROR_NONE) $credentials = $decoded;
                }
                // ensure module is set so provider imports to the correct module
                if (empty($credentials['modulo'])) $credentials['modulo'] = $module;
                if ($provider) {
                    $report = $apiController->syncFromProvider($provider, $credentials);
                    $message = "Sincronizados: {$report['success_count']} - Erros: {$report['error_count']}";
                } else {
                    $message = 'Selecione um provider.';
                }
            }
        }
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
    }
}

// Fixed modules and display labels (only these modules shown)
$moduleOptions = [
    'agua' => 'Água',
    'energia' => 'Energia Elétrica',
    'telefone' => 'Telefone Predial',
    'internet' => 'Internet Predial'
];

// Month names for reference formatting
$meses = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];

// keys for original logic
$modules = array_keys($moduleOptions);

// Fetch documents using controller (module-specific)
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$result = $docController->index($module, $page, $perPage);
// $result is ['items'=>..., 'total'=>...] for module-specific listing
if (is_array($result) && isset($result['items'])) {
    $documentos = $result['items'];
    $totalItems = (int)($result['total'] ?? 0);
} else {
    $documentos = $result;
    $totalItems = is_array($documentos) ? count($documentos) : 0;
}
$paginator = new PaginationHelper($totalItems, $perPage, $page);

// Provider suggestion per module
$providerMap = [
    'agua' => 'saae',
    'energia' => 'cpfl',
    'internet' => 'bestfibra',
    'telefone' => 'netserv'
];

$suggestedProvider = $providerMap[$module] ?? '';

// Fixed status and display fields for the table
$statusMap = [
    'pago' => '✅ Pago',
    'pendente' => '🕒 Pendente',
    'vencido' => '❌ Vencido'
];

require_once __DIR__ . '/../src/includes/header.php';
?>
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Faturas</h1>
            <p class="text-sm text-gray-600">Visualize faturas baixadas via API/provedores e gerencie metadados.</p>
        </div>
        <div>
            <a class="text-sm font-bold text-gray-800">Módulos:</a>
            <form method="get" class="flex gap-2">
                <select name="module" class="px-3 py-2 border rounded" onchange="this.form.submit()">
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= ($m === $module) ? 'selected' : '' ?>><?= htmlspecialchars($moduleOptions[$m]) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-4 p-4 bg-blue-50 border border-blue-100 text-blue-800 rounded"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (empty($documentos)): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded">Nenhum documento encontrado.</div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arquivo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referência</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimento</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($documentos as $doc):
                            // installation identifier (prefer explicit codes)
                            $instalacao = $doc['codigo_instalacao'] ?? $doc['numero_ligacao'] ?? $doc['unidade'] ?? '';
                            $mesRef = $doc['vencimento'] ? date('m', strtotime($doc['vencimento'])) : '';
                            $anoRef = $doc['vencimento'] ? date('Y', strtotime($doc['vencimento'])) : '';
                            $mesRefExt = $meses[$mesRef] ?? $mesRef;
                            $reference = ($mesRefExt && $anoRef) ? "{$mesRefExt}/{$anoRef}" : '';
                            $moduleLabel = $moduleOptions[$module] ?? $module;
                            $titleParts = array_filter([$instalacao, $mesRef, $anoRef, $module], function($v){ return trim((string)$v) !== ''; });
                            $title = implode('_', $titleParts);
                            $file = $doc['arquivo'] ?? $doc['arquivo_pdf'] ?? null;
                            $vencimento = $doc['vencimento'] ?? $doc['data_vencimento'] ?? '';
                            $vencimento = $vencimento ? date('d/m/Y', strtotime($vencimento)) : '';
                            $valor = $doc['total_a_pagar'] ?? $doc['valor_total'] ?? '';
                            // valor formatado com 2 casas decimais e vírgula como separador decimal
                            $valor = is_numeric($valor) ? number_format((float)$valor, 2, ',', '.') : $valor;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars((string)$title) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                <?php if ($file): ?>
                                    <a href="/Contas/uploads/<?= rawurlencode($file) ?>" target="_blank"
                                       class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200 transition-colors">
                                        👁️ Visualizar
                                    </a>
                                <?php else: ?>   
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500"><?= htmlspecialchars((string)$reference) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500"><?= htmlspecialchars((string)$vencimento) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500">R$ <?= htmlspecialchars((string)$valor) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                <?php
                                $statusKey = strtolower(trim((string)($doc['status'] ?? '')));
                                $statusClassMap = [
                                    'pago' => 'bg-green-100 text-green-700 rounded text-xs hover:bg-green-200 transition-colors',
                                    'vencido' => 'bg-red-100 text-red-700 rounded text-xs hover:bg-red-200 transition-colors',
                                    'pendente' => 'bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200 transition-colors'
                                ];
                                $statusClass = $statusClassMap[$statusKey] ?? 'bg-gray-100 text-gray-700 rounded-full text-xs';
                                $statusLabel = $statusMap[$statusKey] ?? '';
                                ?>
                                <span class="inline-flex items-center px-2 py-1 <?= $statusClass ?> font-medium">
                                    <?= htmlspecialchars((string)$statusLabel) ?: '&mdash;'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium">
                                <a href="documento_form.php?id=<?= urlencode($doc['id']) ?>&module=<?= htmlspecialchars($module ?? '') ?>" target="_blank"
                                       class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200 transition-colors">
                                        ✏️ Editar
                                </a>
                                <?php if ($file): ?>
                                    <a href="/Contas/uploads/<?= rawurlencode($file) ?>" target="_blank"
                                       class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200 transition-colors">
                                        💽 Baixar
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Pagination -->
        <?php if ($paginator->getTotalPages() > 1): ?>
        <div class="mt-4 flex items-center justify-between">
            <div class="text-sm text-gray-600">Mostrando página <?= $paginator->getCurrentPage() ?> de <?= $paginator->getTotalPages() ?></div>
            <div class="inline-flex items-center space-x-2">
                <?php if ($paginator->hasPreviousPage()): ?>
                    <a href="?<?= PaginationHelper::buildQueryParams(['page' => $paginator->getCurrentPage() - 1]) ?>" class="px-3 py-1 bg-white border rounded">Anterior</a>
                <?php endif; ?>

                <?php
                $range = $paginator->getPaginationRange();
                for ($i = $range['start']; $i <= $range['end']; $i++):
                ?>
                    <?php if ($i == $paginator->getCurrentPage()): ?>
                        <span class="px-3 py-1 bg-indigo-600 text-white rounded"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= PaginationHelper::buildQueryParams(['page' => $i]) ?>" class="px-3 py-1 bg-white border rounded"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($paginator->hasNextPage()): ?>
                    <a href="?<?= PaginationHelper::buildQueryParams(['page' => $paginator->getCurrentPage() + 1]) ?>" class="px-3 py-1 bg-white border rounded">Próxima</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-4 rounded shadow">
            <h3 class="font-semibold mb-3">Sincronizar via API</h3>
            <form method="post">
                <input type="hidden" name="action" value="sync_api">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">API URL</label>
                    <input type="text" name="api_url" class="mt-1 block w-full border rounded px-3 py-2" placeholder="https://api.exemplo/faturas">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Chave API (opcional)</label>
                    <input type="text" name="api_key" class="mt-1 block w-full border rounded px-3 py-2">
                </div>
                <div>
                    <button class="bg-green-600 text-white px-4 py-2 rounded">Sincronizar</button>
                </div>
            </form>
        </div>

        <div class="bg-white p-4 rounded shadow">
            <h3 class="font-semibold mb-3">Sincronizar via Provedor</h3>
            <form method="post">
                <input type="hidden" name="action" value="sync_provider">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Provedor</label>
                    <select name="provider" class="mt-1 block w-full border rounded px-3 py-2">
                        <option value="saae" <?= ($suggestedProvider === 'saae') ? 'selected' : '' ?>>SAAE</option>
                        <option value="cpfl" <?= ($suggestedProvider === 'cpfl') ? 'selected' : '' ?>>CPFL</option>
                        <option value="bestfibra" <?= ($suggestedProvider === 'bestfibra') ? 'selected' : '' ?>>BestFibra</option>
                        <option value="netserv" <?= ($suggestedProvider === 'netserv') ? 'selected' : '' ?>>Netserv</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Credenciais (JSON)</label>
                    <textarea name="credentials_json" class="mt-1 block w-full border rounded px-3 py-2" rows="3" placeholder='{"download_page":"https://...","modulo":"agua"}'></textarea>
                </div>
                <div>
                    <button class="bg-green-600 text-white px-4 py-2 rounded">Sincronizar Provedor</button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../src/includes/footer.php';
?>
