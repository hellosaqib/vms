<?php
date_default_timezone_set('Europe/London');
//$URL 		 = "https://britdrive.swiftcodershub.com";
$URL 		 = "https://britdrive.test";
$hostname	 = "localhost";
// $dbname	 	 = "swiftcod_britdrive";
// $username	 = 'swiftcod_britdrive';
// $password	 = 'S8XMVJbaZDEYdpW3XxLr';	
$dbname	 	 = "britdrive";
$username	 = 'root';
$password	 = '';	

try { 
    $db	 = new PDO("mysql:host=$hostname;dbname=$dbname;charset=utf8mb4", $username, $password); 
    $db->exec("SET time_zone = '+00:00';"); // For UK (GMT/BST)
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize auth and user manager
include_once 'auth.php';
include_once 'user_manager.php';

$auth = new Auth($db);
$userManager = new UserManager($db);

$user = $auth->getUser();
$roleName = $auth->getRoleName();

?>