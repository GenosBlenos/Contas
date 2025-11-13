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
    $vencimento = $_POST['vencimento'] ?? null;
    $numero_ligacao = $_POST['numero_ligacao'] ?? null;
    $endereco_ligacao = $_POST['endereco_ligacao'] ?? null;
    $total_a_pagar = $_POST['total_a_pagar'] ?? null;
    $status = $_POST['status'] ?? 'Pendente';

    if ($vencimento && $total_a_pagar) {
        try {
            $stmt = $pdo->prepare("INSERT INTO agua (vencimento, total_a_pagar, status, numero_ligacao, endereco_ligacao) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$vencimento, $total_a_pagar, $status, $numero_ligacao, $endereco_ligacao]);
            $_SESSION['success'] = "Conta de agua cadastrada com sucesso!";
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
$stmtPendente = $pdo->prepare("SELECT SUM(total_a_pagar) as total FROM agua WHERE status = 'Pendente'");
$stmtPendente->execute();
$totalMesPendente = $stmtPendente->fetchColumn() ?? 0;

// 2. Total de Contas Atrasadas (contagem de contas com vencimento passado e status 'pendente')
$stmtAtrasadas = $pdo->prepare("SELECT COUNT(*) AS total_atrasadas FROM agua WHERE vencimento < CURDATE() AND status = 'pendente'");
$stmtAtrasadas->execute();
$totalContasAtrasadas = $stmtAtrasadas->fetchColumn() ?? 0;

// 3. Total Pago no Mês
$stmtPagoMes = $pdo->prepare("SELECT SUM(total_a_pagar) as total FROM agua WHERE status = 'Pago' AND MONTH(vencimento) = MONTH(CURRENT_DATE()) AND YEAR(vencimento) = YEAR(CURRENT_DATE())");
$stmtPagoMes->execute();
$totalPagoNoMes = $stmtPagoMes->fetchColumn() ?? 0;

// 4. Média de Consumo (m³)
$stmtMediaConsumo = $pdo->prepare("SELECT AVG(consumo_m3) as media FROM agua WHERE consumo_m3 IS NOT NULL AND consumo_m3 > 0");
$stmtMediaConsumo->execute();
$mediaConsumo = $stmtMediaConsumo->fetchColumn() ?? 0;

// 5. Contas Atrasadas
$stmtAtrasadas = $pdo->prepare("SELECT COUNT(*) FROM agua WHERE vencimento < CURDATE() AND status = 'pendente'");
$stmtAtrasadas->execute();
$contasAtrasadas = $stmtAtrasadas->fetchColumn() ?? 0;
 
// --- Consulta para a Tabela de Registros ---
$stmtRegistros = $pdo->prepare("
    SELECT a.id, a.unidade, a.vencimento, a.total_a_pagar, a.status, a.consumo_m3, a.numero_ligacao, a.endereco_ligacao
    FROM agua a
    ORDER BY a.vencimento DESC
");
$stmtRegistros->execute();
$registros_agua = $stmtRegistros->fetchAll();

?>
<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-blue-500 border-b-2 border-gray-300">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Total Pendente</h3>
            <p class="text-2xl font-bold text-blue-600">R$ <?php echo number_format($totalMesPendente ?? 0, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-green-500 border-b-2 border-gray-300">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Média de Consumo</h3>
            <p class="text-2xl font-bold text-green-600"><?php echo number_format($mediaConsumo ?? 0, 2, ',', '.'); ?> m³</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-red-500 border-b-2 border-gray-300">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Contas Atrasadas</h3>
            <p class="text-2xl font-bold text-red-600"><?php echo $contasAtrasadas ?? 0; ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Registros de Água</h2>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nº Ligação</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unidade</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endereço de Ligação</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Consumo (m³)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($registros_agua as $registro): ?>
                        <tr id="row-<?= htmlspecialchars($registro['id']) ?>">
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($registro['numero_ligacao'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($registro['unidade'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($registro['endereco_ligacao'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars(date('d/m/Y', strtotime($registro['vencimento']))); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo htmlspecialchars(number_format($registro['total_a_pagar'] ?? 0, 2, ',', '.')); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars(number_format($registro['consumo_m3'] ?? 0, 2, ',', '.')); ?></td>
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
                                <a href="fatura_form.php?module=agua&id=<?= $registro['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-2">Editar</a>
                                <form action="agua.php" method="POST" class="inline-block">
                                    <input type="hidden" name="action" value="destroy">
                                    <input type="hidden" name="id" value="<?= $registro['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(gerarCSRFToken()); ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Tem certeza que deseja excluir este registro?')">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Componente de Paginação -->
    <?php require_once __DIR__ . '/../components/pagination_component.php'; ?>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/template.php';
?>
