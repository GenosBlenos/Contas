<?php

// Define o mapeamento de tabelas e colunas para os diferentes módulos
const FATURA_TABLE_MAPPING = [
    'agua' => [
        'table' => 'agua',
        'id_column' => 'id',
        'vencimento_column' => 'vencimento',
        'valor_column' => 'total_a_pagar',
        'status_column' => 'status',
        'unidade_id_column' => 'unidade_id'
    ],
    'energia' => [
        'table' => 'energia',
        'id_column' => 'id',
        'vencimento_column' => 'vencimento',
        'valor_column' => 'total_a_pagar',
        'status_column' => 'status',
        'unidade_id_column' => 'unidade_id'
    ],
    'telefone' => [
        'table' => 'telefone',
        'id_column' => 'id',
        'vencimento_column' => 'vencimento',
        'valor_column' => 'total_a_pagar',
        'status_column' => 'status',
        'unidade_id_column' => 'unidade_id'
    ],
    'internet' => [
        'table' => 'internet',
        'id_column' => 'id',
        'vencimento_column' => 'data_vencimento',
        'valor_column' => 'valor_total', // Corrigido para 'valor_total' conforme salva_fatura.php
        'status_column' => 'status',
        'unidade_id_column' => 'unidade_id'
    ],
    'semparar' => [
        'table' => 'semparar',
        'id_column' => 'id',
        'vencimento_column' => 'data_vencimento',
        'valor_column' => 'total_a_pagar',
        'status_column' => 'status',
        'unidade_id_column' => 'unidade_id'
    ],
    // Adicione outros módulos conforme necessário
];

function updateStatusConta(PDO $pdo, int $contaId, string $novoStatus, $module): bool
{
    // Extrair o módulo real se for um array
    $moduloReal = is_array($module) ? ($module['categoria'] ?? $module['modulo'] ?? reset($module)) : $module;
    
    $statusPermitidos = ['pendente', 'pago', 'atrasado', 'cancelado'];
    if (!in_array($novoStatus, $statusPermitidos)) {
        error_log("Tentativa de atualização com status inválido: " . $novoStatus);
        return false;
    }
    
    if (!isset(FATURA_TABLE_MAPPING[$moduloReal])) {
        error_log("Módulo inválido: " . $moduloReal);
        return false;
    }
    
    $mapping = FATURA_TABLE_MAPPING[$moduloReal];
    $tableName = $mapping['table'];
    $idColumn = $mapping['id_column'];
    $statusColumn = $mapping['status_column'];

    $sql = "UPDATE {$tableName} SET {$statusColumn} = :status WHERE {$idColumn} = :id";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':status' => $novoStatus, ':id' => $contaId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao atualizar status da conta: " . $e->getMessage());
        return false;
    }
}

// Alias para compatibilidade - usa a função existente updateStatusConta
function updateStatusFatura(PDO $pdo, int $faturaId, string $novoStatus, $module): bool
{
    return updateStatusConta($pdo, $faturaId, $novoStatus, $module);
}

function getContasFromDatabase($pdo, $moduleFiltro = 'todos', $unidadeFiltro = 0) {
    $queries = [];
    $baseParams = [];

    // Extrair o módulo real se for um array
    $moduloFiltroReal = is_array($moduleFiltro) ? ($moduleFiltro['categoria'] ?? $moduleFiltro['modulo'] ?? reset($moduleFiltro)) : $moduleFiltro;

    foreach (FATURA_TABLE_MAPPING as $module => $details) {
        if ($moduloFiltroReal !== 'todos' && $moduloFiltroReal !== $module) {
            continue;
        }
        $tableName = $details['table'];
        $vencimentoCol = $details['vencimento_column'];
        $valorCol = $details['valor_column'];
        $statusCol = $details['status_column'];
        $unidadeIdCol = $details['unidade_id_column'];

        $query = "SELECT {$details['id_column']} as id, {$vencimentoCol} as data_vencimento, {$valorCol} as valor, {$statusCol} as status, arquivo_pdf, observacao as observacoes, '{$module}' as modulo FROM {$tableName}";
        
        $whereClauses = [];
        $params = [];

        if ($unidadeFiltro > 0 && $unidadeIdCol) {
            $whereClauses[] = "{$unidadeIdCol} = ?";
            $params[] = $unidadeFiltro;
        }

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $queries[] = '(' . $query . ')';
        $baseParams = array_merge($baseParams, $params);
    }

    if (empty($queries)) {
        return [];
    }

    $sql = implode(" UNION ALL ", $queries);

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($baseParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar contas do banco de dados: " . $e->getMessage());
        return [];
    }
}

