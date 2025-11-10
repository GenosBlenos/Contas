<?php

class ActivityMonitor {
    private static $instance = null;
    private $logger;
    private $db;
    private $securityManager;

    private function __construct() {
        $this->logger = Logger::getInstance();
        $this->db = Database::getInstance();
        $this->securityManager = SecurityManager::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function logActivity($action, $details = [], $userId = null) {
        if ($userId === null && isset($_SESSION['usuario_id'])) {
            $userId = $_SESSION['usuario_id'];
        }

        $sql = "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->db->prepare($sql);
            $detailsJson = json_encode($details);
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            
            $stmt->bind_param('issss', $userId, $action, $detailsJson, $ipAddress, $userAgent);
            $stmt->execute();

            $this->logger->info("Atividade registrada: {$action}", [
                'user_id' => $userId,
                'details' => $details
            ]);

        } catch (Exception $e) {
            $this->logger->error('Erro ao registrar atividade', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function monitorQueryPerformance($query, $executionTime) {
        if ($executionTime > 1.0) { // Queries que levam mais de 1 segundo
            $this->logger->warning('Query lenta detectada', [
                'query' => $query,
                'execution_time' => $executionTime
            ]);
        }
    }

    public function monitorLoginAttempts($email, $success = false) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (!$success) {
            $this->securityManager->recordFailedLogin($email, $ip);
        }

        $sql = "INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ssi', $email, $ip, $success);
        $stmt->execute();
    }

    public function monitorResourceUsage() {
        $usage = [
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'cpu_time' => getrusage()
        ];

        if ($usage['memory'] > 64 * 1024 * 1024) { // 64MB
            $this->logger->warning('Alto uso de memória detectado', $usage);
        }

        return $usage;
    }

    public function monitorErrors($error) {
        $this->logger->error('Erro da aplicação', [
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'trace' => $error['trace'] ?? null
        ]);
    }

    public function getActivityHistory($userId = null, $limit = 50) {
        $sql = "SELECT * FROM activity_log";
        $params = [];
        
        if ($userId !== null) {
            $sql .= " WHERE user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        
        if (!empty($params)) {
            $types = str_repeat('i', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getLoginAttempts($email, $timeframe = 900) { // 15 minutos
        $sql = "SELECT * FROM login_attempts 
                WHERE email = ? 
                AND attempt_time >= DATE_SUB(NOW(), INTERVAL ? SECOND)
                ORDER BY attempt_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('si', $email, $timeframe);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function cleanOldRecords() {
        // Limpar registros antigos (mais de 30 dias)
        $tables = ['activity_log', 'login_attempts'];
        
        foreach ($tables as $table) {
            $sql = "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $this->db->query($sql);
        }
    }
}
