<?php
// dbcon.php
$host = 'localhost';
$dbname = 'flo';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// PDO Connection
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        // Kalıcı bağlantıları kapatıyoruz, bu PHP-MySQL oturum çakışmalarını önler
        PDO::ATTR_PERSISTENT => false,
    ];

    // Bağlantı öncesi hata ayıklama
    error_log("Connecting to database: $dsn");
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Bağlantı başarılı mı test edelim
    $testStmt = $pdo->query("SELECT 1");
    if (!$testStmt) {
        throw new PDOException("Database connection test failed");
    }
    
    // MySQL önbellek ve izolasyon seviyesini ayarla
    $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    $pdo->exec("SET SESSION innodb_lock_wait_timeout = 50");
    
    // MySQL 8.0+ için transaction_isolation, eski sürümler için tx_isolation kullanılır
    try {
        $pdo->exec("SET SESSION transaction_isolation = 'READ-COMMITTED'");
    } catch (PDOException $isolationEx) {
        // Eski MySQL sürümleri için fallback
        try {
            $pdo->exec("SET SESSION tx_isolation = 'READ-COMMITTED'");
        } catch (PDOException $oldIsolationEx) {
            // Eski MySQL sürümlerinde başarısız olursa, görmezden gel
            error_log("Could not set isolation level: " . $oldIsolationEx->getMessage());
        }
    }
    
    // MySQL zaman dilimini ayarla  
    $pdo->exec("SET time_zone = '+03:00'"); // Türkiye için
    
    error_log("Database connection established and tested successfully");
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Kullanıcıya hata göstermeyelim, bu güvenlik açığına neden olabilir
    die("Veritabanı bağlantısı başarısız oldu. Lütfen daha sonra tekrar deneyin veya yönetici ile iletişime geçin.");
}

// MySQLi Connection
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset($charset);
?>