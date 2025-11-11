<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';
session_destroy();
header('Location: /Contas/login.php');
exit;
