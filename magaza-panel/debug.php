<?php
// Enable all error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Output session data
echo "<h2>Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check database connection
echo "<h2>Database Connection Test:</h2>";
try {
    require_once('../dbcon.php');
    
    // Test PDO connection
    echo "PDO connection: ";
    $testStmt = $pdo->query("SELECT 1 as test");
    $result = $testStmt->fetch();
    echo "Success! Test query result: " . $result['test'] . "<br>";
    
    // Test MySQLi connection
    echo "MySQLi connection: ";
    $result = $conn->query("SELECT 1 as test");
    $row = $result->fetch_assoc();
    echo "Success! Test query result: " . $row['test'] . "<br>";
    
    // Get PHP and MySQL info
    echo "<h2>System Information:</h2>";
    echo "PHP Version: " . phpversion() . "<br>";
    $mysqlVersion = $pdo->query('select version()')->fetchColumn();
    echo "MySQL Version: " . $mysqlVersion . "<br>";
    
    // Show loaded PHP extensions
    echo "<h2>Loaded PHP Extensions:</h2>";
    $extensions = get_loaded_extensions();
    echo "<pre>";
    print_r($extensions);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check if dashboard.php exists and is readable
echo "<h2>File Access Test:</h2>";
$dashboardPath = __DIR__ . '/dashboard.php';
echo "Dashboard path: " . $dashboardPath . "<br>";
echo "File exists: " . (file_exists($dashboardPath) ? 'Yes' : 'No') . "<br>";
echo "File readable: " . (is_readable($dashboardPath) ? 'Yes' : 'No') . "<br>";
echo "File size: " . (file_exists($dashboardPath) ? filesize($dashboardPath) . ' bytes' : 'N/A') . "<br>";

// Test creating a magaza object
echo "<h2>Magaza Table Test:</h2>";
try {
    $stmt = $conn->prepare("SELECT * FROM magaza LIMIT 1");
    $stmt->execute();
    $magaza = $stmt->fetch_assoc();
    
    if ($magaza) {
        echo "Successfully retrieved a magaza record.<br>";
        echo "MagazaID: " . $magaza['magazaID'] . "<br>";
        echo "MagazaAdi: " . $magaza['magazaAdi'] . "<br>";
        echo "BasvuruDurumu: " . $magaza['basvuruDurumu'] . "<br>";
    } else {
        echo "No magaza records found.<br>";
    }
} catch (Exception $e) {
    echo "Error querying magaza table: " . $e->getMessage();
}
?> 