// Função para buscar faturas de um módulo específico com filtros
function buscarFaturas($pdo, $modulo, $filtros = []) {
    // Extrair o módulo real se for um array
    $moduloReal = is_array($modulo) ? ($modulo['categoria'] ?? $modulo['modulo'] ?? reset($modulo)) : $modulo;
    
    if (!isset(FATURA_TABLE_MAPPING[$moduloReal])) {
        error_log("Módulo inválido para buscar faturas: " . $moduloReal);
        return [];
    }

    // Obter mapeamento do módulo
    $mapping = FATURA_TABLE_MAPPING[$moduloReal];
    $tableName = $mapping['table'];
    $vencimentoColumn = $mapping['vencimento_column'];
    $statusColumn = $mapping['status_column'];

    // Mapeamento de colunas por módulo para filtros específicos
    $mapeamentoColunasInstalacao = [
        'agua' => 'numero_ligacao',
        'energia' => 'codigo_instalacao',
        'semparar' => 'codigo_cliente',
        'telefone' => 'contrato',
        'internet' => 'numero_nota_fiscal'
    ];

    $colunaInstalacao = $mapeamentoColunasInstalacao[$moduloReal] ?? null;

    // Construir a query base
    $sql = "SELECT * FROM {$tableName} WHERE 1=1";
    $params = [];

    // Filtro por instalação
    if (!empty($filtros['instalacao']) && $filtros['instalacao'] !== 'todas' && $colunaInstalacao) {
        $sql .= " AND {$colunaInstalacao} = ?";
        $params[] = $filtros['instalacao'];
    }

    // Filtro por mês/ano
    if (!empty($filtros['mes_ano']) && $filtros['mes_ano'] !== 'todos') {
        $sql .= " AND DATE_FORMAT({$vencimentoColumn}, '%Y-%m') = ?";
        $params[] = $filtros['mes_ano'];
    }

    // Filtro por status
    if (!empty($filtros['status']) && $filtros['status'] !== 'todos') {
        $sql .= " AND {$statusColumn} = ?";
        $params[] = $filtros['status'];
    }

    $sql .= " ORDER BY {$vencimentoColumn} DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar faturas: " . $e->getMessage());
        return [];
    }
}

// Função para calcular totais das faturas
function calcularTotaisFaturas($faturas, $modulo) {
    // Extrair o módulo real se for um array
    $moduloReal = is_array($modulo) ? ($modulo['categoria'] ?? $modulo['modulo'] ?? reset($modulo)) : $modulo;
    
    if (!isset(FATURA_TABLE_MAPPING[$moduloReal])) {
        error_log("Módulo inválido para calcular totais: " . $moduloReal);
        return [];
    }

    // Obter mapeamento do módulo
    $mapping = FATURA_TABLE_MAPPING[$moduloReal];
    $valorColumn = $mapping['valor_column'];
    $statusColumn = $mapping['status_column'];
    $vencimentoColumn = $mapping['vencimento_column'];
    
    $totais = [
        'total' => 0,
        'pago' => 0,
        'pendente' => 0,
        'atrasado' => 0,
        'quantidade_total' => 0,
        'quantidade_pago' => 0,
        'quantidade_pendente' => 0,
        'quantidade_atrasado' => 0
    ];

    $hoje = date('Y-m-d');

    foreach ($faturas as $fatura) {
        // Verificar se $fatura é um array antes de acessar
        if (!is_array($fatura)) {
            continue;
        }

        $valor = isset($fatura[$valorColumn]) ? (float)$fatura[$valorColumn] : 0;
        $status = $fatura[$statusColumn] ?? 'pendente';
        $dataVencimento = $fatura[$vencimentoColumn] ?? null;

        $totais['total'] += $valor;
        $totais['quantidade_total']++;

        if ($status === 'pago') {
            $totais['pago'] += $valor;
            $totais['quantidade_pago']++;
        } else {
            $totais['pendente'] += $valor;
            $totais['quantidade_pendente']++;

            // Verificar se está atrasado
            if ($dataVencimento && $dataVencimento < $hoje) {
                $totais['atrasado'] += $valor;
                $totais['quantidade_atrasado']++;
            }
        }
    }

    return $totais;
}

