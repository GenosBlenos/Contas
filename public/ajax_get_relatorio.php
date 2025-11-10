<?php
require_once __DIR__ . '/../app/conexao.php';

header('Content-Type: application/json');

$module = $_GET['module'] ?? '';
$unidade = $_GET['unidade'] ?? null;
$type = $_GET['type'] ?? 'summary'; // summary or full

$sql = '';

if ($type === 'summary') {
    if (!$unidade_id) {
        echo json_encode(['error' => 'ID da unidade não fornecido.']);
        exit;
    }
    switch ($module) {
        case 'agua':
            $sql = "SELECT COUNT(*) AS total_registros, SUM(CASE WHEN status = 'pendente' AND vencimento < CURDATE() THEN 1 ELSE 0 END) AS contas_atrasadas, SUM(total_a_pagar) AS valor_total, AVG(consumo_m3) AS media_consumo FROM agua WHERE unidade_id = :unidade_id";
            break;
        case 'energia':
            $sql = "SELECT COUNT(*) AS total_registros, SUM(CASE WHEN status = 'pendente' AND vencimento < CURDATE() THEN 1 ELSE 0 END) AS contas_atrasadas, SUM(total_a_pagar) AS valor_total, AVG(consumo_kwh) AS media_consumo FROM energia WHERE unidade_id = :unidade_id";
            break;
        case 'telefone':
            $sql = "SELECT COUNT(*) AS total_registros, SUM(CASE WHEN status = 'pendente' AND vencimento < CURDATE() THEN 1 ELSE 0 END) AS contas_atrasadas, SUM(total_a_pagar) AS valor_total, SUM(valor_servico) AS total_servicos FROM telefone WHERE unidade_id = :unidade_id";
            break;
        case 'semparar':
            $sql = "SELECT COUNT(*) AS total_registros, SUM(CASE WHEN status = 'pendente' AND data_vencimento < CURDATE() THEN 1 ELSE 0 END) AS contas_atrasadas, SUM(total_a_pagar) AS valor_total FROM semparar WHERE unidade_id = :unidade_id";
            break;
        default:
            echo json_encode(['error' => 'Módulo inválido.']);
            exit;
    }
} else { // full report
    switch ($module) {
        case 'agua':
            $sql = "SELECT u.nome AS Unidade, a.id AS ID, a.numero_ligacao AS Numero_Ligacao, a.endereco_ligacao AS Endereco, a.referencia AS Referencia, a.emissao AS Data_Emissao, a.vencimento AS Data_Vencimento, a.consumo_m3 AS Consumo, a.total_a_pagar AS Valor_Total, a.status AS Status, a.arquivo_pdf AS Arquivo, a.unidade AS Unidade, NULL AS Observacoes FROM agua a LEFT JOIN unidades u ON a.unidade = u.nome";
            break;
        case 'energia':
            $sql = "SELECT u.nome AS Unidade, e.id AS ID, e.codigo_instalacao AS Codigo_Instalacao, e.conta_mes AS Referencia, e.vencimento AS Data_Vencimento, e.total_a_pagar AS Valor_Total, e.endereco_consumo AS Endereco, e.classificacao AS Classificacao, e.consumo_kwh AS Consumo, e.status AS Status, e.arquivo_pdf AS Arquivo, e.unidade AS Unidade, NULL AS Observacoes FROM energia e LEFT JOIN unidades u ON e.unidade = u.nome";
            break;
        case 'telefone':
            $sql = "SELECT u.nome AS Unidade, t.id AS ID, t.contrato AS Contrato, t.fatura AS Fatura, t.periodo_servico AS Referencia, t.vencimento AS Data_Vencimento, t.total_a_pagar AS Valor_Total, t.valor_servico AS Valor_Servico, t.status AS Status, t.arquivo_pdf AS Arquivo, t.unidade AS Unidade, NULL AS Observacoes FROM telefone t LEFT JOIN unidades u ON t.unidade = u.nome";
            break;
        case 'semparar':
            $sql = "SELECT u.nome AS Unidade, s.id AS ID, s.numero_fatura AS Numero_Fatura, s.codigo_cliente AS Codigo_Cliente, s.data_emissao AS Data_Emissao, s.data_vencimento AS Data_Vencimento, s.total_a_pagar AS Valor_Total, s.status AS Status, s.arquivo_pdf AS Arquivo, s.unidade AS Unidade, NULL AS Observacoes FROM semparar s LEFT JOIN unidades u ON s.unidade = u.nome";
            break;
        default:
            echo json_encode(['error' => 'Módulo inválido.']);
            exit;
    }
}

try {
    $stmt = $pdo->prepare($sql);
    if ($type === 'summary' && $unidade) {
        $stmt->bindValue(':unidade', $unidade, PDO::PARAM_STR);
    }
    $stmt->execute();
    $resultado = ($type === 'summary') ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($resultado ?: ($type === 'summary' ? [] : []), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
