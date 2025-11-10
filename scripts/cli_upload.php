<?php
// cli_upload.php
// Usage: php cli_upload.php <path-to-pdf-relative-to-public/uploads>

if ($argc < 2) {
    echo "Usage: php cli_upload.php <upload_filename_in_public/uploads>\n";
    exit(1);
}

$uploadFile = __DIR__ . '/../public/uploads/' . $argv[1];
if (!file_exists($uploadFile)) {
    echo "File not found: $uploadFile\n";
    exit(2);
}

// Bootstrap to generate CSRF token in a session and use the same cookie jar
session_start();
require_once __DIR__ . '/../src/includes/helpers.php';
// Ensure session path is same as webserver (best-effort)
$token = gerarCSRFToken();

$cookieFile = sys_get_temp_dir() . '/compras_test_cookie.txt';
// Save session cookie manually (PHPSESSID)
file_put_contents($cookieFile, "PHPSESSID=" . session_id());

// Prepare cURL POST to the application's public endpoint
$url = 'http://127.0.0.1/compras/public/processa_pdf.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// Prepare POST fields including csrf_token
$postFields = [
    'csrf_token' => $token,
    'salvar' => '1',
    'pdfFile' => new CURLFile($uploadFile, 'application/pdf', basename($uploadFile))
];
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
// Cookie (attempt) - set PHPSESSID cookie header
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
$response = curl_exec($ch);
$err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode\n";
if ($err) {
    echo "cURL Error: $err\n";
}
echo "Response:\n" . $response . "\n";

// Print last lines of system log to help debugging
$logFile = __DIR__ . '/../logs/system.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $last = array_slice($lines, -80);
    echo "\n---- last system.log lines ----\n";
    echo implode('', $last);
}

?>