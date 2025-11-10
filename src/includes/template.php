<?php
 // Includes the header
 require_once __DIR__ . '/header.php';
 
// Include breadcrumbs
// Don't show breadcrumbs on login/logout pages
$is_auth_page = in_array(basename($_SERVER['PHP_SELF']), ['login.php', 'logout.php']);
if (!$is_auth_page) {
     require_once __DIR__ . '/breadcrumbs.php';
}
 
 // Outputs the content passed to the template
 if (isset($content)) {
     echo $content;
 }

?>