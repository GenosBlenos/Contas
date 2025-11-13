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
$selectedUnidade = $_GET['unidade'] ?? 'all';

ob_start();
?>

<div class="space-y-6">
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold text-gray-800 mb-4"><?= htmlspecialchars($pageTitle) ?></h2>

        <!-- Filter Form -->
        <form id="report-filter-form" action="" method="get"
            class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 items-end">
            <div>
                <label for="module" class="block text-sm font-medium text-gray-700">Módulo</label>
                <select name="module" id="module"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="agua" <?= ($selectedModule === 'agua') ? 'selected' : '' ?>>Água</option>
                    <option value="energia" <?= ($selectedModule === 'energia') ? 'selected' : '' ?>>Energia</option>
                    <option value="telefone" <?= ($selectedModule === 'telefone') ? 'selected' : '' ?>>Telefone</option>
                    <option value="semparar" <?= ($selectedModule === 'semparar') ? 'selected' : '' ?>>Sem Parar</option>
                </select>
            </div>
            <div>
                <label for="unidade_id" class="block text-sm font-medium text-gray-700">Unidade</label>
                <select name="unidade" id="unidade"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="all" <?= ($selectedUnidade === 'all') ? 'selected' : '' ?>>Todas as Unidades</option>
                    <?php foreach ($unidades as $unidade): ?>
                        <option value="<?= $unidade['id'] ?>" <?= ($selectedUnidade == $unidade['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($unidade['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end space-x-2">
                <button type="button" id="export-xlsx-btn"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md shadow-sm">
                    Gerar XLSX
                </button>
                <button type="button" id="view-report-btn"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-sm">
                    Ver Relatório Detalhado
                </button>
            </div>
        </form>

        <!-- Summary Cards -->
        <div id="summary-cards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Summary cards will be populated by JS -->
        </div>

        <!-- Detailed Report Table -->
        <div id="report-table-container" class="hidden mt-6 max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
            <table id="report-table" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0"></thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- Print Button (hidden until a report is displayed) -->
        <div>
            <button type="button" id="print-report-btn" style="display:none;"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-sm">
                Imprimir Relatório
            </button>
        </div>
    </div>
</div>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const moduleSelect = document.getElementById('module');
        const unidadeSelect = document.getElementById('unidade');
        const exportCsvBtn = document.getElementById('export-csv-btn');
        const viewReportBtn = document.getElementById('view-report-btn');
        const summaryCardsContainer = document.getElementById('summary-cards');
        const tableContainer = document.getElementById('report-table-container');
        const reportTable = $('#report-table');
        let dataTable = null;
        let fullReportData = null; // will hold the entire dataset for printing
        const printBtn = document.getElementById('print-report-btn');

        function formatCurrency(value) {
            if (value === null || isNaN(value)) return 'R$ 0,00';
            return parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        function formatDate(dateString) {
            if (!dateString) return dateString;
            // Verifica se é formato YYYY-MM-DD
            const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
            if (dateRegex.test(dateString)) {
                const [year, month, day] = dateString.split('-');
                return `${day}-${month}-${year}`;
            }
            return dateString;
        }

        function formatRowData(row) {
            // Itera por cada campo da linha e formata datas
            Object.keys(row).forEach(key => {
                if (row[key] && typeof row[key] === 'string') {
                    row[key] = formatDate(row[key]);
                }
            });
            return row;
        }

        function fetchSummaryData() {
            const module = moduleSelect.value;
            const unidadeId = unidadeSelect.value;

            // only require selection when value is empty string; 'all' is allowed
            if (unidadeId === '') {
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
            const unidadeId = unidadeSelect.value;
            const url = `ajax_get_relatorio.php?type=full&module=${module}&unidade_id=${unidadeId}`;

            if (dataTable) {
                dataTable.destroy();
            }

            tableContainer.classList.remove('hidden');
            summaryCardsContainer.classList.add('hidden');

            $.ajax({
                url: url,
                method: 'GET',
                dataType: 'json',
                success: function (data) {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    if (data.length === 0) {
                        alert("Nenhum dado encontrado para o módulo selecionado.");
                        tableContainer.classList.add('hidden');
                        summaryCardsContainer.classList.remove('hidden');
                        // hide print button when no data
                        if (printBtn) printBtn.style.display = 'none';
                        return;
                    }

                    // Formata todas as datas nos dados
                    data = data.map(row => formatRowData(row));

                    // store full dataset (formatted) for printing
                    fullReportData = data;
                    if (printBtn) printBtn.style.display = 'inline-block';

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
                error: function (xhr, status, error) {
                    console.error("AJAX error:", error);
                    alert("Erro ao carregar os dados do relatório.");
                }
            });
        }

        function exportToXlsx() {
            const module = moduleSelect.value;
            const unidadeId = unidadeSelect.value;
            const url = `gerar_relatorio.php?module=${module}&unidade_id=${unidadeId}`;
            window.location.href = url;
        }

        // Initial data fetch
        fetchSummaryData();

        // Event listeners
        moduleSelect.addEventListener('change', fetchSummaryData);
        unidadeSelect.addEventListener('change', fetchSummaryData);
        document.getElementById('export-xlsx-btn').addEventListener('click', exportToXlsx);
        viewReportBtn.addEventListener('click', function () {
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
                    // hide print button and clear stored data
                    if (printBtn) printBtn.style.display = 'none';
                    fullReportData = null;
                }
            }
        });
        // Print button: build and print full, non-paginated table using stored fullReportData
        if (printBtn) {
            printBtn.addEventListener('click', function () {
                if (!fullReportData || fullReportData.length === 0) {
                    alert('Nenhum relatório carregado para imprimir.');
                    return;
                }

                // Build printable HTML table
                const cols = Object.keys(fullReportData[0]);
                let tableHtml = '<table style="border-collapse:collapse;width:100%;">';
                tableHtml += '<thead><tr>' + cols.map(c => `<th style="border:1px solid #000;padding:6px;text-align:left;">${c}</th>`).join('') + '</tr></thead>';
                tableHtml += '<tbody>' + fullReportData.map(row => '<tr>' + cols.map(c => `<td style="border:1px solid #000;padding:6px;">${row[c] ?? ''}</td>`).join('') + '</tr>').join('') + '</tbody>';
                tableHtml += '</table>';

                                const moduleName = moduleSelect.options[moduleSelect.selectedIndex].text || moduleSelect.value;
                                const unidadeName = unidadeSelect.options[unidadeSelect.selectedIndex] ? unidadeSelect.options[unidadeSelect.selectedIndex].text : unidadeSelect.value;
                                const headerTitle = `Relatórios Consolidados - ${moduleName} - ${unidadeName}`;

                                

                                const win = window.open('', '', 'width=1000,height=800');
                                const head = `
                                    <meta charset="utf-8">
                                    <meta name="viewport" content="width=device-width,initial-scale=1">
                                    <title>${headerTitle}</title>
                                    <script src="https://cdn.tailwindcss.com"><\/script>
                                    <style>
                                        /* small print tweaks */
                                        table { page-break-inside: auto; border-collapse: collapse; width: 100%; }
                                        tr    { page-break-inside: avoid; page-break-after: auto; }
                                        th, td { border: 1px solid #000; padding: 6px; text-align: left; font-size:12px; }
                                        @media print { .no-print { display: none !important; } }
                                    </style>
                                `;

                                const body = `
                                    <main class="container mx-auto px-4 py-6">
                                        <h2 class="text-xl font-semibold text-gray-800 mb-4">${headerTitle}</h2>
                                        <div class="overflow-x-auto">${tableHtml}</div>
                                    </main>
                                `;

                                win.document.write(`<html><head>${head}</head><body>${body}</body></html>`);
                                win.document.close();
                                // wait a bit for Tailwind to apply before printing
                                setTimeout(() => { win.focus(); win.print(); }, 600);
                // optional: close window after print (some browsers block immediate close)
                // setTimeout(() => win.close(), 1000);
            });
        }
    });
        function imprimirDiv(divId) {
                const conteudo = document.getElementById(divId).innerHTML;
                const moduleName = document.getElementById('module') ? document.getElementById('module').options[document.getElementById('module').selectedIndex].text : document.getElementById('module').value;
                const unidadeEl = document.getElementById('unidade');
                const unidadeName = unidadeEl && unidadeEl.options[unidadeEl.selectedIndex] ? unidadeEl.options[unidadeEl.selectedIndex].text : (unidadeEl ? unidadeEl.value : '');
                const headerTitle = `Relatórios Consolidados - ${moduleName} - ${unidadeName}`;
                const pageHeader = document.querySelector('header') ? document.querySelector('header').outerHTML : '';
                const janela = window.open('', '', 'width=1000,height=800');
                const head = `
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width,initial-scale=1">
                    <title>${headerTitle}</title>
                    <script src="https://cdn.tailwindcss.com"><\/script>
                    <style>
                        table { page-break-inside: auto; border-collapse: collapse; width: 100%; }
                        tr    { page-break-inside: avoid; page-break-after: auto; }
                        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                        @media print { .no-print { display: none !important; } }
                    </style>
                `;
                const body = `${pageHeader}<main class="container mx-auto px-4 py-6"><h2 class="text-xl font-semibold text-gray-800 mb-4">${headerTitle}</h2>${conteudo}</main>`;
                janela.document.write(`<html><head>${head}</head><body>${body}</body></html>`);
                janela.document.close();
                setTimeout(() => { janela.focus(); janela.print(); }, 600);
        }

</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../src/includes/template.php';
?>