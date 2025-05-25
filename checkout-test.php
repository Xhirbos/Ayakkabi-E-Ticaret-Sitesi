<?php
// Enable all error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'dbcon.php';

// Debug output
echo "<h2>Debug Bilgileri</h2>";
echo "<pre>";
echo "SESSION: ";
print_r($_SESSION);
echo "\nPOST: ";
print_r($_POST);
echo "</pre>";

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo "<h1>Hata: Giriş yapılmamış!</h1>";
    echo "<p>Sipariş vermek için giriş yapmanız gerekiyor.</p>";
    echo "<a href='index.php'>Ana Sayfaya Dön</a>";
    exit;
}

$musteriID = $_SESSION['user']['id'];
$errorMessage = '';
$successMessage = '';

// Veritabanı tablolarını kontrol edelim
$tablesInfo = [];
try {
    // Müşteri bilgisini kontrol et
    $userStmt = $pdo->prepare("SELECT * FROM musteri WHERE musteriID = ?");
    $userStmt->execute([$musteriID]);
    $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    $tablesInfo['musteri'] = $userInfo ? "Bulundu (ID: {$musteriID})" : "Bulunamadı!";
    
    // Adres tablosunu kontrol et
    $addrStmt = $pdo->prepare("SELECT COUNT(*) as count FROM musteriadres WHERE musteriID = ?");
    $addrStmt->execute([$musteriID]);
    $addrCount = (int)$addrStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $tablesInfo['musteriadres'] = "Adres sayısı: " . $addrCount;
    
    // Sepet tablosunu kontrol et
    $cartStmt = $pdo->prepare("SELECT COUNT(*) as count FROM sepet WHERE musteriID = ?");
    $cartStmt->execute([$musteriID]);
    $cartCount = (int)$cartStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $tablesInfo['sepet'] = "Ürün sayısı: " . $cartCount;
    
    // Sipariş tablosunu kontrol et
    $orderStmt = $pdo->query("SHOW TABLES LIKE 'siparis'");
    $tablesInfo['siparis_tablo'] = $orderStmt->rowCount() > 0 ? "Tablo mevcut" : "Tablo bulunamadı!";
    
    // Sipariş detay tablosunu kontrol et
    $detailStmt = $pdo->query("SHOW TABLES LIKE 'siparisdetay'");
    $tablesInfo['siparisdetay_tablo'] = $detailStmt->rowCount() > 0 ? "Tablo mevcut" : "Tablo bulunamadı!";
    
    // Foreign key kısıtlamalarını kontrol et
    $constraintsQuery = "SELECT * FROM information_schema.TABLE_CONSTRAINTS 
                        WHERE CONSTRAINT_SCHEMA = 'flo' 
                        AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                        AND TABLE_NAME IN ('siparis', 'siparisdetay')";
    $constraintsStmt = $pdo->query($constraintsQuery);
    $constraints = $constraintsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $tablesInfo['foreign_keys'] = count($constraints) . " kısıtlama bulundu";
    
} catch (PDOException $e) {
    $errorMessage = "Tablo kontrolü sırasında hata: " . $e->getMessage();
}