function calcularVariacaoMensal(array $contas): array
{
    if (empty($contas)) {
        return [];
    }
    
    $contasPorInstalacao = [];
    foreach ($contas as $conta) {
        // Verificar se $conta é um array antes de acessar
        if (!is_array($conta)) {
            continue;
        }
        
        $identificador = $conta['instalacao'] ?? ($conta['codigo_instalacao'] ?? ($conta['numero_ligacao'] ?? 'nao_identificado_' . md5(json_encode($conta))));
        $contasPorInstalacao[$identificador][] = $conta;
    }
    
    $contasComVariacao = [];
    foreach ($contasPorInstalacao as $grupoDeContas) {
        usort($grupoDeContas, function ($a, $b) {
            $dataA = $a['data_vencimento'] ?? 'now';
            $dataB = $b['data_vencimento'] ?? 'now'; // Assuming 'data_vencimento' is always present after fetching
            return strtotime($dataA) - strtotime($dataB);
        });
        
        $valorAnterior = null;
        foreach ($grupoDeContas as &$conta) {
            $valorAtual = isset($conta['valor']) ? (float)$conta['valor'] : 0;
            if ($valorAnterior !== null && $valorAnterior > 0) {
                $variacao = (($valorAtual - $valorAnterior) / $valorAnterior) * 100;
                $conta['variacao_mes_anterior'] = sprintf('%+.2f%%', $variacao);
            } else {
                $conta['variacao_mes_anterior'] = 'N/A';
            }
            $valorAnterior = $valorAtual;
        }
        $contasComVariacao = array_merge($contasComVariacao, $grupoDeContas);
    }
    
    return $contasComVariacao;
}

function gerarCSVContas($contas, $statusFiltro = 'todas', $moduleFiltro = 'todos') {
    // Extrair o módulo real se for um array
    $moduloFiltroReal = is_array($moduleFiltro) ? ($moduleFiltro['categoria'] ?? $moduleFiltro['modulo'] ?? reset($moduleFiltro)) : $moduleFiltro;
    
    $nomeArquivo = 'contas';
    $contasFiltradas = $contas;
    $nomeArquivo .= ($moduloFiltroReal !== 'todos') ? '_' . $moduloFiltroReal : '';

    if ($statusFiltro === 'pendentes') {
        $contasFiltradas = array_filter($contasFiltradas, function($conta) use ($moduloFiltroReal) {
            if (!is_array($conta) || !isset(FATURA_TABLE_MAPPING[$moduloFiltroReal])) return false;
            $statusColumn = FATURA_TABLE_MAPPING[$moduloFiltroReal]['status_column'];
            return ($conta[$statusColumn] ?? 'pendente') === 'pendente';
        });
        $nomeArquivo .= '_pendentes';
    } elseif ($statusFiltro === 'pagas') {
        $contasFiltradas = array_filter($contasFiltradas, function($conta) use ($moduloFiltroReal) {
            if (!is_array($conta) || !isset(FATURA_TABLE_MAPPING[$moduloFiltroReal])) return false;
            $statusColumn = FATURA_TABLE_MAPPING[$moduloFiltroReal]['status_column'];
            return ($conta[$statusColumn] ?? '') === 'pago';
        });
        $nomeArquivo .= '_pagas';
    }

    $nomeArquivo .= '.csv';

    $contasFiltradas = calcularVariacaoMensal($contasFiltradas);

    ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

    $output = fopen('php://output', 'w+');

    if (!empty($contasFiltradas)) {
        // Obter headers das primeiras keys do primeiro item
        $firstItem = reset($contasFiltradas);
        if (is_array($firstItem)) {
            $headers = array_keys($firstItem);
            fputcsv($output, $headers);

            foreach ($contasFiltradas as $conta) {
                if (is_array($conta)) {
                    fputcsv($output, $conta);
                }
            }
        }
    } else {
        fputcsv($output, ['Nenhum dado encontrado para os filtros selecionados.']);
    }

    fclose($output);
    exit;
}