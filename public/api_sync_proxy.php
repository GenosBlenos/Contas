<?php
// Protótipo de proxy para sincronização via URL genérica
// Uso (GET):  public/api_sync_proxy.php?api_url=https://example.com/api/files&api_key=TOKEN
// Uso (POST JSON): { "mode": "api", "api_url": "https://...", "api_key": "..." }

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/controllers/ApiIntegrationController.php';

$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) $input = $json;
}

// merge GET/POST for convenience
$input = array_merge($_GET, $_POST, $input);

$controller = new ApiIntegrationController();

if (!empty($input['mode']) && $input['mode'] === 'provider') {
    $provider = $input['provider'] ?? $input['provider_name'] ?? null;
    $credentials = [];
    if (!empty($input['credentials_json'])) {
        $credentials = json_decode($input['credentials_json'], true) ?: [];
    } elseif (!empty($input['credentials'])) {
        // allow passing credentials as JSON string or array
        if (is_string($input['credentials'])) $credentials = json_decode($input['credentials'], true) ?: [];
        elseif (is_array($input['credentials'])) $credentials = $input['credentials'];
    }

    if (!$provider) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta provider (provider/provider_name)'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $report = $controller->syncFromProvider($provider, $credentials);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$apiUrl = $input['api_url'] ?? null;
$apiKey = $input['api_key'] ?? ($input['key'] ?? null);

if (empty($apiUrl)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Parâmetro api_url é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$report = $controller->syncFromApi($apiUrl, $apiKey);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// fim
