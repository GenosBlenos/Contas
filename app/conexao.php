<?php
// Configurações padrão
$host = 'localhost';
$db   = 'compras';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// Tenta carregar configs do .env.php se existir
$env_path = __DIR__ . '/.env.php';
if (file_exists($env_path)) {
    $env = include $env_path;
    $host = $env['host'] ?? $host;
    $db = $env['db'] ?? $db;
    $user = $env['user'] ?? $user;
    $pass = $env['pass'] ?? $pass;
    $charset = $env['charset'] ?? $charset;
}

// --- CONEXÃO MYSQLI (para código legado) ---
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    error_log("Falha na conexão MySQLi: " . $conn->connect_error);
    die("Erro ao conectar com o banco de dados via MySQLi.");
}
$conn->set_charset($charset);
$conn->query("SET NAMES 'utf8mb4'");
$conn->query("SET CHARACTER SET utf8mb4");
$conn->query("SET SESSION collation_connection = 'utf8mb4_unicode_ci'");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- CONEXÃO PDO (para código novo) ---
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    error_log("Conexão PDO bem-sucedida com o banco: $db");
} catch (PDOException $e) {
    error_log("Falha na conexão PDO: " . $e->getMessage());
    die("Erro ao conectar com o banco de dados via PDO: " . $e->getMessage());
}
?>