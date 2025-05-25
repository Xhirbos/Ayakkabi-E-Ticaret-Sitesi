<?php
// En basit form - JavaScript ve AJAX olmadan
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Oturum başlat
session_start();

// Veritabanı bağlantısı
$host = 'localhost';
$dbname = 'flo';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// Hata mesajları
$errors = [];
$success = '';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Kullanıcı giriş yapmış mı kontrol et
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo "Lütfen giriş yapın!";
    exit;
}

// Kullanıcı bilgileri
$musteriID = $_SESSION['user']['id'];

// Debug için kullanıcı bilgileri
echo "<div style='background:#f8f9fa;padding:10px;margin-bottom:20px;border:1px solid #ddd;'>";
echo "<h4>Kullanıcı Bilgileri:</h4>";
echo "<pre>";
print_r($_SESSION['user']);
echo "</pre>";
echo "</div>";

// Sepet ve adres bilgilerini getir
try {
    // Sepet içeriğini kontrol et
    $cartQuery = "SELECT COUNT(*) FROM sepet WHERE musteriID = ?";
    $cartStmt = $pdo->prepare($cartQuery);
    $cartStmt->execute([$musteriID]);
    $cartCount = $cartStmt->fetchColumn();
    
    if ($cartCount == 0) {
        echo "<div style='color:red;padding:10px;border:1px solid red;margin:10px 0;'>Sepetinizde ürün bulunmuyor!</div>";
        echo "<p><a href='cart-page.php'>Sepete Dön</a></p>";
        exit;
    }
    
    // Adres seçeneklerini getir
    $adresQuery = "SELECT * FROM musteriadres WHERE musteriID = ?";
    $adresStmt = $pdo->prepare($adresQuery);
    $adresStmt->execute([$musteriID]);
    $adresler = $adresStmt->fetchAll();
    
    if (empty($adresler)) {
        echo "<div style='color:red;padding:10px;border:1px solid red;margin:10px 0;'>Kayıtlı adresiniz yok. Lütfen adres ekleyin.</div>";
        echo "<p><a href='account-settings.php'>Hesap Ayarlarına Git</a></p>";
        exit;
    }
    
    // Sepet içeriğini getir
    $sepetQuery = "SELECT s.*, u.urunAdi FROM sepet s JOIN urun u ON s.urunID = u.urunID WHERE s.musteriID = ?";
    $sepetStmt = $pdo->prepare($sepetQuery);
    $sepetStmt->execute([$musteriID]);
    $sepetItems = $sepetStmt->fetchAll();
    
    // Toplam tutarı hesapla
    $totalQuery = "SELECT SUM(miktar * birimFiyat) as toplam FROM sepet WHERE musteriID = ?";
    $totalStmt = $pdo->prepare($totalQuery);
    $totalStmt->execute([$musteriID]);
    $total = $totalStmt->fetchColumn();
    
} catch (PDOException $e) {
    echo "Sorgu hatası: " . $e->getMessage();
    exit;
}

