<?php
require_once 'dbcon.php';

echo "<h1>Inserting Review Test Data</h1>";

// Start transaction
$pdo->beginTransaction();

try {
    // 1. Test müşterisi oluştur
    echo "<h2>Creating Test Customer</h2>";
    
    // Önce test müşterisinin var olup olmadığını kontrol et
    $stmt = $pdo->prepare("SELECT musteriID FROM musteri WHERE eposta = ?");
    $stmt->execute(['test@customer.com']);
    $testCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$testCustomer) {
        $stmt = $pdo->prepare("INSERT INTO musteri (eposta, ad, soyad, adres, sifre, telefon) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'test@customer.com',
            'Test',
            'Müşteri',
            'Test Adres, Test Mahalle, Test İlçe, Test İl',
            password_hash('test123', PASSWORD_DEFAULT),
            '5551234567'
        ]);
        $testCustomerId = $pdo->lastInsertId();
        echo "<p>Created test customer with ID: {$testCustomerId}</p>";
    } else {
        $testCustomerId = $testCustomer['musteriID'];
        echo "<p>Test customer already exists with ID: {$testCustomerId}</p>";
    }

    // 2. Test müşterisi için adres oluştur
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM musteriadres WHERE musteriID = ?");
    $stmt->execute([$testCustomerId]);
    $addressCount = $stmt->fetchColumn();
    
    if ($addressCount == 0) {
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
        echo "<p>Created test address with ID: {$addressId}</p>";
    } else {
        $stmt = $pdo->prepare("SELECT adresID FROM musteriadres WHERE musteriID = ? LIMIT 1");
        $stmt->execute([$testCustomerId]);
        $addressId = $stmt->fetchColumn();
        echo "<p>Test address already exists with ID: {$addressId}</p>";
    }

    // 3. Ürünleri al
    $stmt = $pdo->query("SELECT urunID, urunAdi FROM urun LIMIT 3");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo "<p>No products found. Please run insert-test-data.php first.</p>";
        $pdo->rollBack();
        exit;
    }

    // 4. Her ürün için test siparişi oluştur (sadece yoksa)
    foreach ($products as $product) {
        $productId = $product['urunID'];
        $productName = $product['urunAdi'];
        
        // Bu ürün için sipariş var mı kontrol et
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM siparisdetay sd 
            JOIN siparis s ON sd.siparisID = s.siparisID 
            WHERE s.musteriID = ? AND sd.urunID = ?
        ");
        $stmt->execute([$testCustomerId, $productId]);
        $orderExists = $stmt->fetchColumn();
        
        if ($orderExists == 0) {
            // Sipariş oluştur
            $siparisNo = 'TST' . date('Ymd') . rand(1000, 9999);
            $stmt = $pdo->prepare("INSERT INTO siparis (siparisNo, musteriID, adresID, toplamTutar, odemeTutari, odemeYontemi, durum) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $siparisNo,
                $testCustomerId,
                $addressId,
                1299.99,
                1299.99,
                'KrediKarti',
                'TeslimEdildi'
            ]);
            $siparisId = $pdo->lastInsertId();
            
            // Sipariş detayı oluştur
            $stmt = $pdo->prepare("INSERT INTO siparisdetay (siparisID, urunID, birimFiyat, miktar, toplamTutar, durum) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $siparisId,
                $productId,
                1299.99,
                1,
                1299.99,
                'TeslimEdildi'
            ]);
            
            echo "<p>Created test order for product: {$productName}</p>";
        } else {
            echo "<p>Test order already exists for product: {$productName}</p>";
        }
    }

    // 5. Test yorumları oluştur
    echo "<h2>Creating Test Reviews</h2>";
    
    $testReviews = [
        [
            'urunID' => $products[0]['urunID'],
            'puan' => 5,
            'baslik' => 'Mükemmel ayakkabı!',
            'yorum' => 'Bu ayakkabıyı çok beğendim. Hem rahat hem de şık. Kalitesi gerçekten çok iyi, tavsiye ederim.',
            'onayDurumu' => 'Onaylandi'
        ],
        [
            'urunID' => $products[0]['urunID'],
            'puan' => 4,
            'baslik' => 'Güzel ürün',
            'yorum' => 'Genel olarak memnunum. Sadece biraz dar geldi, bir numara büyük alınabilir.',
            'onayDurumu' => 'Onaylandi'
        ]
    ];

    // İkinci müşteri oluştur
    $stmt = $pdo->prepare("SELECT musteriID FROM musteri WHERE eposta = ?");
    $stmt->execute(['test2@customer.com']);
    $testCustomer2 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$testCustomer2) {
        $stmt = $pdo->prepare("INSERT INTO musteri (eposta, ad, soyad, adres, sifre, telefon) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'test2@customer.com',
            'Ahmet',
            'Yılmaz',
            'Test Adres 2, Test Mahalle, Test İlçe, Test İl',
            password_hash('test123', PASSWORD_DEFAULT),
            '5559876543'
        ]);
        $testCustomerId2 = $pdo->lastInsertId();
        echo "<p>Created second test customer with ID: {$testCustomerId2}</p>";
        
        // İkinci müşteri için adres
        $stmt = $pdo->prepare("INSERT INTO musteriadres (musteriID, baslik, adres, il, ilce, postaKodu, varsayilan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $testCustomerId2,
            'Ev',
            'Test Mahalle 2, Test Sokak No:2',
            'Ankara',
            'Çankaya',
            '06100',
            1
        ]);
        $addressId2 = $pdo->lastInsertId();
        
        // İkinci müşteri için sipariş
        $siparisNo2 = 'TST' . date('Ymd') . rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO siparis (siparisNo, musteriID, adresID, toplamTutar, odemeTutari, odemeYontemi, durum) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $siparisNo2,
            $testCustomerId2,
            $addressId2,
            1199.99,
            1199.99,
            'KrediKarti',
            'TeslimEdildi'
        ]);
        $siparisId2 = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO siparisdetay (siparisID, urunID, birimFiyat, miktar, toplamTutar, durum) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $siparisId2,
            $products[0]['urunID'],
            1199.99,
            1,
            1199.99,
            'TeslimEdildi'
        ]);
    } else {
        $testCustomerId2 = $testCustomer2['musteriID'];
        echo "<p>Second test customer already exists with ID: {$testCustomerId2}</p>";
    }

    // Yorumları ekle
    foreach ($testReviews as $index => $review) {
        $customerId = ($index == 0) ? $testCustomerId : $testCustomerId2;
        
        // Bu müşterinin bu ürün için yorumu var mı kontrol et
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM yorum WHERE musteriID = ? AND urunID = ?");
        $stmt->execute([$customerId, $review['urunID']]);
        $reviewExists = $stmt->fetchColumn();
        
        if ($reviewExists == 0) {
            $stmt = $pdo->prepare("INSERT INTO yorum (musteriID, urunID, puan, baslik, yorum, onayDurumu) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $customerId,
                $review['urunID'],
                $review['puan'],
                $review['baslik'],
                $review['yorum'],
                $review['onayDurumu']
            ]);
            
            $reviewId = $pdo->lastInsertId();
            echo "<p>Created review with ID: {$reviewId} for product: {$products[0]['urunAdi']}</p>";
        } else {
            echo "<p>Review already exists for customer ID: {$customerId} and product: {$products[0]['urunAdi']}</p>";
        }
    }

    // Commit transaction
    $pdo->commit();
    echo "<h2>✅ All test review data inserted successfully!</h2>";
    echo "<p>You can now test the review system with:</p>";
    echo "<ul>";
    echo "<li>Email: test@customer.com, Password: test123</li>";
    echo "<li>Email: test2@customer.com, Password: test123</li>";
    echo "</ul>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2>❌ Error occurred: " . $e->getMessage() . "</h2>";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<h2>❌ Database error: " . $e->getMessage() . "</h2>";
}
?> 