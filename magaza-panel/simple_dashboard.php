<?php
// Enable maximum error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Display a message to confirm this file is being executed
echo "<h1>Simple Dashboard Test Page</h1>";
echo "<p>If you can see this message, PHP is executing correctly.</p>";

// Test session functionality
echo "<h2>Session Test:</h2>";
session_start();
echo "Session started.<br>";

if (!isset($_SESSION['test_value'])) {
    $_SESSION['test_value'] = 'Test value: ' . time();
    echo "Session variable was not set, creating it now.<br>";
} else {
    echo "Found existing session variable: " . $_SESSION['test_value'] . "<br>";
}

// Test database connection
echo "<h2>Database Connection Test:</h2>";
try {
    require_once('../dbcon.php');
    echo "Database connection file included.<br>";
    
    if (isset($conn)) {
        echo "MySQLi connection variable exists.<br>";
        
        // Test simple query
        $result = $conn->query("SELECT 1 as test");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "Database query successful! Result: " . $row['test'] . "<br>";
        } else {
            echo "Database query failed.<br>";
        }
        
        // Test magaza table
        echo "<h3>Magaza Table Test:</h3>";
        $magazaResult = $conn->query("SHOW TABLES LIKE 'magaza'");
        if ($magazaResult->num_rows > 0) {
            echo "Magaza table exists.<br>";
            
            // Count records
            $countResult = $conn->query("SELECT COUNT(*) as total FROM magaza");
            $count = $countResult->fetch_assoc()['total'];
            echo "Found $count records in magaza table.<br>";
            
            if ($count > 0) {
                // Show first record
                $firstRecord = $conn->query("SELECT * FROM magaza LIMIT 1");
                $magaza = $firstRecord->fetch_assoc();
                echo "<pre>";
                print_r($magaza);
                echo "</pre>";
            }
        } else {
            echo "Magaza table does not exist!<br>";
        }
    } else {
        echo "ERROR: MySQLi connection variable (\$conn) is not defined!<br>";
    }
    
    if (isset($pdo)) {
        echo "PDO connection variable exists.<br>";
    } else {
        echo "ERROR: PDO connection variable (\$pdo) is not defined!<br>";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

// Test if the original dashboard.php exists
$dashboardPath = __DIR__ . '/dashboard.php';
echo "<h2>File Check:</h2>";
echo "Dashboard path: " . $dashboardPath . "<br>";
echo "File exists: " . (file_exists($dashboardPath) ? 'Yes' : 'No') . "<br>";
echo "File size: " . (file_exists($dashboardPath) ? filesize($dashboardPath) . ' bytes' : 'N/A') . "<br>";

echo "<h2>What to do next:</h2>";
echo "<ol>";
echo "<li>Check if this page displays correctly (basic PHP works)</li>";
echo "<li>Check the database connection results above</li>";
echo "<li>Visit <a href='error_checker.php'>error_checker.php</a> for more detailed diagnostics</li>";
echo "<li>After fixing any errors, try the <a href='dashboard.php'>main dashboard</a> again</li>";
echo "</ol>";
?> 