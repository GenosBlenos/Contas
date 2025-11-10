<?php
// Inclui o arquivo de conexão com o banco de dados.
// A variável $pdo estará disponível para uso.
require_once 'app/conexao.php';
require_once 'src/includes/faturas_helper.php';

/**
 * Função para formatar valores.
 *
 * @param float|null $value O valor a ser formatado.
 * @param string $type O tipo de formatação (currency, volume, kwh, integer).
 * @return string O valor formatado.
 */
function formatValue($value, $type = 'currency') {
    // Se o valor for nulo ou não numérico, retorna um valor padrão.
    if (!is_numeric($value)) {
        switch ($type) {
            case 'currency':
                return 'R$ 0,00';
            case 'volume':
                return '0,00 m³';
            case 'kwh':
                return '0,00 kWh';
            case 'integer':
                return '0';
            default:
                return '0,00';
        }
    }

    // Formata o número com 2 casas decimais, usando vírgula como separador decimal.
    $formatted = number_format($value, 2, ',', '.');

    // Adiciona o prefixo ou sufixo conforme o tipo.
    switch ($type) {
        case 'currency':
            return 'R$ ' . $formatted;
        case 'volume':
            return $formatted . ' m³';
        case 'kwh':
            return $formatted . ' kWh';
        case 'integer':
            return number_format($value, 0, ',', '.'); // Sem casas decimais para contagens.
        default:
            return $formatted;
    }
}

// --- QUERIES PARA OS MÓDULOS ---

// Inicializa um array para armazenar os resultados.
$data = [];

$categorias = ['energia', 'agua', 'semparar', 'telefone'];

foreach ($categorias as $categoria) {
    $faturas = buscarFaturas($pdo, ['categoria' => $categoria]);
    $data[$categoria] = calcularTotaisFaturas($faturas, $categoria);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Contas</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .dashboard-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        .module {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .module-title {
            font-size: 1.5em;
            font-weight: 600;
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .card {
            border-left: 5px solid;
            padding: 15px 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .card-title {
            font-size: 0.9em;
            font-weight: 600;
            color: #555;
            margin: 0 0 5px 0;
        }
        .card-value {
            font-size: 1.8em;
            font-weight: 700;
            color: #222;
        }
        /* Cores dos módulos */
        .module-energia .module-title { border-color: #f39c12; }
        .card-energia { border-color: #f39c12; }

        .module-agua .module-title { border-color: #3498db; }
        .card-agua { border-color: #3498db; }

        .module-semparar .module-title { border-color: #9b59b6; }
        .card-semparar { border-color: #9b59b6; }

        .module-telefone .module-title { border-color: #2ecc71; }
        .card-telefone { border-color: #2ecc71; }
    </style>
</head>
<body>

    <div class="dashboard-container">

        <!-- MÓDULO ENERGIA -->
        <div class="module module-energia">
            <h2 class="module-title">⚡ Energia</h2>
            <div class="cards-container">
                <div class="card card-energia">
                    <h3 class="card-title">Total Pendente</h3>
                    <p class="card-value"><?php echo formatValue($data['energia']['totalPendente'], 'currency'); ?></p>
                </div>
                <div class="card card-energia">
                    <h3 class="card-title">Média de Consumo</h3>
                    <p class="card-value"><?php echo formatValue($data['energia']['mediaConsumo'], 'kwh'); ?></p>
                </div>
                <div class="card card-energia">
                    <h3 class="card-title">Contas Atrasadas</h3>
                    <p class="card-value"><?php echo formatValue($data['energia']['contasAtrasadas'], 'integer'); ?></p>
                </div>
            </div>
        </div>

        <!-- MÓDULO ÁGUA -->
        <div class="module module-agua">
            <h2 class="module-title">💧 Água</h2>
            <div class="cards-container">
                <div class="card card-agua">
                    <h3 class="card-title">Total Pendente</h3>
                    <p class="card-value"><?php echo formatValue($data['agua']['totalPendente'], 'currency'); ?></p>
                </div>
                <div class="card card-agua">
                    <h3 class="card-title">Média de Consumo</h3>
                    <p class="card-value"><?php echo formatValue($data['agua']['mediaConsumo'], 'volume'); ?></p>
                </div>
                <div class="card card-agua">
                    <h3 class="card-title">Contas Atrasadas</h3>
                    <p class="card-value"><?php echo formatValue($data['agua']['contasAtrasadas'], 'integer'); ?></p>
                </div>
            </div>
        </div>

        <!-- MÓDULO SEM PARAR -->
        <div class="module module-semparar">
            <h2 class="module-title">🚗 Sem Parar</h2>
            <div class="cards-container">
                <div class="card card-semparar">
                    <h3 class="card-title">Total do Mês (Pendente)</h3>
                    <p class="card-value"><?php echo formatValue($data['semparar']['totalMesPendente'], 'currency'); ?></p>
                </div>
                <div class="card card-semparar">
                    <h3 class="card-title">Faturas no Mês (Pendente)</h3>
                    <p class="card-value"><?php echo formatValue($data['semparar']['faturasNoMes'], 'integer'); ?></p>
                </div>
                <div class="card card-semparar">
                    <h3 class="card-title">Total Anual (Pendente)</h3>
                    <p class="card-value"><?php echo formatValue($data['semparar']['totalAnual'], 'currency'); ?></p>
                </div>
            </div>
        </div>

        <!-- MÓDULO TELEFONE -->
        <div class="module module-telefone">
            <h2 class="module-title">📞 Telefone</h2>
            <div class="cards-container">
                <div class="card card-telefone">
                    <h3 class="card-title">Total de Contas Atrasadas</h3>
                    <p class="card-value"><?php echo formatValue($data['telefone']['contasAtrasadas'], 'integer'); ?></p>
                </div>
                <div class="card card-telefone">
                    <h3 class="card-title">Média de Consumo</h3>
                    <p class="card-value"><?php echo formatValue($data['telefone']['mediaConsumo'], 'currency'); ?></p>
                </div>
                <div class="card card-telefone">
                    <h3 class="card-title">Total do Mês (Pendente)</h3>
                    <p class="card-value"><?php echo formatValue($data['telefone']['totalMesPendente'], 'currency'); ?></p>
                </div>
            </div>
        </div>

        <!-- MÓDULO UNIDADES -->
        <?php include 'src/views/unidades/dashboard_card.php'; ?>

    </div>

</body>
</html>
