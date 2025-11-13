<?php
// Inicia o buffer de saída e a sessão
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inclui a conexão com o banco de dados
require_once __DIR__ . '/../../../app/conexao.php';

// Lógica para lidar com o cadastro de uma nova conta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_conta') {
    $vencimento = $_POST['data_vencimento'] ?? null;
    $total_a_pagar = $_POST['total_a_pagar'] ?? null;
    $status = $_POST['status'] ?? 'Pendente';

    if ($vencimento && $total_a_pagar) {
        try {
            $stmt = $pdo->prepare("INSERT INTO semparar (data_vencimento, total_a_pagar, status) VALUES (?, ?, ?)");
            $stmt->execute([$vencimento, $total_a_pagar, $status]);
            $_SESSION['success'] = "Conta de semparar cadastrada com sucesso!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erro ao cadastrar conta: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Todos os campos são obrigatórios.";
    }
    // Redireciona para a mesma página para evitar reenvio do formulário
    header("Location: index.php");
    exit();
}
// --- Consultas para os Cards ---

// 1. Total Pendente (valor total de contas com status 'Pendente')
$stmtPendente = $pdo->prepare("SELECT SUM(total_a_pagar) as total FROM semparar WHERE status = 'Pendente'");
$stmtPendente->execute();
$totalMesPendente = $stmtPendente->fetchColumn() ?? 0;

// 2. Total de Contas Atrasadas (contagem de contas com vencimento passado e status 'pendente')
$stmtAtrasadas = $pdo->prepare("SELECT COUNT(*) AS total_atrasadas FROM semparar WHERE data_vencimento < CURDATE() AND status = 'pendente'");
$stmtAtrasadas->execute();
$totalContasAtrasadas = $stmtAtrasadas->fetchColumn() ?? 0;

// 3. Total Pago no Mês
$stmtPagoMes = $pdo->prepare("SELECT SUM(total_a_pagar) as total FROM semparar WHERE status = 'Pago' AND MONTH(data_vencimento) = MONTH(CURRENT_DATE()) AND YEAR(data_vencimento) = YEAR(CURRENT_DATE())");
$stmtPagoMes->execute();
$totalPagoNoMes = $stmtPagoMes->fetchColumn() ?? 0;

// --- Consulta para a Tabela de Registros ---
$stmtRegistros = $pdo->prepare("SELECT id, data_vencimento, total_a_pagar, unidade, status FROM semparar ORDER BY data_vencimento DESC");
$stmtRegistros->execute();
$registros_semparar = $stmtRegistros->fetchAll();

?>
<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-purple-500 border-b-2 border-gray-300">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Total do Mês (Pendente)</h3>
            <p class="text-2xl font-bold text-purple-600">R$ <?php echo number_format($totalMesPendente ?? 0, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-red-500">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Total de Contas Atrasadas</h3>
            <p class="text-2xl font-bold text-red-600"><?php echo $totalContasAtrasadas; ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-indigo-500 border-b-2 border-gray-300">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Total Anual (Pendente)</h3>
            <p class="text-2xl font-bold text-indigo-600">R$ <?php echo number_format($totalAnual ?? 0, 2, ',', '.'); ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Registros de Sem Parar</h2>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unidade</th>                     
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($registros_semparar as $registro): ?>
                        <tr id="row-<?= htmlspecialchars($registro['id']) ?>">
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars(date('d/m/Y', strtotime($registro['data_vencimento']))); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($registro['unidade'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo htmlspecialchars(number_format($registro['total_a_pagar'] ?? 0, 2, ',', '.')); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_status_fatura">
                                    <input type="hidden" name="conta_id" value="<?= $registro['id'] ?>">
                                   <select name="status" onchange="this.form.submit()" class="block w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="pendente" <?= ($registro['status'] === 'pendente') ? 'selected' : '' ?>>Pendente</option>
                                        <option value="pago" <?= ($registro['status'] === 'pago') ? 'selected' : '' ?>>Pago</option>
                                        <option value="atrasado" <?= ($registro['status'] === 'atrasado') ? 'selected' : '' ?>>Atrasado</option>
                                        <option value="cancelado" <?= ($registro['status'] === 'cancelado') ? 'selected' : '' ?>>Cancelado</option>
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="alert('Funcionalidade de edição a ser implementada.')" class="text-indigo-600 hover:text-indigo-900 mr-2">Editar</button>
                                <button onclick="alert('Funcionalidade de exclusão a ser implementada.')" class="text-red-600 hover:text-red-900">Excluir</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Componente de Paginação -->
<?php require_once __DIR__ . '/../components/pagination_component.php'; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/template.php';
?>