// Sipariş işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    
    $adresID = isset($_POST['adresID']) ? (int)$_POST['adresID'] : 0;
    $odemeYontemi = isset($_POST['odemeYontemi']) ? $_POST['odemeYontemi'] : '';
    
    // Adres ve ödeme yöntemi kontrolü
    if ($adresID <= 0) {
        $errors[] = "Lütfen bir teslimat adresi seçin.";
    }
    
    if (empty($odemeYontemi)) {
        $errors[] = "Lütfen bir ödeme yöntemi seçin.";
    }
    
    // Hata yoksa siparişi oluştur
    if (empty($errors)) {
        try {
            // İşlemi başlat
            $pdo->beginTransaction();
            
            // Sipariş numarası oluştur
            $siparisNo = 'OD' . rand(10000, 99999);
            
            // Siparişi ekle
            $siparisQuery = "INSERT INTO siparis (siparisNo, musteriID, adresID, siparisTarihi, toplamTutar, indirimTutari, odemeTutari, kargoUcreti, odemeYontemi, durum) VALUES (?, ?, ?, NOW(), ?, 0, ?, 0, ?, 'Hazirlaniyor')";
            $siparisStmt = $pdo->prepare($siparisQuery);
            $siparisResult = $siparisStmt->execute([
                $siparisNo,
                $musteriID,
                $adresID,
                $total,
                $total,
                $odemeYontemi
            ]);
            
            if (!$siparisResult) {
                throw new Exception("Sipariş kaydedilemedi: " . implode(", ", $siparisStmt->errorInfo()));
            }
            
            // Sipariş ID'sini al
            $siparisID = $pdo->lastInsertId();
            
            // Sipariş detaylarını ekle
            foreach ($sepetItems as $item) {
                $toplamTutar = $item['miktar'] * $item['birimFiyat'];
                
                $detayQuery = "INSERT INTO siparisdetay (siparisID, urunID, varyantID, birimFiyat, indirimliFiyat, miktar, toplamTutar, durum) VALUES (?, ?, ?, ?, NULL, ?, ?, 'Beklemede')";
                $detayStmt = $pdo->prepare($detayQuery);
                $detayResult = $detayStmt->execute([
                    $siparisID,
                    $item['urunID'],
                    $item['varyantID'],
                    $item['birimFiyat'],
                    $item['miktar'],
                    $toplamTutar
                ]);
                
                if (!$detayResult) {
                    throw new Exception("Sipariş detayı eklenemedi: " . implode(", ", $detayStmt->errorInfo()));
                }
            }
            
            // Sepeti temizle
            $clearQuery = "DELETE FROM sepet WHERE musteriID = ?";
            $clearStmt = $pdo->prepare($clearQuery);
            $clearStmt->execute([$musteriID]);
            
            // İşlemi tamamla
            $pdo->commit();
            
            $success = "Siparişiniz başarıyla oluşturuldu! Sipariş numaranız: $siparisNo";
            
        } catch (Exception $e) {
            // Hata durumunda geri al
            $pdo->rollBack();
            $errors[] = "Sipariş oluşturulurken bir hata oluştu: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doğrudan Sipariş Oluştur</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; }
        .error { color: #e74c3c; background-color: #f9e7e7; padding: 10px; margin-bottom: 15px; border: 1px solid #e74c3c; }
        .success { color: #2ecc71; background-color: #e7f9e7; padding: 10px; margin-bottom: 15px; border: 1px solid #2ecc71; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="radio"] { margin-right: 5px; }
        select, button { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background-color: #3498db; color: white; cursor: pointer; border: none; padding: 10px 15px; }
        button:hover { background-color: #2980b9; }
        .address-box { border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; }
        .payment-option { padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Doğrudan Sipariş Oluşturma</h1>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <p><a href="orders.php">Siparişlerinizi Görüntüleyin</a></p>
        <?php else: ?>
        
        <h2>Sepetinizdeki Ürünler</h2>
        <table>
            <thead>
                <tr>
                    <th>Ürün</th>
                    <th>Birim Fiyat</th>
                    <th>Miktar</th>
                    <th>Toplam</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sepetItems as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['urunAdi']); ?></td>
                    <td><?php echo number_format($item['birimFiyat'], 2, ',', '.'); ?> TL</td>
                    <td><?php echo $item['miktar']; ?></td>
                    <td><?php echo number_format($item['miktar'] * $item['birimFiyat'], 2, ',', '.'); ?> TL</td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>Genel Toplam</strong></td>
                    <td><strong><?php echo number_format($total, 2, ',', '.'); ?> TL</strong></td>
                </tr>
            </tbody>
        </table>
        
        <form method="post" action="">
            <h2>Teslimat Adresi</h2>
            <?php foreach ($adresler as $adres): ?>
            <div class="address-box">
                <label>
                    <input type="radio" name="adresID" value="<?php echo $adres['adresID']; ?>" <?php echo $adres['varsayilan'] ? 'checked' : ''; ?>>
                    <strong><?php echo htmlspecialchars($adres['baslik']); ?></strong>
                </label>
                <p><?php echo nl2br(htmlspecialchars($adres['adres'])); ?><br>
                <?php echo htmlspecialchars($adres['ilce'] . '/' . $adres['il']); ?></p>
            </div>
            <?php endforeach; ?>
            
            <h2>Ödeme Yöntemi</h2>
            <div class="payment-option">
                <label>
                    <input type="radio" name="odemeYontemi" value="KrediKarti" checked>
                    Kredi Kartı
                </label>
            </div>
            <div class="payment-option">
                <label>
                    <input type="radio" name="odemeYontemi" value="Havale">
                    Havale / EFT
                </label>
            </div>
            <div class="payment-option">
                <label>
                    <input type="radio" name="odemeYontemi" value="KapidaOdeme">
                    Kapıda Ödeme
                </label>
            </div>
            
            <button type="submit" name="create_order">Siparişi Oluştur</button>
        </form>
        
        <p style="margin-top: 20px;">
            <a href="cart-page.php">Sepete Geri Dön</a>
        </p>
        <?php endif; ?>
    </div>
</body>
</html> 