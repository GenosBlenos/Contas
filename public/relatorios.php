<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/header.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/login.php');
    exit;
}
require_once __DIR__ . '/../app/conexao.php';

$pageTitle = 'Relatórios Consolidados';

// Fetch Unidades for the filter dropdown
$unidades = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM unidades ORDER BY nome");
    $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar unidades: " . $e->getMessage());
    // Handle error appropriately
}

$selectedModule = $_GET['module'] ?? 'agua'; // Default to 'agua'
$selectedUnidade = $_GET['unidade'] ?? ($unidades[0]['nome'] ?? '');

ob_start();
?>

<div class="space-y-6">
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold text-gray-800 mb-4"><?= htmlspecialchars($pageTitle) ?></h2>

        <!-- Filter Form -->
        <form id="report-filter-form" action="" method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 items-end">
            <div>
                <label for="module" class="block text-sm font-medium text-gray-700">Módulo</label>
                <select name="module" id="module" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="agua" <?= ($selectedModule === 'agua') ? 'selected' : '' ?>>Água</option>
                    <option value="energia" <?= ($selectedModule === 'energia') ? 'selected' : '' ?>>Energia</option>
                    <option value="telefone" <?= ($selectedModule === 'telefone') ? 'selected' : '' ?>>Telefone</option>
                    <option value="semparar" <?= ($selectedModule === 'semparar') ? 'selected' : '' ?>>Sem Parar</option>
                </select>
            </div>
           <div>
                <label for="unidade_id" class="block text-sm font-medium text-gray-700">Unidade</label>
                <select name="unidade" id="unidade" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <?php foreach ($unidades as $unidade) : ?>
                        <option value="<?= $unidade['id'] ?>" <?= ($selectedUnidade == $unidade['id']) ? 'selected' : '' ?>><?= htmlspecialchars($unidade['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end space-x-2">
                <button type="button" id="export-xlsx-btn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md shadow-sm">
                    Gerar XLSX
                </button>
                 <button type="button" id="view-report-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-sm">
                    Ver Relatório Detalhado
                </button>
            </div>
        </form>

        <!-- Summary Cards -->
        <div id="summary-cards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Summary cards will be populated by JS -->
        </div>
        
        <!-- Detailed Report Table -->
        <div id="report-table-container" class="hidden mt-6">
            <table id="report-table" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
   const moduleSelect = document.getElementById('module');
    const unidadeSelect = document.getElementById('unidade');
    const exportCsvBtn = document.getElementById('export-csv-btn');
    const viewReportBtn = document.getElementById('view-report-btn');
    const summaryCardsContainer = document.getElementById('summary-cards');
    const tableContainer = document.getElementById('report-table-container');
    const reportTable = $('#report-table');
    let dataTable = null;

    function formatCurrency(value) {
        if (value === null || isNaN(value)) return 'R$ 0,00';
        return parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function fetchSummaryData() {
        const module = moduleSelect.value;
        const unidadeId = unidadeSelect.value;

        if (!unidadeId) {
            summaryCardsContainer.innerHTML = '<p>Selecione uma unidade</p>';
            return;
        }

        const url = `ajax_get_relatorio.php?type=summary&module=${module}&unidade_id=${unidadeId}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    summaryCardsContainer.innerHTML = `<p class="text-red-500">${data.error}</p>`;
                    return;
                }
                
                let cardsHtml = `
                    <div class="bg-gray-100 p-4 rounded-lg shadow"><h3 class="text-sm font-medium text-gray-500">Total de Registros</h3><p class="text-2xl font-bold">${data.total_registros || 0}</p></div>
                    <div class="bg-gray-100 p-4 rounded-lg shadow"><h3 class="text-sm font-medium text-gray-500">Contas Atrasadas/Pendentes</h3><p class="text-2xl font-bold">${data.contas_atrasadas || data.contas_pendentes || 0}</p></div>
                    <div class="bg-gray-100 p-4 rounded-lg shadow"><h3 class="text-sm font-medium text-gray-500">Valor Total</h3><p class="text-2xl font-bold">${formatCurrency(data.valor_total)}</p></div>
                `;

                let mediaLabel = 'Média/Outros';
                let mediaValue = '-';

                 if (module === 'agua' && data.media_consumo) {
                    mediaLabel = 'Média Consumo (m³)';
                    mediaValue = parseFloat(data.media_consumo).toFixed(2);
                } else if (module === 'energia' && data.media_consumo) {
                    mediaLabel = 'Média Consumo (kWh)';
                    mediaValue = parseFloat(data.media_consumo).toFixed(2);
                } else if (module === 'telefone' && data.total_servicos) {
                    mediaLabel = 'Total Serviços';
                    mediaValue = formatCurrency(data.total_servicos);
                }

                cardsHtml += `<div class="bg-gray-100 p-4 rounded-lg shadow"><h3 class="text-sm font-medium text-gray-500">${mediaLabel}</h3><p class="text-2xl font-bold">${mediaValue}</p></div>`;
                summaryCardsContainer.innerHTML = cardsHtml;
            })
    }

    function fetchFullReportData() {
        const module = moduleSelect.value;
        const url = `ajax_get_relatorio.php?type=full&module=${module}`;

        if (dataTable) {
            dataTable.destroy();
        }

        tableContainer.classList.remove('hidden');
        summaryCardsContainer.classList.add('hidden');

        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                if(data.length === 0) {
                    alert("Nenhum dado encontrado para o módulo selecionado.");
                    tableContainer.classList.add('hidden');
                    summaryCardsContainer.classList.remove('hidden');
                    return;
                }

                const columns = Object.keys(data[0]).map(key => ({ title: key, data: key }));
                
                dataTable = reportTable.DataTable({
                    data: data,
                    columns: columns,
                    responsive: true,
                    language: { 
                        url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
                    }
                });
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error);
                alert("Erro ao carregar os dados do relatório.");
            }
        });
    }

    function exportToXlsx() {
        const module = moduleSelect.value;
        const url = `gerar_relatorio.php?module=${module}`;
        window.location.href = url;
    }

    // Initial data fetch
    fetchSummaryData();

    // Event listeners
    moduleSelect.addEventListener('change', fetchSummaryData);
   unidadeSelect.addEventListener('change', fetchSummaryData);
    document.getElementById('export-xlsx-btn').addEventListener('click', exportToXlsx);
    viewReportBtn.addEventListener('click', function() {
        if (tableContainer.classList.contains('hidden')) {
            fetchFullReportData();
            viewReportBtn.textContent = 'Ver Resumo';
        } else {
            tableContainer.classList.add('hidden');
            summaryCardsContainer.classList.remove('hidden');
            viewReportBtn.textContent = 'Ver Relatório Detalhado';
            if (dataTable) {
                dataTable.destroy();
                reportTable.empty(); // Clear the table content
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../src/includes/template.php';
?>
