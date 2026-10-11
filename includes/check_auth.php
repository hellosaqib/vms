<?php
require_once 'config.php';

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check if user has permission to access this page
$requiredPermission = $requiredPermission ?? null;
if ($requiredPermission && !$auth->hasPermission($requiredPermission)) {
    header('HTTP/1.0 403 Forbidden');
    echo "You don't have permission to access this page.";
    exit;
} 
?>