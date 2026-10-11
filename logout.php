<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/config/config.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Submit the logout request to the existing API endpoint.
    $logoutUrl = $URL.'/api/auth/logout.php';

    // Redirect the browser using a POST form submission.
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head><title>Logging out...</title></head>';
    echo '<body>';
    echo '<form id="logoutForm" method="POST" action="' . htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') . '">';
    echo '</form>';
    echo '<script>document.getElementById("logoutForm").submit();</script>';
    echo '</body>';
    echo '</html>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logout Now</title>
        <link href="../assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">

</head>
<body  style="text-align:center;">

<h2>Logout Now</h2>

<p>
    Are you sure you want to logout
    
</p>

<form method="POST">
    <button class="btn btn-danger" type="submit">Logout</button>
</form>

</body>
</html>