<?php
// Start the session
session_start();

// Display information about session
echo "<h1>Session Diagnostics</h1>";

echo "<h2>Session Information</h2>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . session_status() . " (1=PHP_SESSION_NONE, 2=PHP_SESSION_ACTIVE)</p>";
echo "<p><strong>Session Cookies Enabled:</strong> " . (ini_get('session.use_cookies') ? 'Yes' : 'No') . "</p>";

echo "<h2>Session Contents</h2>";
if (!empty($_SESSION)) {
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
} else {
    echo "<p>Session is empty</p>";
}

// Display cookie information
echo "<h2>Cookies</h2>";
if (!empty($_COOKIE)) {
    echo "<pre>";
    print_r($_COOKIE);
    echo "</pre>";
} else {
    echo "<p>No cookies found</p>";
}

// Display server information
echo "<h2>Server Information</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<p><strong>HTTP User Agent:</strong> " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "</p>";

// Session configuration
echo "<h2>Session Configuration</h2>";
echo "<ul>";
echo "<li><strong>session.save_path:</strong> " . ini_get('session.save_path') . "</li>";
echo "<li><strong>session.name:</strong> " . ini_get('session.name') . "</li>";
echo "<li><strong>session.cookie_lifetime:</strong> " . ini_get('session.cookie_lifetime') . " seconds</li>";
echo "<li><strong>session.cookie_path:</strong> " . ini_get('session.cookie_path') . "</li>";
echo "<li><strong>session.cookie_domain:</strong> " . ini_get('session.cookie_domain') . "</li>";
echo "<li><strong>session.cookie_secure:</strong> " . (ini_get('session.cookie_secure') ? 'Yes' : 'No') . "</li>";
echo "<li><strong>session.cookie_httponly:</strong> " . (ini_get('session.cookie_httponly') ? 'Yes' : 'No') . "</li>";
echo "<li><strong>session.cookie_samesite:</strong> " . ini_get('session.cookie_samesite') . "</li>";
echo "</ul>";

echo "<h2>Actions</h2>";
echo "<a href='logout.php'>Logout (clear session)</a> | ";
echo "<a href='index.php'>Go to Home Page</a> | ";
echo "<a href='register-check.php'>Registration Check</a>";
?> 