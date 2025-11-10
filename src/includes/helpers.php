<?php

// Helpers para Formatação
function formatarMoeda(float $valor): string {
    return number_format($valor, 2, ',', '.');
}

function formatarData(string $data): string {
    return date('d/m/Y', strtotime($data));
}

// Helpers para Segurança e Validação
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = preg_replace('/\s+/', ' ', trim($data));
    $data = strip_tags($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Helper para validar uploads de arquivos
function validarUploadArquivo($file, $tiposPermitidos = ['application/pdf'], $tamanhoMaxMB = 5, $extensoesPermitidas = ['pdf']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return 'Erro no upload do arquivo.';
    }
    if ($file['size'] > $tamanhoMaxMB * 1024 * 1024) {
        return 'Arquivo excede o tamanho máximo permitido.';
    }
    if (!in_array($file['type'], $tiposPermitidos)) {
        return 'Tipo de arquivo não permitido.';
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extensoesPermitidas)) {
        return 'Extensão de arquivo não permitida.';
    }
    return true;
}

function gerarNomeUnicoArquivo($originalName) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return uniqid('upload_', true) . '.' . $ext;
}

// Helper para CSRF token
function gerarCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function validarCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validarPermissao(string $permissao): void {
    if (!isset($_SESSION['permissoes']) || !in_array($permissao, $_SESSION['permissoes'])) {
        header('Location: unauthorized.php');
        exit;
    }
}

function validarCNPJ(string $cnpj): bool {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) != 14) return false;
    if (preg_match('/(\d)\1{13}/', $cnpj)) return false;
    
    // Validação do primeiro dígito verificador
    $soma = 0;
    for ($i = 0, $j = 5; $i < 12; $i++) {
        $soma += (int)$cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    if ((int)$cnpj[12] !== $digito1) return false;
    
    // Validação do segundo dígito verificador
    $soma = 0;
    for ($i = 0, $j = 6; $i < 13; $i++) {
        $soma += (int)$cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    return (int)$cnpj[13] === $digito2;
}

function validarCPF(string $cpf): bool {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $resto = ($soma * 10) % 11;
        if ($resto == 10 || $resto == 11) {
            $resto = 0;
        }
        if ($resto != (int)$cpf[$t]) {
            return false;
        }
    }
    
    return true;
}

function validarSenha(string $senha): bool {
    // Mínimo 8 caracteres, pelo menos uma letra maiúscula, uma minúscula e um número
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $senha);
}


// Helpers para Mensagens e Sessão

function isActive(string $pagina): string {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $pagina ? 'active' : '';
}

// Helpers para Máscaras
function mascaraCPF(string $cpf): string {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }
    return $cpf;
}

function mascaraCNPJ(string $cnpj): string {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    if (strlen($cnpj) === 14) {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }
    return $cnpj;
}

function mascaraTelefone(string $telefone): string {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    $length = strlen($telefone);
    
    if ($length === 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
    } else if ($length === 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
    }
    
    return $telefone;
}

// Helpers para Lógica de Negócio
function calcularMedia(array $valores): float {
    if (empty($valores)) return 0;
    return array_sum($valores) / count($valores);
}

function calcularTotal(array $valores): float {
    return (float)array_sum($valores);
}

function slug(string $string): string {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

function limparString(string $string): string {
    return preg_replace('/[^a-zA-Z0-9]/', '', $string);
}

// Helpers para Integrações e Logs
function buscarCEP(string $cep): array {
    $cep = preg_replace('/[^0-9]/', '', $cep);
    if (strlen($cep) !== 8) {
        return ['erro' => true, 'mensagem' => 'CEP inválido.'];
    }
    
    $url = "https://viacep.com.br/ws/{$cep}/json/";
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['erro' => true, 'mensagem' => 'Erro na requisição cURL: ' . $error_msg];
    }
    curl_close($ch);
    
    if ($http_code !== 200) {
        return ['erro' => true, 'mensagem' => 'Erro na resposta do servidor (HTTP ' . $http_code . ').'];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['erro' => true, 'mensagem' => 'Resposta inválida do servidor (JSON).'];
    }
    
    if (isset($data['erro']) && $data['erro'] === true) {
        return ['erro' => true, 'mensagem' => 'CEP não encontrado.'];
    }
    
    return $data;
}

function enviarEmail(string $para, string $assunto, string $mensagem): bool {
    // Configurar envio de e-mail (implementar conforme necessidade)
    return mail($para, $assunto, $mensagem);
}

