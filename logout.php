<?php
session_start();
session_unset();
session_destroy();
header("Location: ../Contas/public/login.php");
exit();
?>