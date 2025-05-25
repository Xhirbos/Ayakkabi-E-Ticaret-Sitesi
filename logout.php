<?php
// Start the session
session_start();

// Log the logout
if (isset($_SESSION['user'])) {
    error_log("User logged out: " . print_r($_SESSION['user'], true));
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to the home page
header("Location: index.php");
exit;
?> 