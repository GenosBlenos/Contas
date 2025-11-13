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
    $total_a_pagar = $_POST['total_a_pagar'] ?? null;
    $status = $_POST['status'] ?? 'Pendente';

    if ($vencimento && $total_a_pagar) {
        try {
            $stmt = $pdo->prepare("INSERT INTO energia (vencimento, total_a_pagar, status) VALUES (?, ?, ?)");
            $stmt->execute([$vencimento, $total_a_pagar, $status]);
            $_SESSION['success'] = "Conta de energia cadastrada com sucesso!";
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
$stmtPendente = $pdo->prepare("SELECT SUM(total_a_pagar) as total FROM energia WHERE status = 'Pendente'");
$stmtPendente->execute();
$totalMesPendente = $stmtPendente->fetchColumn() ?? 0;

// 2. Total de Multa por Atraso Somada
$stmtMultaAtraso = $pdo->prepare("SELECT SUM(multa_atraso) as total_multa FROM energia WHERE multa_atraso IS NOT NULL AND multa_atraso > 0");
$stmtMultaAtraso->execute();
$totalMultaAtraso = $stmtMultaAtraso->fetchColumn() ?? 0;

// 3. Total Pago no Mês
$stmtPagoMes = $pdo->prepare("SELECT SUM(total_a_pagar) as total FROM energia WHERE status = 'Pago' AND MONTH(vencimento) = MONTH(CURRENT_DATE()) AND YEAR(vencimento) = YEAR(CURRENT_DATE())");
$stmtPagoMes->execute();
$totalPagoNoMes = $stmtPagoMes->fetchColumn() ?? 0;

// 4. Média de Consumo (kWh)
$stmtMediaConsumo = $pdo->prepare("SELECT AVG(consumo_kwh) as media FROM energia WHERE consumo_kwh IS NOT NULL AND consumo_kwh > 0");
$stmtMediaConsumo->execute();
$mediaConsumo = $stmtMediaConsumo->fetchColumn() ?? 0;

// --- Consulta para a Tabela de Registros ---
$stmtRegistros = $pdo->prepare("SELECT id, vencimento, total_a_pagar, status, consumo_kwh, endereco_consumo, codigo_instalacao, multa_atraso, valor_final, fat_impostos, fat_distribuidora, imposto_retido_total, imposto_retido_irrf, unidade FROM energia ORDER BY vencimento DESC");
$stmtRegistros->execute();
$registros_energia = $stmtRegistros->fetchAll();

?>
<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-blue-500 border-b-2 border-gray-300">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Total Pendente</h3>
            <p class="text-2xl font-bold text-blue-600">R$ <?php echo number_format($totalMesPendente ?? 0, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-green-500 border-b-2 border-gray-300">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Média de Consumo</h3>
            <p class="text-2xl font-bold text-green-600"><?php echo number_format($mediaConsumo ?? 0, 2, ',', '.'); ?> kWh</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-red-500 border-b-2 border-gray-300">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Multa por Atraso Somada</h3>
            <p class="text-2xl font-bold text-red-600">R$ <?php echo number_format($totalMultaAtraso ?? 0, 2, ',', '.'); ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Registros de Energia</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endereço de Consumo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código Instalação</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unidade</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Inicial</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Multa por Atraso (R$)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Final</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Consumo (kWh)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fat. Impostos (R$)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fat. Distribuidora (R$)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imposto Retido: TOTAL (R$)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imposto Retido: ret. out. fornec irrf -1,2%</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($registros_energia as $registro): ?>
                        <tr id="row-<?= htmlspecialchars($registro['id']) ?>">
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($registro['endereco_consumo'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($registro['codigo_instalacao'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($registro['unidade'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars(date('d/m/Y', strtotime($registro['vencimento']))); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo htmlspecialchars(number_format($registro['total_a_pagar'] ?? 0, 2, ',', '.')); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo htmlspecialchars(number_format($registro['multa_atraso'] ?? 0, 2, ',', '.')); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo htmlspecialchars(number_format($registro['valor_final'] ?? 0, 2, ',', '.')); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars(number_format($registro['consumo_kwh'] ?? 0, 2, ',', '.')); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo htmlspecialchars(number_format($registro['fat_impostos'] ?? 0, 2, ',', '.')); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo htmlspecialchars(number_format($registro['fat_distribuidora'] ?? 0, 2, ',', '.')); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo htmlspecialchars(number_format($registro['imposto_retido_total'] ?? 0, 2, ',', '.')); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo htmlspecialchars(number_format($registro['imposto_retido_irrf'] ?? 0, 2, ',', '.')); ?></td>
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
                                <a href="fatura_form.php?module=energia&id=<?= $registro['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-2">Editar</a>
                                <form action="energia.php" method="POST" class="inline-block">
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const valorFaturaInput = document.getElementById('total_a_pagar');
    const dataVencimentoInput = document.getElementById('vencimento');
    const dataPagamentoInput = document.getElementById('data_pagamento');
    const multaAtrasoInput = document.getElementById('multa_atraso');
    const fatImpostosInput = document.getElementById('fat_impostos');
    const impostoRetidoTotalInput = document.getElementById('imposto_retido_total');
    const impostoRetidoIrrfInput = document.getElementById('imposto_retido_irrf');
    const valorFinalInput = document.getElementById('valor_final');

    function calcularMultaAtraso() {
        const valorFatura = parseFloat(valorFaturaInput.value) || 0;
        const dataVencimento = dataVencimentoInput.value;
        const dataPagamento = dataPagamentoInput.value;

        if (valorFatura > 0 && dataVencimento && dataPagamento) {
            const vencimento = new Date(dataVencimento + 'T00:00:00');
            const pagamento = new Date(dataPagamento + 'T00:00:00');

            if (pagamento > vencimento) {
                const diffTime = Math.abs(pagamento - vencimento);
                const diasAtraso = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                const multa = valorFatura * 0.02;
                const juros = valorFatura * 0.00033 * diasAtraso;
                const multaTotal = multa + juros;

                multaAtrasoInput.value = multaTotal.toFixed(2);
            } else {
                multaAtrasoInput.value = '0.00';
            }
        } else {
            multaAtrasoInput.value = '0.00';
        }
        calcularValorFinal();
    }

    function calcularValorFinal() {
        const valorFatura = parseFloat(valorFaturaInput.value) || 0;
        const multaAtraso = parseFloat(multaAtrasoInput.value) || 0;
        const valorFinal = valorFatura + multaAtraso;
        valorFinalInput.value = valorFinal.toFixed(2);
    }

    [valorFaturaInput, dataVencimentoInput, dataPagamentoInput].forEach(input => input.addEventListener('input', calcularMultaAtraso));
    [fatImpostosInput, impostoRetidoTotalInput, impostoRetidoIrrfInput].forEach(input => input.addEventListener('input', calcularValorFinal));
});
</script>

<!-- Componente de Paginação -->
<?php require_once __DIR__ . '/../components/pagination_component.php'; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/template.php';
?>