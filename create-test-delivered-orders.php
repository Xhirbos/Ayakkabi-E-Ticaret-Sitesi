<?php
require_once 'dbcon.php';

echo "<h1>Test Siparişleri Oluşturucu</h1>";

try {
    $pdo->beginTransaction();
    
    // Test müşterisi oluştur veya mevcut olanı kullan
    $stmt = $pdo->prepare("SELECT musteriID FROM musteri WHERE eposta = ?");
    $stmt->execute(['test@customer.com']);
    $testCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$testCustomer) {
        $stmt = $pdo->prepare("INSERT INTO musteri (eposta, ad, soyad, sifre, telefon) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            'test@customer.com',
            'Test',
            'Müşteri',
            password_hash('test123', PASSWORD_DEFAULT),
            '5551234567'
        ]);
        $testCustomerId = $pdo->lastInsertId();
        echo "<p>✅ Test müşterisi oluşturuldu (ID: {$testCustomerId})</p>";
        
        // Test adresi oluştur
        $stmt = $pdo->prepare("INSERT INTO musteriadres (musteriID, baslik, adres, il, ilce, postaKodu, varsayilan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $testCustomerId,
            'Ev',
            'Test Mahalle, Test Sokak No:1',
            'İstanbul',
            'Kadıköy',
            '34710',
            1
        ]);
        $addressId = $pdo->lastInsertId();
        echo "<p>✅ Test adresi oluşturuldu (ID: {$addressId})</p>";
    } else {
        $testCustomerId = $testCustomer['musteriID'];
        echo "<p>ℹ️ Test müşterisi zaten mevcut (ID: {$testCustomerId})</p>";
        
        // Adres kontrolü
        $stmt = $pdo->prepare("SELECT adresID FROM musteriadres WHERE musteriID = ? LIMIT 1");
        $stmt->execute([$testCustomerId]);
        $address = $stmt->fetch(PDO::FETCH_ASSOC);
        $addressId = $address['adresID'];
    }
    
    // Ürünleri al
    $stmt = $pdo->query("SELECT urunID, urunAdi FROM urun WHERE aktif = 1 LIMIT 5");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo "<p>❌ Aktif ürün bulunamadı!</p>";
        $pdo->rollBack();
        exit;
    }
    
    echo "<h2>Teslim Edilmiş Siparişler Oluşturuluyor...</h2>";
    
    foreach ($products as $product) {
        $productId = $product['urunID'];
        $productName = $product['urunAdi'];
        
        // Bu ürün için teslim edilmiş sipariş var mı kontrol et
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM siparisdetay sd 
            JOIN siparis s ON sd.siparisID = s.siparisID 
            WHERE s.musteriID = ? AND sd.urunID = ? AND sd.durum = 'TeslimEdildi'
        ");
        $stmt->execute([$testCustomerId, $productId]);
        $deliveredOrderExists = $stmt->fetchColumn();
        
        if ($deliveredOrderExists == 0) {
            // Sipariş oluştur
            $siparisNo = 'TEST' . date('Ymd') . rand(1000, 9999);
            $stmt = $pdo->prepare("INSERT INTO siparis (siparisNo, musteriID, adresID, toplamTutar, odemeTutari, odemeYontemi, durum) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $siparisNo,
                $testCustomerId,
                $addressId,
                999.99,
                999.99,
                'KrediKarti',
                'TeslimEdildi'
            ]);
            $siparisId = $pdo->lastInsertId();
            
            // Sipariş detayı oluştur - TeslimEdildi durumunda
            $stmt = $pdo->prepare("INSERT INTO siparisdetay (siparisID, urunID, birimFiyat, miktar, toplamTutar, durum) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $siparisId,
                $productId,
                999.99,
                1,
                999.99,
                'TeslimEdildi'
            ]);
            
            echo "<p>✅ Teslim edilmiş sipariş oluşturuldu: <strong>{$productName}</strong> (Sipariş No: {$siparisNo})</p>";
        } else {
            echo "<p>ℹ️ <strong>{$productName}</strong> için zaten teslim edilmiş sipariş mevcut</p>";
        }
    }
    
    $pdo->commit();
    
    echo "<h2>🎉 İşlem Tamamlandı!</h2>";
    echo "<div style='background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>Test Bilgileri:</h3>";
    echo "<p><strong>E-posta:</strong> test@customer.com</p>";
    echo "<p><strong>Şifre:</strong> test123</p>";
    echo "<p>Bu bilgilerle giriş yaparak ürün detay sayfalarında yorum yapabilirsiniz.</p>";
    echo "</div>";
    
    echo "<h3>Sonraki Adımlar:</h3>";
    echo "<ol>";
    echo "<li>Test müşterisi ile giriş yapın (test@customer.com / test123)</li>";
    echo "<li>Herhangi bir ürün detay sayfasına gidin</li>";
    echo "<li>Sayfanın altında yorum formu görünmelidir</li>";
    echo "<li>Yorum yapabilirsiniz!</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2>❌ Hata oluştu: " . $e->getMessage() . "</h2>";
}
?> 