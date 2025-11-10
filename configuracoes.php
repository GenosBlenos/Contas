<?php
// Arquivo central de configurações do sistema

// Configurações do Banco de Dados
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'compras');
}

// URL da API de Machine Learning
if (!defined('ML_API_URL')) {
    define('ML_API_URL', 'http://localhost:5001');
}