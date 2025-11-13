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
            $stmt = $pdo->prepare("INSERT INTO telefone (vencimento, total_a_pagar, status) VALUES (?, ?, ?)");
            $stmt->execute([$vencimento, $total_a_pagar, $status]);
            $_SESSION['success'] = "Conta de telefone cadastrada com sucesso!";
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
$stmtPendente = $pdo->prepare("SELECT SUM(total_a_pagar) as total FROM telefone WHERE status = 'Pendente'");
$stmtPendente->execute();
$totalMesPendente = $stmtPendente->fetchColumn() ?? 0;

// 2. Total de Contas Atrasadas (contagem de contas com vencimento passado e status 'pendente')
$stmtAtrasadas = $pdo->prepare("SELECT COUNT(*) AS total_atrasadas FROM telefone WHERE vencimento < CURDATE() AND status = 'pendente'");
$stmtAtrasadas->execute();
$totalContasAtrasadas = $stmtAtrasadas->fetchColumn() ?? 0;

// 3. Total Pago no Mês
$stmtPagoMes = $pdo->prepare("SELECT SUM(total_a_pagar) as total FROM telefone WHERE status = 'Pago' AND MONTH(vencimento) = MONTH(CURRENT_DATE()) AND YEAR(vencimento) = YEAR(CURRENT_DATE())");
$stmtPagoMes->execute();
$totalPagoNoMes = $stmtPagoMes->fetchColumn() ?? 0;

// --- Consulta para a Tabela de Registros ---
$stmtRegistros = $pdo->prepare("SELECT id, vencimento, total_a_pagar, unidade, status FROM telefone ORDER BY vencimento DESC");
$stmtRegistros->execute();
$registros_telefone = $stmtRegistros->fetchAll();

?>

<!-- Exibir mensagens de sucesso ou erro da sessão -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
        <?php echo htmlspecialchars($_SESSION['success']); ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
        <?php echo htmlspecialchars($_SESSION['error']); ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>


<div class="space-y-6">
    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-blue-500">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Total Pendente</h3>
            <p class="text-2xl font-bold text-blue-600">R$ <?php echo number_format($totalMesPendente, 2, ',', '.'); ?>
            </p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-red-500">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Total de Contas Atrasadas</h3>
            <p class="text-2xl font-bold text-red-600"><?php echo $totalContasAtrasadas; ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-green-500">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300 pb-2 mb-2">Total Pago no Mês</h3>
            <p class="text-2xl font-bold text-green-600">R$ <?php echo number_format($totalPagoNoMes, 2, ',', '.'); ?>
            </p>
        </div>
    </div>

    <!-- Tabela de Registros de Telefone -->
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Registros de Telefone</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unidade</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($registros_telefone)): ?>
                        <?php foreach ($registros_telefone as $registro): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars(date('d/m/Y', strtotime($registro['vencimento']))); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($registro['unidade'] ?? '-'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo htmlspecialchars(number_format($registro['total_a_pagar'], 2, ',', '.')); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($registro['status']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="#" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                   <a href="#" class="text-red-600 hover:text-red-900 ml-4">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                                        <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">Nenhum registro encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Componente de Paginação -->
<?php require_once __DIR__ . '/../components/pagination_component.php'; ?>

<?php
// Captura o conteúdo do buffer e o armazena na variável $content
$content = ob_get_clean();
// Inclui o template principal, que usará a variável $content
require_once __DIR__ . '/../../includes/template.php';
?>
