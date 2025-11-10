<?php
// recomendacoes.php (versão final standalone)

require_once __DIR__ . '/../src/includes/session_config.php';
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/Database.php';
$pdo = Database::getInstance()->getConnection();

$pageTitle = 'Recomendações';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /compras/login.php');
    session_start();
    exit;
}

require_once __DIR__ . '/../src/includes/header.php';

$_GET['module'] = 'recomendacoes';

// 1. OBTER VALORES DOS FILTROS DA URL
$filtroModulo = $_GET['modulo'] ?? 'energia'; 
$filtroInstalacao = $_GET['instalacao'] ?? 'todas';
$filtroMesAno = $_GET['mes_ano'] ?? 'todos';

// 2. DEFINIR MAPEAMENTO DE COLUNAS POR MÓDULO
$mapeamentoColunas = [
    'agua' => [
        'instalacao' => 'numero_ligacao',
        'data_vencimento' => 'vencimento',
        'consumo' => 'consumo_m3',
        'valor' => 'total_a_pagar',
        'unidade' => 'unidade_id'
    ],
    'energia' => [
        'instalacao' => 'codigo_instalacao',
        'data_vencimento' => 'vencimento',
        'consumo' => 'consumo_kwh',
        'valor' => 'total_a_pagar',
        'unidade' => 'unidade_id'
    ],
    'semparar' => [
        'instalacao' => 'codigo_cliente',
        'data_vencimento' => 'data_vencimento',
        'consumo' => null,
        'valor' => 'total_a_pagar',
        'unidade' => 'unidade'
    ],
    'telefone' => [
        'instalacao' => 'contrato',
        'data_vencimento' => 'vencimento',
        'consumo' => null,
        'valor' => 'total_a_pagar',
        'unidade' => 'unidade_id'
   ],
    'internet' => [
        'instalacao' => 'numero_nota_fiscal',
        'data_vencimento' => 'data_emissao',
        'consumo' => null,
        'valor' => 'valor_total',
        'unidade' => 'unidade_id'
    ]
];

// 3. OBTER DADOS PARA POPULAR OS FILTROS
$modulosDisponiveis = array_keys($mapeamentoColunas);
$instalacoesDisponiveis = [];
$mesesAnosDisponiveis = [];

if ($filtroModulo !== 'todos' && isset($mapeamentoColunas[$filtroModulo])) {
    $colunas = $mapeamentoColunas[$filtroModulo];
    
    // Buscar instalações disponíveis
    try {
        $colunaInstalacao = $colunas['instalacao'];
        $stmt = $pdo->query("SELECT DISTINCT {$colunaInstalacao} FROM {$filtroModulo} WHERE {$colunaInstalacao} IS NOT NULL AND {$colunaInstalacao} != '' ORDER BY {$colunaInstalacao}");
        $instalacoesDisponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Erro ao buscar instalações: " . $e->getMessage());
    }
    
    // Buscar meses/anos disponíveis
    try {
        $colunaData = $colunas['data_vencimento'];
        $stmt = $pdo->query("SELECT DISTINCT DATE_FORMAT({$colunaData}, '%Y-%m') as mes_ano FROM {$filtroModulo} WHERE {$colunaData} IS NOT NULL ORDER BY mes_ano DESC");
        $mesesAnosDisponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Erro ao buscar meses/anos: " . $e->getMessage());
    }
}

// 4. BUSCAR E ANALISAR OS DADOS PARA GERAR RECOMENDAÇÕES
$recomendacoes = [];
$totalEconomia = 0;

