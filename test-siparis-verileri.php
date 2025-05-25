<?php
require_once('dbcon.php');

try {
    // Mevcut test verilerini temizle
    echo "Mevcut test verileri temizleniyor...\n";
    
    // Test müşterisinin siparişlerini sil
    $conn->query("DELETE sd FROM siparisdetay sd 
                  JOIN siparis s ON sd.siparisID = s.siparisID 
                  JOIN musteri m ON s.musteriID = m.musteriID 
                  WHERE m.eposta = 'test@test.com'");
    
    $conn->query("DELETE s FROM siparis s 
                  JOIN musteri m ON s.musteriID = m.musteriID 
                  WHERE m.eposta = 'test@test.com'");
    
    $conn->query("DELETE FROM musteriadres WHERE musteriID IN (SELECT musteriID FROM musteri WHERE eposta = 'test@test.com')");
    $conn->query("DELETE FROM musteri WHERE eposta = 'test@test.com'");
    
    echo "Eski test verileri temizlendi.\n";

    // Test müşteri ekle
    $testMusteriStmt = $conn->prepare("
        INSERT INTO musteri (eposta, ad, soyad, adres, sifre, telefon) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE musteriID = LAST_INSERT_ID(musteriID)
    ");
    
    $testMusteriStmt->bind_param("ssssss", 
        $eposta, $ad, $soyad, $adres, $sifre, $telefon
    );
    
    $eposta = "test@test.com";
    $ad = "Test";
    $soyad = "Müşteri";
    $adres = "Test Adres, Test Mahalle, Test/Test";
    $sifre = password_hash("123456", PASSWORD_DEFAULT);
    $telefon = "5551234567";
    
    $testMusteriStmt->execute();
    $musteriID = $conn->insert_id ?: $conn->query("SELECT musteriID FROM musteri WHERE eposta = 'test@test.com'")->fetch_assoc()['musteriID'];
    
    // Test müşteri adresi ekle
    $testAdresStmt = $conn->prepare("
        INSERT INTO musteriadres (musteriID, baslik, adres, il, ilce, postaKodu, varsayilan) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE adresID = LAST_INSERT_ID(adresID)
    ");
    
    $testAdresStmt->bind_param("isssssi", 
        $musteriID, $baslik, $adresDetay, $il, $ilce, $postaKodu, $varsayilan
    );
    
    $baslik = "Ev";
    $adresDetay = "Test Mahalle, Test Sokak No:1";
    $il = "İstanbul";
    $ilce = "Kadıköy";
    $postaKodu = "34000";
    $varsayilan = 1;
    
    $testAdresStmt->execute();
    $adresID = $conn->insert_id ?: $conn->query("SELECT adresID FROM musteriadres WHERE musteriID = $musteriID LIMIT 1")->fetch_assoc()['adresID'];
    
    // Mağazanın ürünlerini al
    $urunlerStmt = $conn->prepare("SELECT urunID, urunAdi, temelFiyat FROM urun WHERE magazaID = 4 LIMIT 3");
    $urunlerStmt->execute();
    $urunler = $urunlerStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($urunler)) {
        echo "Test için ürün bulunamadı. Önce mağaza panelinden ürün ekleyin.\n";
        exit;
    }
    
    // Test siparişleri oluştur
    for ($i = 1; $i <= 3; $i++) {
        $siparisNo = "SIP" . date('Ymd') . str_pad($i, 4, '0', STR_PAD_LEFT);
        $toplamTutar = 0;
        
        // Sipariş oluştur
        $siparisStmt = $conn->prepare("
            INSERT INTO siparis (siparisNo, musteriID, adresID, toplamTutar, odemeTutari, odemeYontemi, durum) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $durum = ['Hazirlaniyor', 'Kargoda', 'TeslimEdildi'][array_rand(['Hazirlaniyor', 'Kargoda', 'TeslimEdildi'])];
        $odemeYontemi = 'KrediKarti';
        
        // Toplam tutarı hesapla
        foreach ($urunler as $urun) {
            $toplamTutar += $urun['temelFiyat'] * rand(1, 2);
        }
        
        $siparisStmt->bind_param("siiddss", 
            $siparisNo, $musteriID, $adresID, $toplamTutar, $toplamTutar, $odemeYontemi, $durum
        );
        
        $siparisStmt->execute();
        $siparisID = $conn->insert_id;
        
        // Sipariş detayları oluştur
        foreach ($urunler as $urun) {
            $miktar = rand(1, 2);
            $birimFiyat = $urun['temelFiyat'];
            $toplamUrunTutar = $birimFiyat * $miktar;
            
            $detayStmt = $conn->prepare("
                INSERT INTO siparisdetay (siparisID, urunID, birimFiyat, miktar, toplamTutar, durum) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            // Sipariş durumuna göre detay durumunu belirle
            $detayDurum = '';
            switch ($durum) {
                case 'Hazirlaniyor':
                    $detayDurum = 'Hazirlaniyor';
                    break;
                case 'Kargoda':
                    $detayDurum = 'Gonderildi';
                    break;
                case 'TeslimEdildi':
                    $detayDurum = 'TeslimEdildi';
                    break;
                default:
                    $detayDurum = 'Beklemede';
            }
            
            $detayStmt->bind_param("iidiis", 
                $siparisID, $urun['urunID'], $birimFiyat, $miktar, $toplamUrunTutar, $detayDurum
            );
            
            $detayStmt->execute();
        }
        
        echo "Test sipariş $i oluşturuldu: $siparisNo\n";
    }
    
    echo "Test verileri başarıyla oluşturuldu!\n";
    echo "Test müşteri: test@test.com / 123456\n";
    
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}
?> 