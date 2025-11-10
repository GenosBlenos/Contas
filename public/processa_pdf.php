<?php
// processa_pdf.php
define('API_REQUEST', true);
require_once __DIR__ . '/../src/includes/session_config.php';
require_once __DIR__ . '/../src/includes/config.php';
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

// [Manipuladores de Erro Globais - mantidos iguais]
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

// 1. Validação CSRF e de Upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdfFile']) || !isset($_POST['csrf_token']) || !validarCSRFToken($_POST['csrf_token'])) {
    gerarLog('Falha CSRF ou upload', 'Tentativa de upload sem token CSRF válido ou sem arquivo.');
    sendJsonResponse(false, 'Erro: Requisição inválida.', 400);
}

$validacaoUpload = validarUploadArquivo($_FILES['pdfFile']);
if ($validacaoUpload !== true) {
    gerarLog('Falha upload', $validacaoUpload);
    sendJsonResponse(false, htmlspecialchars($validacaoUpload), 400);
}

// 2. Salvar arquivo temporário
$pdfFileName = sanitizeInput($_FILES['pdfFile']['name']);
$nomeUnico = gerarNomeUnicoArquivo($pdfFileName);
$caminhoDestino = __DIR__ . '/uploads/' . $nomeUnico;
if (!move_uploaded_file($_FILES['pdfFile']['tmp_name'], $caminhoDestino)) {
    gerarLog('Falha upload', 'Erro ao mover o arquivo para uploads/');
    sendJsonResponse(false, 'Erro ao salvar o arquivo enviado.', 500);
}

// 3. Chamar a API de Machine Learning
$apiUrl = ML_API_URL . '/classify_pdf';
gerarLog('Envio para classificação', 'Arquivo: ' . $pdfFileName);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
$cfile = new CURLFile($caminhoDestino, 'application/pdf', $nomeUnico);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 4. Tratar a resposta da API
if ($response === false) {
    gerarLog('Erro cURL', $curlError);
    sendJsonResponse(false, 'Erro de comunicação com o serviço de IA: ' . htmlspecialchars($curlError), 500);
}

if ($httpCode != 200) {
    gerarLog('Erro API', 'HTTP: ' . $httpCode . ' | Resposta: ' . $response);
    $errorData = json_decode($response, true);
    $apiErrorMsg = $errorData['error'] ?? 'Erro desconhecido da API';
    sendJsonResponse(false, 'O serviço de IA retornou um erro: ' . htmlspecialchars($apiErrorMsg) . ' (HTTP ' . $httpCode . ')', 500);
}

$result = json_decode($response, true);

if (isset($result['status']) && $result['status'] !== 'success') {
    $apiError = $result['error'] ?? 'Erro desconhecido da API Python.';
    gerarLog('Erro API Python', $apiError);
    sendJsonResponse(false, 'Erro na extração de dados: ' . htmlspecialchars($apiError), 400);
}
if (!isset($result['category']) || !isset($result['details'])) {
     gerarLog('Erro API Python', 'Resposta da API incompleta. ' . $response);
    sendJsonResponse(false, 'API de IA retornou uma resposta incompleta.', 500);
}

// 5. RENOMEAR ARQUIVO COM OS DADOS EXTRAÍDOS - CORRIGIDO
$novoNome = gerarNomeArquivoComDados($result['category'], $result['details'], $nomeUnico);
$novoCaminho = __DIR__ . '/uploads/' . $novoNome;

if (rename($caminhoDestino, $novoCaminho)) {
    $result['arquivo_pdf'] = 'uploads/' . $novoNome;
    gerarLog('Arquivo renomeado', 'De: ' . $nomeUnico . ' Para: ' . $novoNome);
} else {
    $result['arquivo_pdf'] = 'uploads/' . $nomeUnico;
    gerarLog('Erro ao renomear arquivo', 'Arquivo mantido como: ' . $nomeUnico);
}

// 6. ENVIAR DADOS EXTRAÍDOS DE VOLTA PARA O FRONTEND
header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $result]);
exit;

