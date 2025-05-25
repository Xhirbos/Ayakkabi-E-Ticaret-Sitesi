<?php
// Force display of ALL errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Magaza Panel Error Diagnostics</h1>";

// Basic PHP info
echo "<h2>PHP Environment:</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PHP memory_limit: " . ini_get('memory_limit') . "<br>";
echo "PHP max_execution_time: " . ini_get('max_execution_time') . " seconds<br>";
echo "Display errors: " . (ini_get('display_errors') ? 'ON' : 'OFF') . "<br>";

// Check for common errors in dashboard.php
echo "<h2>Dashboard.php Inspection:</h2>";
$dashboardPath = __DIR__ . '/dashboard.php';

if (!file_exists($dashboardPath)) {
    echo "<span style='color:red'>ERROR: dashboard.php file does not exist!</span><br>";
} else {
    echo "dashboard.php exists (" . filesize($dashboardPath) . " bytes)<br>";
    
    // Read the beginning of the file to check for basic syntax
    $dashboardContent = file_get_contents($dashboardPath);
    
    // Check for common issues
    echo "<h3>Common Issues Check:</h3>";
    echo "Opening PHP tag: " . (strpos($dashboardContent, '<?php') === 0 ? 'OK' : 'MISSING!') . "<br>";
    echo "Session start: " . (strpos($dashboardContent, 'session_start') !== false ? 'Found' : 'NOT FOUND!') . "<br>";
    echo "Database include: " . (strpos($dashboardContent, "require_once('../dbcon.php')") !== false ? 'Found' : 'NOT FOUND!') . "<br>";
    
    // Check for potential parse errors by looking at file contents
    if (strpos($dashboardContent, '?>') !== false && strpos($dashboardContent, '<?php', 10) !== false) {
        echo "<span style='color:orange'>WARNING: Multiple PHP open/close tags found which can cause issues.</span><br>";
    }
}

// Database connection test
echo "<h2>Database Connection Test:</h2>";
try {
    // Try to include the database connection file
    echo "Attempting to include dbcon.php...<br>";
    @include_once('../dbcon.php');
    
    if (!isset($conn) || !$conn) {
        echo "<span style='color:red'>ERROR: \$conn variable is not defined or is empty!</span><br>";
    } else {
        echo "MySQLi connection (\$conn) exists.<br>";
        
        try {
            // Test the connection with a simple query
            $result = $conn->query("SELECT 1 as test");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "Database query successful: " . $row['test'] . "<br>";
            } else {
                echo "<span style='color:red'>ERROR: Database query failed!</span><br>";
            }
        } catch (Exception $e) {
            echo "<span style='color:red'>ERROR executing query: " . $e->getMessage() . "</span><br>";
        }
    }
    
    if (!isset($pdo) || !$pdo) {
        echo "<span style='color:red'>ERROR: \$pdo variable is not defined or is empty!</span><br>";
    } else {
        echo "PDO connection (\$pdo) exists.<br>";
    }
    
} catch (Exception $e) {
    echo "<span style='color:red'>ERROR including dbcon.php: " . $e->getMessage() . "</span><br>";
}

// Check PHP error logs
echo "<h2>Recent PHP Errors:</h2>";
$error_log_path = ini_get('error_log');
if (!$error_log_path || $error_log_path == 'syslog') {
    // Try common locations
    $possible_paths = [
        'C:/xampp/php/logs/php_error_log',
        'C:/xampp/logs/php_error_log',
        __DIR__ . '/../php_errors.log',
        __DIR__ . '/php_errors.log'
    ];
    
    $found = false;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $error_log_path = $path;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "Could not locate PHP error log file.<br>";
    }
}

if (isset($error_log_path) && file_exists($error_log_path)) {
    echo "Error log location: " . $error_log_path . "<br>";
    echo "<pre>";
    $errors = file_exists($error_log_path) ? `tail -n 20 "$error_log_path"` : "Error log file not found";
    echo htmlspecialchars($errors ?: "No recent errors found or could not read error log.");
    echo "</pre>";
} else {
    echo "Error log file not found or not readable.<br>";
}

// Attempt to manually execute a simple script like the dashboard
echo "<h2>Basic Script Execution Test:</h2>";
echo "<textarea style='width:100%; height:150px;'>";
echo "<?php
// Test script
session_start();
echo 'Session started\n';

require_once('../dbcon.php');
echo 'Database included\n';

// Test session
\$_SESSION['test'] = 'working';
echo 'Session variable set\n';

// Test database
\$result = \$conn->query('SELECT 1 as test');
\$row = \$result->fetch_assoc();
echo 'Database query result: ' . \$row['test'] . '\n';

echo 'Script finished successfully';
?>";
echo "</textarea>";

// Execute the test
echo "<h3>Execution Result:</h3>";
echo "<pre>";
try {
    // Write to temp file
    $temp_file = __DIR__ . '/temp_test.php';
    file_put_contents($temp_file, "<?php
// Test script
session_start();
echo 'Session started\n';

@include_once('../dbcon.php');
echo 'Database included\n';

// Test session
\$_SESSION['test'] = 'working';
echo 'Session variable set\n';

if (isset(\$conn)) {
    // Test database
    \$result = \$conn->query('SELECT 1 as test');
    if (\$result) {
        \$row = \$result->fetch_assoc();
        echo 'Database query result: ' . \$row['test'] . '\n';
    } else {
        echo 'Database query failed\n';
    }
} else {
    echo '\$conn is not defined\n';
}

echo 'Script finished successfully';
");

    // Execute
    ob_start();
    include($temp_file);
    $result = ob_get_clean();
    echo htmlspecialchars($result);
    
    // Clean up
    unlink($temp_file);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
echo "</pre>";

// Check session functionality
echo "<h2>Session Test:</h2>";
echo "Session status: " . session_status() . " (2 means active)<br>";
echo "Session ID: " . session_id() . "<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Final recommendations
echo "<h2>Recommendations:</h2>";
echo "<ol>";
echo "<li>Check that the dashboard.php file doesn't have any syntax errors.</li>";
echo "<li>Ensure dbcon.php is correctly setting up both \$pdo and \$conn variables.</li>";
echo "<li>Verify that the database tables (magaza, urun, urunvaryant, kategori) exist in the database.</li>";
echo "<li>Try inserting 'exit(\"testing\");' at the top of dashboard.php to see if it's being executed at all.</li>";
echo "<li>Make sure your PHP version is compatible with the code (you're running PHP " . phpversion() . ").</li>";
echo "<li>If running through XAMPP, try restarting the Apache and MySQL services.</li>";
echo "</ol>";
?> 