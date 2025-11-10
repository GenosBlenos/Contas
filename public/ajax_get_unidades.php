<?php
require_once __DIR__ . '/../src/includes/config.php';
require_once __DIR__ . '/../src/includes/Database.php';

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT id, nome FROM unidades ORDER BY nome");
    $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'unidades' => $unidades]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar unidades.']);
}