/**
 * Gera o nome do arquivo PDF baseado nos dados extraídos
 * Formato: modulo_mes_ano_instalacao.pdf
 */
function gerarNomeArquivoComDados(string $categoria, array $detalhes, string $nomeOriginal): string {
    $extensao = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
    
    // Obter os componentes do nome - CORRIGIDO
    $modulo = $categoria; // Usar a categoria como módulo (agua, energia, etc.)
    $mesReferencia = obterMesReferencia($categoria, $detalhes);
    $anoReferencia = obterAnoReferencia($categoria, $detalhes);
    $instalacao = obterCodigoInstalacao($categoria, $detalhes);
    
    // Limpar e formatar os componentes
    $modulo = limparParaNomeArquivo($modulo);
    $mes = limparParaNomeArquivo(extrairApenasMes($mesReferencia) ?? 'mes');
    $ano = limparParaNomeArquivo($anoReferencia ?? 'ano');
    $instalacao = limparParaNomeArquivo($instalacao ?? 'instalacao');
    
    return $modulo . '_' . $mes . '_' . $ano . '_' . $instalacao . '.' . $extensao;
}

/**
 * Extrai apenas a parte do mês da referência (ex: "AGO/2025" -> "AGO")
 */
function extrairApenasMes(?string $mesReferencia): ?string {
    if (!$mesReferencia) return null;
    
    // Remove espaços e divide por barra
    $partes = explode('/', trim($mesReferencia));
    if (count($partes) >= 1) {
        return $partes[0];
    }
    
    return $mesReferencia;
}

/**
 * Obtém o ano de referência baseado na categoria
 */
function obterAnoReferencia(string $categoria, array $detalhes): ?string {
    $mesReferencia = obterMesReferencia($categoria, $detalhes);
    
    if (!$mesReferencia) return null;
    
    // Tenta extrair ano do formato "MES/ANO"
    if (preg_match('/(\d{4})/', $mesReferencia, $matches)) {
        return $matches[1];
    }
    
    // Fallback para data de vencimento ou emissão
    $dataVencimento = $detalhes['vencimento'] ?? $detalhes['data_vencimento'] ?? null;
    if ($dataVencimento && preg_match('/(\d{4})/', $dataVencimento, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Obtém o código de instalação baseado na categoria - CORRIGIDO
 */
function obterCodigoInstalacao(string $categoria, array $detalhes): ?string {
    switch ($categoria) {
        case 'agua':
            return $detalhes['numero_ligacao'] ?? null;
        case 'energia':
            // Para energia, prioriza codigo_instalacao, fallback para número da fatura
            return $detalhes['codigo_instalacao'] ?? $detalhes['numero_fatura'] ?? null;
        case 'telefone':
            return $detalhes['contrato'] ?? $detalhes['numero_fatura'] ?? null;
        case 'internet':
        case 'semparar':
            return $detalhes['numero_fatura'] ?? $detalhes['codigo_cliente'] ?? null;
        default:
            return null;
    }
}

/**
 * Obtém o mês de referência baseado na categoria - MANTIDO
 */
function obterMesReferencia(string $categoria, array $detalhes): ?string {
    switch ($categoria) {
        case 'agua':
            return $detalhes['referencia'] ?? null;
        case 'energia':
            return $detalhes['conta_mes'] ?? null;
        case 'telefone':
        case 'internet':
        case 'semparar':
            return $detalhes['periodo_servico'] ?? $detalhes['data_emissao'] ?? null;
        default:
            return null;
    }
}

/**
 * Limpa string para uso em nome de arquivo - MANTIDO
 */
function limparParaNomeArquivo(string $texto): string {
    // Remove caracteres especiais e substitui espaços por underscores
    $texto = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $texto);
    // Remove múltiplos underscores
    $texto = preg_replace('/_{2,}/', '_', $texto);
    // Remove underscores no início e fim
    $texto = trim($texto, '_');
    
    return $texto;
}