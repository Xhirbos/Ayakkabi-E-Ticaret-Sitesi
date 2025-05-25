<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'dbcon.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Yorum yapabilmek için giriş yapmanız gerekmektedir.'
    ]);
    exit;
}

$musteriID = $_SESSION['user']['id'];

if ($_POST['action'] === 'add_review') {
    try {
        $urunID = (int)$_POST['urunID'];
        $puan = (int)$_POST['puan'];
        $baslik = trim($_POST['baslik'] ?? '');
        $yorum = trim($_POST['yorum']);

        // Validasyon
        if ($urunID <= 0) {
            throw new Exception('Geçersiz ürün ID\'si.');
        }

        if ($puan < 1 || $puan > 5) {
            throw new Exception('Puan 1-5 arasında olmalıdır.');
        }

        if (empty($yorum)) {
            throw new Exception('Yorum içeriği boş olamaz.');
        }

        if (strlen($yorum) > 1000) {
            throw new Exception('Yorum çok uzun. Maksimum 1000 karakter olmalıdır.');
        }

        if (!empty($baslik) && strlen($baslik) > 100) {
            throw new Exception('Başlık çok uzun. Maksimum 100 karakter olmalıdır.');
        }

        // Kullanıcının bu üründen satın alıp almadığını kontrol et
        $stmtSatinAlma = $pdo->prepare("
            SELECT COUNT(*) as satinAlmaSayisi
            FROM siparisdetay sd
            JOIN siparis s ON sd.siparisID = s.siparisID
            WHERE s.musteriID = ? AND sd.urunID = ? AND sd.durum = 'TeslimEdildi'
        ");
        $stmtSatinAlma->execute([$musteriID, $urunID]);
        $satinAlmaResult = $stmtSatinAlma->fetch(PDO::FETCH_ASSOC);

        if ($satinAlmaResult['satinAlmaSayisi'] == 0) {
            throw new Exception('Bu ürün hakkında yorum yapabilmek için önce satın almanız gerekmektedir.');
        }

        // Kullanıcının bu ürün için daha önce yorum yapıp yapmadığını kontrol et
        $stmtMevcutYorum = $pdo->prepare("
            SELECT COUNT(*) as yorumSayisi
            FROM yorum
            WHERE musteriID = ? AND urunID = ?
        ");
        $stmtMevcutYorum->execute([$musteriID, $urunID]);
        $mevcutYorumResult = $stmtMevcutYorum->fetch(PDO::FETCH_ASSOC);

        if ($mevcutYorumResult['yorumSayisi'] > 0) {
            throw new Exception('Bu ürün için zaten bir yorumunuz bulunmaktadır.');
        }

        // Ürünün var olup olmadığını kontrol et
        $stmtUrun = $pdo->prepare("SELECT urunID FROM urun WHERE urunID = ? AND aktif = 1");
        $stmtUrun->execute([$urunID]);
        if (!$stmtUrun->fetch()) {
            throw new Exception('Ürün bulunamadı veya aktif değil.');
        }

        // Yorumu veritabanına ekle
        $stmtYorumEkle = $pdo->prepare("
            INSERT INTO yorum (musteriID, urunID, puan, baslik, yorum, onayDurumu, olusturmaTarihi)
            VALUES (?, ?, ?, ?, ?, 'Beklemede', NOW())
        ");
        
        $stmtYorumEkle->execute([
            $musteriID,
            $urunID,
            $puan,
            $baslik ?: null,
            $yorum
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Yorumunuz başarıyla gönderildi. Onaylandıktan sonra görüntülenecektir.'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } catch (PDOException $e) {
        error_log("Review handler PDO error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Veritabanı hatası oluştu. Lütfen tekrar deneyiniz.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz işlem.'
    ]);
}
?> 