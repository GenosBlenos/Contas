<?php

class SecurityManager {
    private static $instance = null;
    private $logger;
    private $failedLogins = [];
    private $blockedIPs = [];
    private $sessionTimeout = 1800; // 30 minutos
    private $csrfToken;

    private function __construct() {
        $this->logger = Logger::getInstance();
        $this->initializeSession();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configurar cookies seguros
            $cookieParams = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => $cookieParams['lifetime'],
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            session_start();
        }

        // Regenerar ID da sessão periodicamente
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutos
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }

        // Verificar timeout da sessão
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $this->sessionTimeout)) {
            $this->destroySession();
        }
        $_SESSION['last_activity'] = time();

        // Inicializar token CSRF se não existir
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $this->csrfToken = $_SESSION['csrf_token'];
    }

    public function validateCSRF($token) {
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            $this->logger->warning('CSRF token inválido detectado', [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
            return false;
        }
        return true;
    }

    public function getCSRFToken() {
        return $this->csrfToken;
    }

    public function validateLoginAttempt($email, $ip) {
        $key = $email . '_' . $ip;
        
        if (isset($this->failedLogins[$key])) {
            $attempts = $this->failedLogins[$key];
            if ($attempts['count'] >= 5 && (time() - $attempts['time']) < 900) { // 15 minutos
                $this->logger->warning('Múltiplas tentativas de login detectadas', [
                    'email' => $email,
                    'ip' => $ip
                ]);
                return false;
            }
        }
        
        return true;
    }

    public function recordFailedLogin($email, $ip) {
        $key = $email . '_' . $ip;
        
        if (!isset($this->failedLogins[$key])) {
            $this->failedLogins[$key] = ['count' => 0, 'time' => time()];
        }
        
        $this->failedLogins[$key]['count']++;
        $this->failedLogins[$key]['time'] = time();

        $this->logger->warning('Falha no login', [
            'email' => $email,
            'ip' => $ip,
            'attempt' => $this->failedLogins[$key]['count']
        ]);
    }

    public function validateInput($input, $type = 'text') {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT);
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT);
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL);
            case 'ip':
                return filter_var($input, FILTER_VALIDATE_IP);
            default:
                return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        }
    }

    public function sanitizeArray($array) {
        return array_map(function($item) {
            if (is_array($item)) {
                return $this->sanitizeArray($item);
            }
            return $this->validateInput($item);
        }, $array);
    }

    public function validatePassword($password) {
        // Mínimo 8 caracteres, pelo menos uma letra maiúscula, uma minúscula, um número e um caractere especial
        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
        return preg_match($pattern, $password);
    }

    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function destroySession() {
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }

    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    public function isSecureConnection() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $_SERVER['SERVER_PORT'] == 443;
    }

    public function enforceSecureConnection() {
        if (!$this->isSecureConnection()) {
            $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('Location: ' . $redirect);
            exit();
        }
    }

    public function addSecurityHeaders() {
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net; img-src \'self\' data:; font-src \'self\' https://cdn.jsdelivr.net');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}