if ($filtroModulo !== 'todos' && isset($mapeamentoColunas[$filtroModulo])) {
    try {
        $colunas = $mapeamentoColunas[$filtroModulo];
        $colunaInstalacao = $colunas['instalacao'];
        $colunaData = $colunas['data_vencimento'];
        $colunaConsumo = $colunas['consumo'];
        $colunaValor = $colunas['valor'];
        
        // Construir a query base
        $sql = "SELECT * FROM {$filtroModulo} WHERE 1=1";
        $params = [];
        
        if ($filtroInstalacao !== 'todas') {
            $sql .= " AND {$colunaInstalacao} = ?";
            $params[] = $filtroInstalacao;
        }
        
        if ($filtroMesAno !== 'todos') {
            $sql .= " AND DATE_FORMAT({$colunaData}, '%Y-%m') = ?";
            $params[] = $filtroMesAno;
        }
        
        $sql .= " ORDER BY {$colunaInstalacao}, {$colunaData}";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar nomes das unidades
        $nomesUnidades = [];
        $stmtUnidades = $pdo->query("SELECT id, nome FROM unidades");
        while ($unidade = $stmtUnidades->fetch(PDO::FETCH_ASSOC)) {
            $nomesUnidades[$unidade['id']] = $unidade['nome'];
        }
        
        // Processar os dados e gerar recomendações
        $dadosPorInstalacao = [];
        
        // Agrupar dados por instalação
        foreach ($dados as $linha) {
            $instalacao = $linha[$colunaInstalacao];
            $unidade = $linha['unidade'] ?? null;
            
            // Usar nome da unidade se disponível, caso contrário usar código da instalação
            $nomeInstalacao = $nomesUnidades[$unidade] ?? $instalacao;
            
            if (!isset($dadosPorInstalacao[$nomeInstalacao])) {
                $dadosPorInstalacao[$nomeInstalacao] = [];
            }
            $dadosPorInstalacao[$nomeInstalacao][] = $linha;
        }
        
        // Analisar cada instalação
        foreach ($dadosPorInstalacao as $instalacao => $registros) {
            $recomendacoesInstalacao = [];
            
            // Ordenar registros por data
            usort($registros, function($a, $b) use ($colunaData) {
                return strtotime($a[$colunaData]) - strtotime($b[$colunaData]);
            });
            
            // ANÁLISE 1: Atrasos no pagamento
            $hoje = date('Y-m-d');
            foreach ($registros as $registro) {
                if (isset($registro[$colunaData]) && 
                    $registro[$colunaData] < $hoje && 
                    (!isset($registro['status']) || $registro['status'] === 'pendente')) {
                    
                    $multaEstimada = $registro[$colunaValor] * 0.02; // 2% de multa
                    $jurosEstimado = $registro[$colunaValor] * 0.01; // 1% de juros ao mês
                    $economia = $multaEstimada + $jurosEstimado;
                    
                    $recomendacoesInstalacao[] = [
                        'tipo' => 'Pagamento em Atraso',
                        'mensagem' => "Conta vencida em " . date('d/m/Y', strtotime($registro[$colunaData])) . " ainda não foi paga. Evite multas e juros.",
                        'severidade' => 'alta',
                        'economia_anual' => $economia * 12,
                        'data' => $registro[$colunaData]
                    ];
                    $totalEconomia += $economia * 12;
                    break;
                }
            }
            
            // ANÁLISE 2: Variação significativa no consumo (apenas para módulos com consumo)
            if ($colunaConsumo && count($registros) >= 2) {
                $ultimosMeses = array_slice($registros, -2);
                $mesAtual = $ultimosMeses[1];
                $mesAnterior = $ultimosMeses[0];
                
                if (isset($mesAtual[$colunaConsumo]) && isset($mesAnterior[$colunaConsumo]) && 
                    is_numeric($mesAtual[$colunaConsumo]) && is_numeric($mesAnterior[$colunaConsumo]) &&
                    $mesAnterior[$colunaConsumo] > 0) {
                    
                    $variacao = (($mesAtual[$colunaConsumo] - $mesAnterior[$colunaConsumo]) / $mesAnterior[$colunaConsumo]) * 100;
                    
                    if ($variacao > 30) {
                        $economiaEstimada = $mesAtual[$colunaValor] * 0.15; // 15% de economia potencial
                        
                        $recomendacoesInstalacao[] = [
                            'tipo' => 'Consumo Elevado',
                            'mensagem' => "Aumento de " . round($variacao, 1) . "% no consumo em relação ao mês anterior. Verifique possíveis vazamentos ou desperdícios.",
                            'severidade' => 'media',
                            'economia_anual' => $economiaEstimada * 12,
                            'data' => $mesAtual[$colunaData]
                        ];
                        $totalEconomia += $economiaEstimada * 12;
                    } elseif ($variacao < -20) {
                        $recomendacoesInstalacao[] = [
                            'tipo' => 'Redução no Consumo',
                            'mensagem' => "Redução de " . round(abs($variacao), 1) . "% no consumo. Continue com as boas práticas!",
                            'severidade' => 'info',
                            'economia_anual' => 0,
                            'data' => $mesAtual[$colunaData]
                        ];
                    }
                }
            }
            
            // ANÁLISE 3: Consumo acima da média histórica (apenas para módulos com consumo)
            if ($colunaConsumo && count($registros) >= 3) {
                $consumos = array_filter(array_column($registros, $colunaConsumo), 'is_numeric');
                if (count($consumos) >= 3) {
                    $mediaConsumo = array_sum($consumos) / count($consumos);
                    $ultimoRegistro = end($registros);
                    $ultimoConsumo = $ultimoRegistro[$colunaConsumo];
                    
                    if ($ultimoConsumo > ($mediaConsumo * 1.2)) {
                        $excesso = $ultimoConsumo - $mediaConsumo;
                        // Calcular o custo do excesso: assumindo que o valor é proporcional ao consumo
                        $custoPorUnidade = $ultimoRegistro[$colunaValor] / $ultimoConsumo;
                        $economiaEstimada = $excesso * $custoPorUnidade;
                        
                        $recomendacoesInstalacao[] = [
                            'tipo' => 'Consumo Acima da Média',
                            'mensagem' => "Consumo atual está " . round(($ultimoConsumo/$mediaConsumo - 1) * 100, 1) . "% acima da média histórica.",
                            'severidade' => 'media',
                            'economia_anual' => $economiaEstimada * 12,
                            'data' => $ultimoRegistro[$colunaData]
                        ];
                        $totalEconomia += $economiaEstimada * 12;
                    }
                }
            }
            
            // ANÁLISE 4: Valores muito altos
            foreach ($registros as $registro) {
                if (isset($registro[$colunaValor]) && $registro[$colunaValor] > 1000) {
                    $economiaEstimada = $registro[$colunaValor] * 0.10; // 10% de economia potencial
                    
                    $recomendacoesInstalacao[] = [
                        'tipo' => 'Valor Elevado',
                        'mensagem' => "Conta de R$ " . number_format($registro[$colunaValor], 2, ',', '.') . " em " . date('m/Y', strtotime($registro[$colunaData])) . ". Verifique se há oportunidades de negociação ou redução.",
                        'severidade' => 'media',
                        'economia_anual' => $economiaEstimada * 12,
                        'data' => $registro[$colunaData]
                    ];
                    $totalEconomia += $economiaEstimada * 12;
                    break;
                }
            }
            
            // ANÁLISE 5: Para energia - horário de pico
            if ($filtroModulo === 'energia') {
                foreach ($registros as $registro) {
                    if (isset($registro[$colunaValor]) && $registro[$colunaValor] > 500) {
                        $economiaEstimada = $registro[$colunaValor] * 0.10;
                        
                        $recomendacoesInstalacao[] = [
                            'tipo' => 'Otimização de Horário',
                            'mensagem' => "Considere transferir consumo para horário fora de pico para reduzir custos na tarifa.",
                            'severidade' => 'media',
                            'economia_anual' => $economiaEstimada * 12,
                            'data' => $registro[$colunaData]
                        ];
                        $totalEconomia += $economiaEstimada * 12;
                        break;
                    }
                }
            }
            
            // Adicionar recomendações da instalação ao resultado geral
            if (!empty($recomendacoesInstalacao)) {
                $recomendacoes[$instalacao] = $recomendacoesInstalacao;
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar dados para recomendações: " . $e->getMessage());
    }
}

// Inicia o buffer de saída para o conteúdo principal
ob_start();
?>

<div class="space-y-8">
    <!-- Cabeçalho e Descrição -->
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Recomendações</h1>
        <p class="text-gray-600">
            Encontre aqui recomendações personalizadas para otimizar seu consumo e reduzir custos, 
            considerando diversos aspectos da sua utilização.
        </p>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Filtrar Análises</h2>
        <form action="recomendacoes.php" method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="modulo" class="block text-sm font-medium text-gray-700">Módulo</label>
                <select name="modulo" id="modulo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <?php foreach ($modulosDisponiveis as $modulo): ?>
                        <option value="<?= $modulo ?>" <?= ($filtroModulo === $modulo) ? 'selected' : '' ?>>
                            <?= ucfirst($modulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="instalacao" class="block text-sm font-medium text-gray-700">Instalação</label>
                <select name="instalacao" id="instalacao" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" <?= empty($instalacoesDisponiveis) ? 'disabled' : '' ?>>
                    <option value="todas">Todas as Instalações</option>
                    <?php foreach ($instalacoesDisponiveis as $inst): ?>
                        <option value="<?= htmlspecialchars($inst) ?>" <?= ($filtroInstalacao === $inst) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($inst) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="mes_ano" class="block text-sm font-medium text-gray-700">Mês/Ano</label>
                <select name="mes_ano" id="mes_ano" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" <?= empty($mesesAnosDisponiveis) ? 'disabled' : '' ?>>
                    <option value="todos">Todos os Períodos</option>
                    <?php foreach ($mesesAnosDisponiveis as $mesAno): ?>
                        <option value="<?= $mesAno ?>" <?= ($filtroMesAno === $mesAno) ? 'selected' : '' ?>>
                            <?= date('m/Y', strtotime($mesAno . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-[#4a90e2] hover:bg-[#2563eb] text-white font-bold py-2 px-4 rounded-md shadow-sm w-full">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- Tabela de Recomendações -->
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unidade</th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recomendação</th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Economia Anual</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($recomendacoes)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                Não há recomendações.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recomendacoes as $instalacao => $listaRecomendacoes): ?>
                            <?php foreach ($listaRecomendacoes as $rec): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($instalacao) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d/m/Y', strtotime($rec['data'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="flex items-center">
                                            <?php
                                                $cores = [
                                                    'alta' => 'bg-red-100 text-red-800',
                                                    'media' => 'bg-yellow-100 text-yellow-800', 
                                                    'info' => 'bg-blue-100 text-blue-800'
                                                ];
                                                $cor = $cores[$rec['severidade']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $cor ?> mr-3">
                                                <?= strtoupper($rec['severidade']) ?>
                                            </span>
                                            <div>
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars($rec['tipo']) ?></div>
                                                <div class="text-gray-500"><?= $rec['mensagem'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($rec['economia_anual'] > 0): ?>
                                            <span class="text-green-600 font-semibold">
                                                R$ <?= number_format($rec['economia_anual'], 2, ',', '.') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Total de Economia -->
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Total Possível Economia</h3>
                <p class="text-sm text-gray-600">Valor anual estimado com a implementação das recomendações</p>
            </div>
            <div class="text-right">
                <span class="text-2xl font-bold text-green-600">
                    R$ <?= number_format($totalEconomia, 2, ',', '.') ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
// Finaliza o buffer e carrega o template principal
$content = ob_get_clean();
require_once __DIR__ . '/../src/includes/template.php';
?>