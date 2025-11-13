<?php
require_once __DIR__ . '/../../app/configuracoes.php';
require_once __DIR__ . '/Logger.php';

class Database {
    private static $instance = null;
    private $connection; // Will be a PDO object
    private $logger;
    private $queryLog = [];
    private $queryCount = 0;
    private $lastQuery;

    private function __construct() {
        $this->logger = Logger::getInstance();
        
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Configurar charset
            $this->connection->exec("SET NAMES utf8mb4");
            $this->connection->exec("SET CHARACTER SET utf8mb4");
            
            // Configurar timezone
            $this->connection->exec("SET time_zone = '+00:00'");
            
        } catch (PDOException $e) {
            $this->logger->error('Erro de conexão com o banco de dados', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Rethrow exception to allow callers to handle it and return proper JSON responses
            throw $e;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function prepare($sql) {
        $this->lastQuery = $sql;
        return $this->connection->prepare($sql);
    }

    public function query($sql) {
        $this->lastQuery = $sql;
        $startTime = microtime(true);
        
        try {
            $stmt = $this->connection->query($sql);
            $this->logQuery($sql, microtime(true) - $startTime);
            return $stmt;
        } catch (PDOException $e) {
            $this->logger->error('Erro na execução da query', [
                'query' => $sql,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function execute($sql, $params = []) {
        $startTime = microtime(true);
        try {
            $stmt = $this->prepare($sql);
            $stmt->execute($params);
            $this->logQuery($sql, microtime(true) - $startTime, $params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logger->error('Erro na execução do prepared statement', [
                'query' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function beginTransaction() {
        if ($this->connection->inTransaction()) {
            return; // Already in a transaction
        }
        $this->connection->beginTransaction();
        $this->logger->debug('Iniciando transação');
    }

    public function commit() {
        if ($this->connection->inTransaction()) {
            $this->connection->commit();
            $this->logger->debug('Commit realizado');
        }
    }

    public function rollback() {
        if ($this->connection->inTransaction()) {
            $this->connection->rollback();
            $this->logger->warning('Rollback realizado', [
                'last_query' => $this->lastQuery
            ]);
        }
    }

    public function quote($string) {
        return $this->connection->quote($string);
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    private function logQuery($sql, $executionTime, $params = []) {
        $this->queryCount++;
        $this->queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'time' => $executionTime,
            'timestamp' => microtime(true)
        ];

        if ($executionTime > 1) {
            $this->logger->warning('Query lenta detectada', [
                'query' => $sql,
                'execution_time' => $executionTime
            ]);
        }
    }

    public function getQueryLog() {
        return $this->queryLog;
    }

    public function getQueryCount() {
        return $this->queryCount;
    }

    public function __destruct() {
        $this->connection = null;
    }
}