// Process order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_complete_order'])) {
    // Get cart information
    try {
        // First check if cart has items
        $checkCartSql = "SELECT COUNT(*) as count FROM sepet WHERE musteriID = ?";
        $checkCartStmt = $pdo->prepare($checkCartSql);
        $checkCartStmt->execute([$musteriID]);
        $cartItemCount = (int)$checkCartStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($cartItemCount === 0) {
            $errorMessage = "Sepetinizde ürün bulunmamaktadır.";
        } else {
            // Simple test: check if the address exists before trying insert
            // Get the first address or use test value
            $addrStmt = $pdo->prepare("SELECT adresID FROM musteriadres WHERE musteriID = ? LIMIT 1");
            $addrStmt->execute([$musteriID]);
            $addrResult = $addrStmt->fetch(PDO::FETCH_ASSOC);
            
            $adresID = $addrResult ? $addrResult['adresID'] : null;
            
            if (!$adresID) {
                $errorMessage = "Adres bulunamadı! Lütfen önce bir adres ekleyin.";
            } else {
                // Basit test: sipariş oluştur
                $siparisNo = 'TEST' . rand(10000, 99999);
                
                // Calculate cart total
                $totalSql = "SELECT SUM(miktar * birimFiyat) as total FROM sepet WHERE musteriID = ?";
                $totalStmt = $pdo->prepare($totalSql);
                $totalStmt->execute([$musteriID]);
                $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
                $cartTotal = $totalResult ? (float)$totalResult['total'] : 0;
                
                // Begin transaction
                $pdo->beginTransaction();
                
                try {
                    // Insert order into siparis table
                    $siparisSQL = "INSERT INTO siparis (siparisNo, musteriID, adresID, siparisTarihi, toplamTutar, indirimTutari, odemeTutari, kargoUcreti, odemeYontemi, durum) VALUES (?, ?, ?, NOW(), ?, 0, ?, 0, ?, 'Hazirlaniyor')";
                    
                    $siparisStmt = $pdo->prepare($siparisSQL);
                    $siparisResult = $siparisStmt->execute([
                        $siparisNo,
                        $musteriID,
                        $adresID,
                        $cartTotal,
                        $cartTotal,
                        'KrediKarti'
                    ]);
                    
                    if (!$siparisResult) {
                        throw new PDOException("Sipariş oluşturulamadı: " . implode(", ", $siparisStmt->errorInfo()));
                    }
                    
                    $siparisID = $pdo->lastInsertId();
                    
                    // Get cart items
                    $cartSql = "SELECT * FROM sepet WHERE musteriID = ?";
                    $cartStmt = $pdo->prepare($cartSql);
                    $cartStmt->execute([$musteriID]);
                    $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Insert order details
                    $detaySQL = "INSERT INTO siparisdetay (siparisID, urunID, varyantID, birimFiyat, indirimliFiyat, miktar, toplamTutar, durum) VALUES (?, ?, ?, ?, NULL, ?, ?, 'Beklemede')";
                    $detayStmt = $pdo->prepare($detaySQL);
                    
                    foreach ($cartItems as $item) {
                        $toplamTutar = $item['miktar'] * $item['birimFiyat'];
                        $detayResult = $detayStmt->execute([
                            $siparisID,
                            $item['urunID'],
                            $item['varyantID'],
                            $item['birimFiyat'],
                            $item['miktar'],
                            $toplamTutar
                        ]);
                        
                        if (!$detayResult) {
                            throw new PDOException("Sipariş detayı eklenemedi: " . implode(", ", $detayStmt->errorInfo()));
                        }
                    }
                    
                    // Sipariş başarılı, sepeti temizle
                    $clearCartSql = "DELETE FROM sepet WHERE musteriID = ?";
                    $clearCartStmt = $pdo->prepare($clearCartSql);
                    $clearCartResult = $clearCartStmt->execute([$musteriID]);
                    
                    if (!$clearCartResult) {
                        throw new PDOException("Sepet temizlenemedi: " . implode(", ", $clearCartStmt->errorInfo()));
                    }
                    
                    // İşlemleri tamamla
                    $pdo->commit();
                    $successMessage = "Test başarılı! Sipariş veritabanına eklendi. Sipariş no: " . $siparisNo;
                    
                } catch (PDOException $e) {
                    // Hata durumunda işlemi geri al
                    $pdo->rollBack();
                    $errorMessage = "Sipariş işlemi sırasında hata: " . $e->getMessage();
                }
            }
        }
    } catch (PDOException $e) {
        $errorMessage = "Veritabanı hatası: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Test Sayfası</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Sipariş İşlemi Test Sayfası</h1>
        
        <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Veritabanı Durumu</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Tablo/Kontrol</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tablesInfo as $name => $status): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($name); ?></td>
                            <td><?php echo htmlspecialchars($status); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Sipariş Testi</h5>
                <p>Bu form, direkt olarak sipariş oluşturmayı test etmek için kullanılır.</p>
                
                <form method="post" action="checkout-test.php">
                    <input type="hidden" name="test_complete_order" value="1">
                    <button type="submit" class="btn btn-primary">Test Siparişi Oluştur</button>
                </form>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="cart-page.php" class="btn btn-secondary">Sepete Dön</a>
            <a href="checkout.php" class="btn btn-info">Normal Checkout Sayfasına Git</a>
        </div>
    </div>
</body>
</html> 