<?php

class Logger {
    private $logFile;
    private $logLevel;
    private static $instance = null;

    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';

    private function __construct() {
        $this->logFile = __DIR__ . '/../../logs/system.log';
        $this->logLevel = self::LEVEL_INFO; // Nível padrão
        
        // Criar diretório de logs se não existir
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setLogLevel($level) {
        $this->logLevel = $level;
    }

    public function debug($message, array $context = []) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    public function info($message, array $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    public function warning($message, array $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    public function error($message, array $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    private function log($level, $message, array $context = []) {
        $date = date('d-m-Y H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
        $userId = $_SESSION['usuario_id'] ?? 'Not logged in';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User Agent';

        // Formatar contexto
        $contextString = empty($context) ? '' : json_encode($context);

        // Formatar mensagem de log
        $logMessage = sprintf(
            "[%s] [%s] [IP: %s] [User: %s] %s %s\n",
            $date,
            $level,
            $ip,
            $userId,
            $message,
            $contextString
        );

        // Adicionar trace para erros
        if ($level === self::LEVEL_ERROR) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $logMessage .= "Stack Trace:\n" . print_r(array_slice($trace, 1), true) . "\n";
        }

        // Escrever no arquivo
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);

        // Para erros críticos, também notificar administrador
        if ($level === self::LEVEL_ERROR) {
            $this->notifyAdmin($logMessage);
        }
    }

    private function notifyAdmin($logMessage) {
        // Implementar notificação (email, Slack, etc.)
        // Por enquanto, apenas grava em um arquivo separado
        $adminLogFile = dirname($this->logFile) . '/critical.log';
        file_put_contents($adminLogFile, $logMessage, FILE_APPEND);
    }

    public function getRecentLogs($lines = 100) {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $logs = [];
        $file = new SplFileObject($this->logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();

        $start = max(0, $lastLine - $lines);
        $file->seek($start);

        while (!$file->eof()) {
            $logs[] = $file->fgets();
        }

        return $logs;
    }

    public function clearLogs() {
        if (file_exists($this->logFile)) {
            $backup = dirname($this->logFile) . '/system.' . date('d-m-Y-H-i-s') . '.bak.log';
            copy($this->logFile, $backup);
            file_put_contents($this->logFile, '');
            return true;
        }
        return false;
    }

    public function rotateLogs() {
        if (file_exists($this->logFile)) {
            $size = filesize($this->logFile);
            if ($size > 5 * 1024 * 1024) { // 5MB
                $backup = dirname($this->logFile) . '/system.' . date('d-m-Y-H-i-s') . '.log';
                rename($this->logFile, $backup);
                return true;
            }
        }
        return false;
    }
}