function gerarPDF(string $html, string $filename = 'documento.pdf'): bool {
    // Implementar geração de PDF (pode usar library como DOMPDF)
    return true;
}

function gerarLog(string $acao, string $descricao): void {
    $logger = null;
    if (file_exists(__DIR__ . '/Logger.php')) {
        require_once __DIR__ . '/Logger.php';
        $logger = Logger::getInstance();
    }
    $logsDir = __DIR__ . '/../logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0777, true);
    }
    $log = date('d-m-Y H:i:s') . " | {$acao} | {$descricao}\n";
    file_put_contents($logsDir . '/system.log', $log, FILE_APPEND);
    if ($logger) {
        $logger->info($acao . ' - ' . $descricao);
    }
}

function gerarGrafico(array $dados, string $tipo = 'line') {
    // Implementar lógica de geração de gráficos
    return json_encode($dados);
}

/**
 * Extrai um valor do texto baseado em uma lista de palavras-chave e um padrão de regex.
 *
 * @param string $text O texto completo para pesquisar.
 * @param array $keywords As palavras-chave para procurar. A primeira encontrada será usada.
 * @param string $patternRegex O padrão regex para extrair o valor que segue a palavra-chave.
 * @param bool $findLast Se verdadeiro, encontra a última ocorrência do padrão no texto.
 * @return string|null O valor extraído ou null se não for encontrado.
 */
function extractData(string $text, array $keywords, string $patternRegex, bool $findLast = false): ?string {
    $lines = explode("\n", $text);
    $foundValue = null;

    // 1. Tenta encontrar usando palavras-chave em linhas separadas
    foreach ($lines as $i => $line) {
        foreach ($keywords as $keyword) {
            if (stripos($line, $keyword) !== false) {
                // Tenta encontrar o valor na mesma linha
                if (preg_match($patternRegex, $line, $matches)) {
                    $foundValue = $matches[1];
                    if (!$findLast) return $foundValue;
                }
                // Tenta encontrar na próxima linha (se o valor estiver quebrado)
                if ($i + 1 < count($lines) && preg_match($patternRegex, $lines[$i+1], $matches)) {
                    $foundValue = $matches[1];
                    if (!$findLast) return $foundValue;
                }
            }
        }
    }

    // 2. Se nada foi encontrado, faz uma busca global no texto (menos preciso)
    if (preg_match_all($patternRegex, $text, $matches)) {
        $foundValue = $findLast ? end($matches[1]) : $matches[1][0];
    }

    return $foundValue;
}

/**
 * Extrai um valor monetário do texto.
 *
 * @param string $text O texto completo.
 * @param array $keywords Lista de palavras-chave para encontrar o valor.
 * @return float|null O valor formatado como float ou null.
 */
function extractValor(string $text, array $keywords): ?float {
    // Regex aprimorado para capturar valores monetários com ou sem "R$" e com diferentes separadores
    $pattern = '/(?:' . implode('|', $keywords) . ')[^\d]*?R?\$\s*([\d.,]+)/i';
    
    preg_match_all($pattern, $text, $matches);

    if (!empty($matches[1])) {
        // Pega a última captura, que geralmente é o total
        $valor_str = end($matches[1]);
        
        // Limpeza robusta do valor
        $valor_str_limpo = str_replace('.', '', $valor_str); // Remove separadores de milhar
        $valor_str_limpo = str_replace(',', '.', $valor_str_limpo); // Troca vírgula por ponto decimal
        
        return (float) $valor_str_limpo;
    }

    return null;
}


/**
 * Extrai a data de vencimento do texto.
 *
 * @param string $text O texto completo.
 * @param array $keywords Lista de palavras-chave para a data.
 * @return string|null A data no formato d-m-Y ou null.
 */
function extractDataVencimento(string $text, array $keywords): ?string {
    $pattern = '/(?:' . implode('|', $keywords) . ')[^\d]*?(\d{2}\/\d{2}\/\d{2,4})/i';
    
    preg_match_all($pattern, $text, $matches);

    if (!empty($matches[1])) {
        $data_str = end($matches[1]); // Pega a última data encontrada
        
        // Tenta criar o objeto de data, primeiro com ano de 2 dígitos, depois com 4
        $dateObj = DateTime::createFromFormat('d/m/y', $data_str);
        if (!$dateObj) {
            $dateObj = DateTime::createFromFormat('d/m/Y', $data_str);
        }
        
        return $dateObj ? $dateObj->format('d-m-Y') : null;
    }

    return null;
}

function sendJsonResponse($success, $message, $httpCode = 200) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($httpCode);
    }
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

