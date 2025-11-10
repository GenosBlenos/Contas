<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Logger.php';

$logger = Logger::getInstance();

try {
    $pdo = Database::getInstance();
    $logger->info('Iniciando atualização de multas para faturas pendentes.');

    // Obter a taxa Selic diária da API do Banco Central
    $selic_url = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.11/dados/ultimos/1?formato=json';
    $selic_data = file_get_contents($selic_url);
    if ($selic_data === false) {
        throw new Exception('Não foi possível obter a taxa Selic da API do Banco Central.');
    }

    $selic_json = json_decode($selic_data, true);
    $taxa_selic_anual = $selic_json[0]['valor'] ?? 0;
    if ($taxa_selic_anual == 0) {
        throw new Exception('Valor da taxa Selic não encontrado na resposta da API.');
    }
    $taxa_selic_diaria = $taxa_selic_anual / 100 / 365; // Convertendo para taxa diária

    // Atualização para faturas de energia (CPFL)
    $sql_energia = "
        UPDATE energia
        SET
            multa_atraso = (valor_fatura * 0.02) + (valor_fatura * :taxa_selic_diaria * DATEDIFF(CURDATE(), data_vencimento)),
            valor_final = valor_fatura + (valor_fatura * 0.02) + (valor_fatura * :taxa_selic_diaria * DATEDIFF(CURDATE(), data_vencimento))
        WHERE
            status = 'pendente' AND data_vencimento < CURDATE() AND (data_pagamento IS NULL OR data_pagamento = '0000-00-00');
    ";

    $stmt_energia = $pdo->prepare($sql_energia);
    $stmt_energia->bindParam(':taxa_selic_diaria', $taxa_selic_diaria, PDO::PARAM_STR);
    $stmt_energia->execute();
    $rowCount_energia = $stmt_energia->rowCount();
    $logger->info("Atualização de multas de energia concluída. Registros afetados: {$rowCount_energia}");

    // Atualização para faturas de água (SAAE)
    $sql_agua = "
        UPDATE agua
        SET
            multa = (valor_fatura * 0.01 / 30 * DATEDIFF(CURDATE(), vencimento)),
            total = valor_fatura + (valor_fatura * 0.01 / 30 * DATEDIFF(CURDATE(), vencimento))
        WHERE
            status = 'pendente' AND vencimento < CURDATE() AND (data_pagamento IS NULL OR data_pagamento = '0000-00-00');
    ";

    $stmt_agua = $pdo->prepare($sql_agua);
    $stmt_agua->execute();
    $rowCount_agua = $stmt_agua->rowCount();
    $logger->info("Atualização de multas de água concluída. Registros afetados: {$rowCount_agua}");

    echo "Atualização de multas concluída. Energia: {$rowCount_energia} registros. Água: {$rowCount_agua} registros.\n";

} catch (Exception $e) {
    $logger->error('Erro ao conectar ou executar a atualização de multas: ' . $e->getMessage());
    die('Erro ao conectar ou executar a atualização: ' . $e->getMessage());
}