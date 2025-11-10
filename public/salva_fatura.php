<?php
ini_set('display_errors', 0); // Impede que erros do PHP corrompam a resposta JSON
require_once __DIR__ . '/../src/includes/session_config.php';

// salvar_fatura.php (Sem alterações da resposta anterior)
define('API_REQUEST', true);

require_once __DIR__ . '/../src/includes/config.php';
require_once __DIR__ . '/../src/includes/auth.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    sendJsonResponse(false, 'Acesso não autorizado.', 401);
    exit;
}

require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/Database.php';

// --- Manipuladores de Erro Globais ---
set_exception_handler(function ($exception) {
    gerarLog('Fatal Exception', $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine());
    if (!headers_sent()) {
        sendJsonResponse(false, 'Erro crítico inesperado no servidor: ' . htmlspecialchars($exception->getMessage()), 500);
    } else {
        error_log('Fatal exception occurred, but headers already sent. Could not send JSON response. Message: ' . $exception->getMessage());
        exit();
    }
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING])) {
        gerarLog('Fatal Error (Shutdown)', $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
        if (!headers_sent()) {
            sendJsonResponse(false, 'Erro fatal inesperado no servidor. Por favor, verifique os logs.', 500);
        } else {
            error_log('Fatal error occurred, but headers already sent. Could not send JSON response. Message: ' . $error['message']);
            exit();
        }
    }
});
// --- Fim dos Manipuladores de Erro ---

// Funções auxiliares
function getDisplayCategoryName(string $category): string {
    $map = [
        'agua' => 'Água',
        'energia' => 'Energia Elétrica',
        'telefone' => 'Telefone',
        'internet' => 'Internet',
        'semparar' => 'Sem Parar',
    ];
    return $map[strtolower($category)] ?? ucfirst($category);
}
function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (bool) $stmt->fetchColumn();
    } catch (Exception $e) {
        gerarLog('Info DB', 'Falha ao checar coluna ' . $column . ' em ' . $table . ': ' . $e->getMessage());
        return false;
    }
}

// 1. Receber os dados JSON do modal
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['category']) || !isset($data['details']) || !isset($data['arquivo_pdf'])) {
    gerarLog('Erro salvamento', 'JSON inválido ou dados ausentes recebidos do modal.');
    sendJsonResponse(false, 'Erro: Dados de salvamento inválidos.', 400);
}

// 2. Extrair dados para as variáveis
$category = strtolower($data['category'] ?? 'desconhecido');
$dados = $data['details'] ?? [];
$arquivoPdfPath = $data['arquivo_pdf']; // Ex: 'uploads/nome-unico.pdf'

$displayCategory = getDisplayCategoryName($category);

// 3. Salvar os dados no banco de dados
try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->beginTransaction();

    // Etapa 1: Obter o NOME da unidade diretamente do payload.
    $unidade_nome = $data['unidade_nome'] ?? 'Sem Unidade (automático)';

    // Etapa 2: Mapeamento e Inserção na Tabela Correta
    $tableMapping = [
        'agua' => ['table' => 'agua', 'columns' => ['numero_ligacao', 'proprietario', 'endereco_ligacao', 'referencia', 'vencimento', 'consumo_m3', 'total_a_pagar']],
        'comunicacao' => ['table' => 'comunicacao', 'columns' => ['numero_nota_fiscal', 'credor', 'cnpj_credor', 'data_emissao', 'periodo_prestacao', 'valor_total']],
        'fatura_veiculos' => ['table' => 'fatura_veiculos', 'columns' => ['numero_fatura', 'codigo_cliente', 'data_emissao', 'data_vencimento', 'total_a_pagar']], // Se 'unidade_id' existia aqui, também deve ser removido
        'energia' => ['table' => 'energia', 'columns' => ['codigo_instalacao', 'conta_mes', 'vencimento', 'total_a_pagar', 'endereco_consumo', 'classificacao', 'consumo_kwh', 'fat_impostos', 'fat_distribuidora', 'multa_atraso', 'imposto_retido_total', 'imposto_retido_irrf']],
        'telefone' => ['table' => 'telefone', 'columns' => ['contrato', 'fatura', 'vencimento', 'total_a_pagar', 'periodo_servico', 'valor_servico']],
        'internet' => ['table' => 'internet', 'columns' => ['mes', 'total_a_pagar', 'multa', 'total', 'status', 'data_vencimento', 'secretaria', 'tipo_plano', 'velocidade', 'ip_fixo', 'num_contrato', 'observacao']],
        'semparar' => ['table' => 'semparar', 'columns' => ['mes', 'total_a_pagar', 'multa', 'total', 'status', 'data_vencimento', 'secretaria', 'placa_veiculo', 'num_tag', 'num_eixos', 'observacao']],
    ];

    if (!isset($tableMapping[$category])) {
        throw new Exception("Categoria '{$category}' não mapeada para uma tabela de banco de dados.");
    }

    $tableInfo = $tableMapping[$category];
    $tableName = $tableInfo['table'];
    $allowedColumns = $tableInfo['columns'];

    $insertData = [];
    foreach ($allowedColumns as $column) {
        if (isset($dados[$column])) {
            // Garante que valores vazios sejam inseridos como NULL
            $insertData[$column] = $dados[$column] === '' ? null : $dados[$column];
        }
    }

    // Adiciona o cálculo do total_a_pagar especificamente para a categoria 'energia'
    if ($category === 'energia') {
        // Calcula o valor original somando os componentes relevantes
        $valorOriginal = (float)($dados['total_a_pagar'] ?? 0) - (float)($dados['multa_atraso'] ?? 0);
        
        // Adiciona o valor calculado aos dados que serão inseridos no banco
        $insertData['total_a_pagar'] = $valorOriginal;

        // Adiciona a coluna 'total_a_pagar' à lista de colunas permitidas para inserção
        if (!in_array('total_a_pagar', $allowedColumns)) {
            $tableMapping[$category]['columns'][] = 'total_a_pagar';
            $allowedColumns[] = 'total_a_pagar';
        }
    }

    if (columnExists($pdo, $tableName, 'unidade')) {
        $insertData['unidade'] = $unidade_nome;
    }

    if (columnExists($pdo, $tableName, 'arquivo_pdf')) {
        $insertData['arquivo_pdf'] = $arquivoPdfPath;
    }

    if (empty($insertData)) {
        throw new Exception("Nenhum dado aplicável para salvar para a categoria '{$category}'.");
    }

    $dbColumns = array_keys($insertData);
    $placeholders = array_map(fn($c) => ":$c", $dbColumns);

    $sql = "INSERT INTO {$tableName} (" . implode(', ', $dbColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);

    $stmt->execute($insertData); // Se houver erro, uma PDOException será lançada aqui devido a ERRMODE_EXCEPTION

    // Verifica se a inserção realmente afetou alguma linha
    if ($stmt->rowCount() === 0) {
        throw new Exception("A inserção na tabela '{$tableName}' não afetou nenhuma linha. Dados podem não ter sido salvos.");
    }

    $lastInsertId = $pdo->lastInsertId();
    
    $pdo->commit();
    gerarLog('Sucesso', "Fatura de {$displayCategory} inserida com ID: " . $lastInsertId);
    sendJsonResponse(true, 'Fatura de ' . htmlspecialchars($displayCategory) . ' salva com sucesso com ID ' . $lastInsertId . '!');

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error_message = $e->getMessage();
    gerarLog('Erro Exceção DB', 'Exceção: ' . $error_message);
    sendJsonResponse(false, 'Erro no processamento do banco de dados: ' . $error_message, 500);
}