<?php
// Uso: php scripts/api_sync.php <api_url> [api_key]

require_once __DIR__ . '/../src/controllers/ApiIntegrationController.php';

if ($argc < 2) {
    echo "Uso:\n";
    echo "  php scripts/api_sync.php api <api_url> [api_key]\n";
    echo "  php scripts/api_sync.php provider <provider_name> <credentials_json_file_or_json_string>\n";
    exit(1);
}

$mode = $argv[1];
$controller = new ApiIntegrationController();

if ($mode === 'api') {
    if ($argc < 3) {
        echo "Faltando <api_url>\n";
        exit(1);
    }
    $apiUrl = $argv[2];
    $apiKey = $argv[3] ?? null;
    $report = $controller->syncFromApi($apiUrl, $apiKey);
} elseif ($mode === 'provider') {
    if ($argc < 4) {
        echo "Faltando <provider_name> e credenciais\n";
        exit(1);
    }
    $providerName = $argv[2];
    $credArg = $argv[3];
    // credArg pode ser um caminho para arquivo JSON ou uma string JSON
    if (file_exists($credArg)) {
        $credJson = file_get_contents($credArg);
    } else {
        $credJson = $credArg;
    }
    $credentials = json_decode($credJson, true) ?? [];
    $report = $controller->syncFromProvider($providerName, $credentials);
} else {
    echo "Modo desconhecido: {$mode}\n";
    exit(1);
}

echo "Sincronização finalizada.\n";
echo "Sucesso: " . count($report['success']) . " itens\n";
foreach ($report['success'] as $s) {
    echo " - index={$s['index']} id={$s['id']} file={$s['file']}\n";
}

if (!empty($report['errors'])) {
    echo "Erros: " . count($report['errors']) . "\n";
    foreach ($report['errors'] as $e) {
        echo " - index={$e['index']} msg={$e['message']}\n";
    }
}
