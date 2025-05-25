<?php
ob_start(); // Start output buffering to prevent header issues
session_start();
// Set proper character encoding
header('Content-Type: text/html; charset=utf-8');
require_once 'dbcon.php'; // Veritabanı bağlantısı

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId === 0) {
    die("Geçerli bir ürün ID'si belirtilmedi!");
}

$product = null;

try {
    // 1. Temel Ürün Bilgilerini Çek
    $stmt = $pdo->prepare("SELECT urunID, urunAdi, urunAciklama, temelFiyat FROM urun WHERE urunID = ? AND aktif = 1");
    $stmt->execute([$productId]);
    $urunTemel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$urunTemel) {
        die("Ürün bulunamadı veya aktif değil!");
    }

    $product = [
        'id' => $urunTemel['urunID'],
        'name' => $urunTemel['urunAdi'],
        'description' => $urunTemel['urunAciklama'],
        'price' => $urunTemel['temelFiyat'], // İndirimli fiyat varsa onu da yönetmek gerekebilir
        'image' => 'https://placehold.co/600x600/CCCCCC/333333?text=Resim+Yok', // Varsayılan
        'thumbnails' => [],
        'variants' => []
    ];

    // 2. Ürün Resimlerini Çek
    $stmtImg = $pdo->prepare("SELECT resimURL, anaResim FROM urunresim WHERE urunID = ? ORDER BY anaResim DESC, sira ASC");
    $stmtImg->execute([$productId]);
    $images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

    $mainImageSet = false;
    foreach ($images as $img) {
        if ($img['anaResim'] && !$mainImageSet) {
            $product['image'] = htmlspecialchars($img['resimURL']);
            $mainImageSet = true;
        }
        // Ana resmi thumbnail listesine de ekleyebiliriz veya ayrı tutabiliriz.
        // Şimdilik tüm resimleri thumbnail olarak ekleyelim, ana resmi ilk thumbnail yapabiliriz.
        $product['thumbnails'][] = htmlspecialchars($img['resimURL']);
    }
    // Eğer hiç resim yoksa ve ana resim set edilmemişse, placehold kalır.
    // Eğer resimler var ama hiçbiri ana resim olarak işaretlenmemişse, ilk resmi ana resim yap
    if (!$mainImageSet && !empty($product['thumbnails'])) {
        $product['image'] = $product['thumbnails'][0];
    }
     // Eğer thumbnail listesi boşsa ve ana resim placeholder değilse, ana resmi thumbnail olarak ekle
    if (empty($product['thumbnails']) && $product['image'] !== 'https://placehold.co/600x600/CCCCCC/333333?text=Resim+Yok') {
        $product['thumbnails'][] = $product['image'];
    }

    // 2.5 Renk tablosundan renk kodlarını çekelim - farklı sorgu ile tüm renkleri alabiliriz
    $stmtRenk = $pdo->prepare("SELECT renkID, renkAdi, renkKodu FROM renk");
    $stmtRenk->execute();
    $renkler = $stmtRenk->fetchAll(PDO::FETCH_ASSOC);
    $renkKodlari = [];
    
    foreach ($renkler as $renk) {
        $renkKodlari[$renk['renkAdi']] = $renk['renkKodu'];
    }

    // 3. Ürün Varyantlarını (Renk, Beden, Stok) Çek
    $stmtVar = $pdo->prepare("
        SELECT
            r.renkAdi,
            r.renkKodu,
            b.numara AS bedenNumarasi,
            uv.stokMiktari
        FROM
            urunvaryant uv
        JOIN
            renk r ON uv.renkID = r.renkID
        JOIN
            beden b ON uv.bedenID = b.bedenID
        WHERE
            uv.urunID = ? AND uv.durum = 'Aktif'
        ORDER BY
            r.renkAdi, b.numara
    ");
    $stmtVar->execute([$productId]);
    $variantsData = $stmtVar->fetchAll(PDO::FETCH_ASSOC);

    foreach ($variantsData as $var) {
        $renk = htmlspecialchars($var['renkAdi']);
        // bedenNumarasi decimal(4,1) olduğu için .0 gelebilir, tam sayıya çevirelim veya string olarak bırakalım.
        // Şimdilik string bırakıyorum, ondalık gerekirse JS tarafında parse edilebilir.
        $beden = htmlspecialchars($var['bedenNumarasi']); 
        $stok = (int)$var['stokMiktari'];
        $renkKodu = $renkKodlari[$renk] ?? $var['renkKodu'] ?? '#000000'; // Önce renkKodlari dizisinden, yoksa varianttan, o da yoksa default

        if (!isset($product['variants'][$renk])) {
            $product['variants'][$renk] = [
                'sizes' => [],
                'colorCode' => $renkKodu
            ];
        }
        $product['variants'][$renk]['sizes'][(string)$beden] = $stok; // Beden numarasını string key olarak kullan
    }

    // 4. Ürün Yorumlarını Çek (Sadece onaylanmış yorumlar)
    $stmtYorumlar = $pdo->prepare("
        SELECT 
            y.yorumID,
            y.puan,
            y.baslik,
            y.yorum,
            y.yanit,
            y.yanitTarihi,
            y.olusturmaTarihi,
            m.ad,
            m.soyad
        FROM yorum y
        JOIN musteri m ON y.musteriID = m.musteriID
        WHERE y.urunID = ? AND y.onayDurumu = 'Onaylandi'
        ORDER BY y.olusturmaTarihi DESC
    ");
    $stmtYorumlar->execute([$productId]);
    $yorumlar = $stmtYorumlar->fetchAll(PDO::FETCH_ASSOC);

    // 5. Kullanıcının bu üründen satın alıp alamayacağını kontrol et
    $kullaniciSatinAlabilirMi = false;
    if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
        $musteriID = $_SESSION['user']['id'];
        
        // Sadece teslim edilmiş siparişleri kontrol et
        $stmtTeslimEdilmis = $pdo->prepare("
            SELECT COUNT(*) as teslimEdilmisSayisi
            FROM siparisdetay sd
            JOIN siparis s ON sd.siparisID = s.siparisID
            WHERE s.musteriID = ? AND sd.urunID = ? AND sd.durum = 'TeslimEdildi'
        ");
        $stmtTeslimEdilmis->execute([$musteriID, $productId]);
        $teslimEdilmisResult = $stmtTeslimEdilmis->fetch(PDO::FETCH_ASSOC);
        
        $kullaniciSatinAlabilirMi = $teslimEdilmisResult['teslimEdilmisSayisi'] > 0;
    }

    // 6. Ortalama puan hesapla
    $ortalamaPuan = 0;
    $toplamYorum = count($yorumlar);
    if ($toplamYorum > 0) {
        $toplamPuan = array_sum(array_column($yorumlar, 'puan'));
        $ortalamaPuan = round($toplamPuan / $toplamYorum, 1);
    }

} catch (PDOException $e) {
    // Gerçek uygulamada burada daha detaylı hata kaydı/gösterimi yapılmalı
    die("Veritabanı sorgu hatası: " . $e->getMessage());
}

if (!$product) { // Bu kontrol yukarıda yapıldı ama yine de kalsın
    die("Ürün bilgileri yüklenirken bir sorun oluştu!");
}

/* 
 * Login and registration are now handled by login-handler.php through AJAX.
 * The old direct form processing code has been removed.
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Adım Adım</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional styles specific to product-detail page can go here or in style.css */
        .product-detail-container { padding-top: 20px; padding-bottom: 20px; }
        .product-image-main { max-width: 100%; height: auto; max-height: 500px; object-fit: contain; border-radius: 8px; margin-bottom: 20px; border: 1px solid #eee; }
        .product-thumbnails { display: flex; flex-wrap: wrap; gap: 10px; }
        .product-thumbnails img { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 1px solid #ddd; padding: 2px; }
        .product-thumbnails img.active { border-color: #e63946; box-shadow: 0 0 5px rgba(230, 57, 70, 0.5); }
        .product-info h1 { font-size: 28px; margin-bottom: 15px; color: #333; }
        .product-price-detail { font-size: 24px; color: #e63946; margin-bottom: 20px; font-weight: bold; }
        .product-description { margin-bottom: 20px; color: #555; line-height: 1.6; }
        .variant-selector label { font-weight: bold; margin-bottom: 8px; display: block; }
        .color-options .btn-group, .size-options .btn-group { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 5px;}
        .size-options .btn { border-color: #ccc; margin: 0 !important; /* Bootstrap'in .btn-group > .btn margin'lerini sıfırla */}
        .size-options .btn.active { background-color: #e63946; color: white; border-color: #e63946; }
        
        /* Color buttons container */
        .color-options .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        /* Completely independent color button style */
        .color-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #ccc;
            position: relative;
            cursor: pointer;
            padding: 0;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: none;
        }
        
        .color-btn.active {
            border: 2px solid #e63946;
            box-shadow: 0 0 0 2px rgba(230, 57, 70, 0.5);
        }
        
        /* Checkmark for active color button */
        .color-btn.active::after {
            content: '\2713'; /* Unicode checkmark character */
            color: white;
            text-shadow: 0 0 1px black, 0 0 2px black; /* Make visible on all color backgrounds */
            font-size: 18px;
            font-weight: bold;
        }
        
        /* White outline for dark colors */
        .color-btn[style*="background-color: #000"],
        .color-btn[style*="background-color: #000000"],
        .color-btn[style*="background-color: rgb(0, 0, 0)"],
        .color-btn[style*="background-color: black"] {
            box-shadow: 0 0 0 1px white inset;
        }
        
        /* Light colors special styles */
        .color-btn[style*="background-color: #fff"],
        .color-btn[style*="background-color: #ffffff"],
        .color-btn[style*="background-color: rgb(255, 255, 255)"],
        .color-btn[style*="background-color: white"] {
            border-color: #ccc;
        }
        
        .color-btn[style*="background-color: #fff"].active::after,
        .color-btn[style*="background-color: #ffffff"].active::after,
        .color-btn[style*="background-color: rgb(255, 255, 255)"].active::after,
        .color-btn[style*="background-color: white"].active::after {
            color: #333;
            text-shadow: none;
        }
        
        .color-btn .color-name {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 10px;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.2s;
            pointer-events: none;
            z-index: 10;
        }
        
        .color-btn:hover .color-name {
            opacity: 1;
        }
        .stock-info { padding: 15px; background-color: #f0f0f0; border-radius: 4px; margin-bottom: 20px; min-height: 50px; display: flex; align-items: center;}
        .stock-info p { margin-bottom: 0; font-weight: 500; }
        .stock-available { color: #28a745; }
        .stock-unavailable { color: #dc3545; }
        .out-of-stock-text {text-decoration: line-through; color: #999;} /* Bedenler için tükenmiş görünümü */

        /* Account dropdown styles */
        .header-button {
            position: relative;
        }
        
        .account-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 200px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 100;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .header-button:hover .account-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .account-dropdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .account-dropdown ul li {
            border-bottom: 1px solid #f1f1f1;
        }
        
        .account-dropdown ul li:last-child {
            border-bottom: none;
        }
        
        .account-dropdown ul li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .account-dropdown ul li a:hover {
            background: #f9f9f9;
            color: #e63946;
        }
        
        .account-dropdown ul li a i {
            margin-right: 10px;
            color: #e63946;
            width: 16px;
            text-align: center;
        }
        
        /* Toast styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 10px;
            overflow: hidden;
            max-width: 350px;
            transform: translateX(100%);
            opacity: 0;
            transition: transform 0.3s, opacity 0.3s;
        }
        
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .toast.success .toast-icon {
            background-color: #4CAF50;
        }
        
        .toast.error .toast-icon {
            background-color: #F44336;
        }
        
        .toast.warning .toast-icon {
            background-color: #FF9800;
        }
        
        .toast-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .toast-content {
            padding: 12px;
            flex-grow: 1;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: #999;
            font-size: 18px;
            cursor: pointer;
            padding: 10px;
        }
        
        .toast-close:hover {
            color: #333;
        }

        /* Yorum Sistemi CSS */
        .reviews-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }
        
        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .reviews-summary {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .average-rating {
            font-size: 2.5rem;
            font-weight: bold;
            color: #e63946;
        }
        
        .stars-display {
            color: #ffc107;
            font-size: 1.5rem;
        }
        
        .review-count {
            color: #666;
            font-size: 0.9rem;
        }
        
        .review-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .star-rating input[type="radio"] {
            display: none;
        }
        
        .star-rating label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .star-rating input[type="radio"]:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffc107;
        }
        
        .review-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .reviewer-name {
            font-weight: bold;
            color: #333;
        }
        
        .review-date {
            color: #666;
            font-size: 0.85rem;
        }
        
        .review-rating {
            color: #ffc107;
            font-size: 1.1rem;
        }
        
        .review-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .review-content {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .review-reply {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #e63946;
            margin-top: 15px;
        }
        
        .reply-header {
            font-weight: bold;
            color: #e63946;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .reply-content {
            color: #555;
            font-size: 0.9rem;
        }
        
        .no-reviews {
            text-align: center;
            padding: 40px;
            color: #666;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .purchase-required {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .btn-review {
            background-color: #e63946;
            border-color: #e63946;
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-review:hover {
            background-color: #d32f2f;
            border-color: #d32f2f;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <main class="container product-detail-container">
        <div class="row">
            <!-- Product Images -->
            <div class="col-lg-6 col-md-12 mb-4 mb-lg-0">
                <img src="<?php echo $product['image']; // htmlspecialchars zaten PHP içinde yapıldı ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image-main" id="mainProductImage">
                <?php if (!empty($product['thumbnails']) && count($product['thumbnails']) > 1): ?>
                <div class="product-thumbnails mt-3">
                    <?php foreach ($product['thumbnails'] as $thumb): ?>
                        <img src="<?php echo $thumb; // htmlspecialchars zaten PHP içinde yapıldı ?>" alt="Thumbnail <?php echo htmlspecialchars($product['name']); ?>" onclick="changeMainImage(this, '<?php echo $thumb; ?>')">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Product Info -->
            <div class="col-lg-6 col-md-12">
                <div class="product-info">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    <div class="product-price-detail"><?php echo number_format((float)$product['price'], 2, ',', '.'); ?> TL</div>
                    <p class="product-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

                    <!-- Color Selector -->
                    <?php if (!empty($product['variants'])): ?>
                    <div class="variant-selector color-options">
                        <label for="colorSelect">Renk Seçin:</label>
                        <div class="btn-group" role="group" aria-label="Color options" id="colorSelect">
                            <?php 
                                $firstColor = true;
                                foreach (array_keys($product['variants']) as $colorName): 
                                    $colorCode = $product['variants'][$colorName]['colorCode'] ?? '#000000';
                            ?>
                                <button type="button" class="color-btn <?php if($firstColor) { echo 'active'; $firstColor = false; } ?>" 
                                        data-color="<?php echo $colorName; ?>" 
                                        style="background-color: <?php echo $colorCode; ?>">
                                    <span class="color-name"><?php echo $colorName; ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Size Selector -->
                    <div class="variant-selector size-options">
                        <label for="sizeSelect">Numara Seçin:</label>
                        <div class="btn-group" role="group" aria-label="Size options" id="sizeSelect">
                            <?php // Sizes will be populated by JavaScript based on selected color ?>
                        </div>
                    </div>
                    
                    <!-- Stock Info -->
                    <div class="stock-info" id="stockInfoContainer">
                        <p>Lütfen renk ve numara seçiniz.</p>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-warning">Bu ürün için henüz renk/beden seçeneği veya stok bilgisi girilmemiştir.</div>
                    <?php endif; ?>


                    <!-- Quantity and Add to Cart -->
                    <div class="d-flex align-items-center mb-3 mt-3">
                        <label for="quantity" class="me-2 visually-hidden">Adet:</label>
                        <input type="number" id="quantity" class="form-control me-3" value="1" min="1" style="width: 80px;" aria-label="Adet">
                        <button class="btn btn-primary btn-lg flex-grow-1" id="addToCartBtn" type="button"><i class="fas fa-shopping-cart me-2"></i>Sepete Ekle</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Yorum Sistemi -->
        <div class="reviews-section">
            <div class="reviews-header">
                <div class="reviews-summary">
                    <?php if ($toplamYorum > 0): ?>
                        <div class="average-rating"><?php echo $ortalamaPuan; ?></div>
                        <div>
                            <div class="stars-display">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= floor($ortalamaPuan)) {
                                        echo '<i class="fas fa-star"></i>';
                                    } elseif ($i <= $ortalamaPuan) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <div class="review-count"><?php echo $toplamYorum; ?> değerlendirme</div>
                        </div>
                    <?php else: ?>
                        <div>
                            <h3>Ürün Değerlendirmeleri</h3>
                            <div class="review-count">Henüz değerlendirme yapılmamış</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Yorum Yazma Formu -->
            <?php if (isset($_SESSION['user'])): ?>
                <?php if ($kullaniciSatinAlabilirMi): ?>
                    <div class="review-form">
                        <h4>Değerlendirme Yazın</h4>
                        <form id="review-form">
                            <input type="hidden" name="urunID" value="<?php echo $productId; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Puanınız:</label>
                                <div class="star-rating">
                                    <input type="radio" name="puan" value="5" id="star5">
                                    <label for="star5">★</label>
                                    <input type="radio" name="puan" value="4" id="star4">
                                    <label for="star4">★</label>
                                    <input type="radio" name="puan" value="3" id="star3">
                                    <label for="star3">★</label>
                                    <input type="radio" name="puan" value="2" id="star2">
                                    <label for="star2">★</label>
                                    <input type="radio" name="puan" value="1" id="star1">
                                    <label for="star1">★</label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="review-title" class="form-label">Başlık (İsteğe bağlı):</label>
                                <input type="text" class="form-control" id="review-title" name="baslik" maxlength="100">
                            </div>
                            
                            <div class="mb-3">
                                <label for="review-content" class="form-label">Yorumunuz:</label>
                                <textarea class="form-control" id="review-content" name="yorum" rows="4" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-review">
                                <i class="fas fa-paper-plane me-2"></i>Değerlendirme Gönder
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="purchase-required">
                        <i class="fas fa-info-circle me-2"></i>
                        Bu ürün hakkında yorum yapabilmek için önce satın almanız gerekmektedir.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="purchase-required">
                    <i class="fas fa-info-circle me-2"></i>
                    Yorum yapabilmek için <a href="#" onclick="$('#login-modal').css('display', 'flex');">giriş yapmanız</a> gerekmektedir.
                </div>
            <?php endif; ?>

            <!-- Yorumlar Listesi -->
            <div class="reviews-list">
                <?php if (!empty($yorumlar)): ?>
                    <?php foreach ($yorumlar as $yorum): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <div class="reviewer-name">
                                        <?php echo htmlspecialchars($yorum['ad'] . ' ' . substr($yorum['soyad'], 0, 1) . '.'); ?>
                                    </div>
                                    <div class="review-date">
                                        <?php echo date('d.m.Y', strtotime($yorum['olusturmaTarihi'])); ?>
                                    </div>
                                </div>
                                <div class="review-rating">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $yorum['puan']) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($yorum['baslik'])): ?>
                                <div class="review-title"><?php echo htmlspecialchars($yorum['baslik']); ?></div>
                            <?php endif; ?>
                            
                            <div class="review-content">
                                <?php echo nl2br(htmlspecialchars($yorum['yorum'])); ?>
                            </div>
                            
                            <?php if (!empty($yorum['yanit'])): ?>
                                <div class="review-reply">
                                    <div class="reply-header">
                                        Mağaza Yanıtı 
                                        <?php if ($yorum['yanitTarihi']): ?>
                                            - <?php echo date('d.m.Y', strtotime($yorum['yanitTarihi'])); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="reply-content">
                                        <?php echo nl2br(htmlspecialchars($yorum['yanit'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-reviews">
                        <i class="fas fa-comments fa-3x mb-3" style="color: #ddd;"></i>
                        <h5>Henüz değerlendirme yapılmamış</h5>
                        <p>Bu ürün için ilk değerlendirmeyi siz yapın!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <!-- Login and Registration Modals -->
    <?php if (!isset($_SESSION['user'])): ?>
    <div id="login-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" id="close-login">&times;</span>
            <h2>Giriş Yap</h2>
            <div class="error-messages" id="login-error-container" style="display:none;">
                <p class="error" id="login-error-message"></p>
            </div>
            <form id="login-form" method="post">
                <div class="form-group">
                    <label for="login-email">E-posta Adresi</label>
                    <input type="email" id="login-email" name="email" value="<?php echo isset($_SESSION['login_email']) ? htmlspecialchars($_SESSION['login_email']) : ''; ?>" placeholder="E-posta adresinizi girin" required>
                    <?php unset($_SESSION['login_email']); ?>
                </div>
                <div class="form-group">
                    <label for="login-password">Şifre</label>
                    <input type="password" id="login-password" name="password" placeholder="Şifrenizi girin" required>
                </div>
                <button type="submit">Giriş Yap</button>
            </form>
            <p>Hesabınız yok mu? <a href="#" id="open-register">Üye Ol</a></p>
        </div>
    </div>
    <div id="register-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" id="close-register">&times;</span>
            <h2>Üye Ol</h2>
            <div class="error-messages" id="register-error-container" style="display:none;">
                <p class="error" id="register-error-message"></p>
            </div>
            <form id="register-form" method="post" autocomplete="off">
                <div class="form-group">
                    <label for="register-ad">Ad</label>
                    <input type="text" id="register-ad" name="ad" value="<?php echo isset($_SESSION['register_data']['ad']) ? htmlspecialchars($_SESSION['register_data']['ad']) : ''; ?>" placeholder="Adınızı girin" required>
                </div>
                <div class="form-group">
                    <label for="register-soyad">Soyad</label>
                    <input type="text" id="register-soyad" name="soyad" value="<?php echo isset($_SESSION['register_data']['soyad']) ? htmlspecialchars($_SESSION['register_data']['soyad']) : ''; ?>" placeholder="Soyadınızı girin" required>
                </div>
                <div class="form-group">
                    <label for="register-email">E-posta Adresi</label>
                    <input type="email" id="register-email" name="email" value="<?php echo isset($_SESSION['register_data']['email']) ? htmlspecialchars($_SESSION['register_data']['email']) : ''; ?>" placeholder="E-posta adresinizi girin" required>
                </div>
                <div class="form-group">
                    <label for="register-telefon">Telefon</label>
                    <input type="tel" id="register-telefon" name="telefon" value="<?php echo isset($_SESSION['register_data']['telefon']) ? htmlspecialchars($_SESSION['register_data']['telefon']) : ''; ?>" placeholder="Telefon numaranızı girin" required>
                </div>
                <div class="form-group">
                    <label for="register-password">Şifre</label>
                    <input type="password" id="register-password" name="password" placeholder="Şifrenizi girin" required>
                </div>
                <button type="submit" id="register-submit-btn">Üye Ol</button>
            </form>
            <p>Zaten hesabınız var mı? <a href="#" id="open-login2">Giriş Yap</a></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Authentication JS -->
    <script src="auth.js"></script>
    <!-- Custom JavaScript -->
    <script>
        // Ürün detay işlemleri 
        const productVariantsData = <?php echo json_encode($product['variants']); ?>;
        let selectedColor = document.querySelector('#colorSelect .color-btn.active')?.dataset.color;
        let selectedSize = null;

        function changeMainImage(thumbElement, newSrc) {
            document.getElementById('mainProductImage').src = newSrc;
            document.querySelectorAll('.product-thumbnails img').forEach(img => img.classList.remove('active'));
            thumbElement.classList.add('active');
        }

        function updateSizeOptions() {
            const sizeSelectContainer = document.getElementById('sizeSelect');
            sizeSelectContainer.innerHTML = ''; 
            selectedSize = null; // Renk değişince beden seçimini sıfırla

            if (selectedColor && productVariantsData[selectedColor]) {
                const sizeData = productVariantsData[selectedColor].sizes;
                Object.keys(sizeData).forEach(size => {
                    const stockForSize = sizeData[size];
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-outline-secondary';
                    button.dataset.size = size;
                    button.textContent = size;
                    if (stockForSize === 0) {
                        button.classList.add('out-of-stock-text');
                        button.disabled = true; // İsteğe bağlı: Tükenen bedeni tıklanamaz yap
                    }
                    sizeSelectContainer.appendChild(button);
                });
                
                document.querySelectorAll('#sizeSelect .btn:not([disabled])').forEach(button => {
                    button.addEventListener('click', function() {
                        document.querySelectorAll('#sizeSelect .btn').forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');
                        selectedSize = this.dataset.size;
                        updateStockInfo();
                    });
                });
            }
            updateStockInfo(); 
        }

        function updateStockInfo() {
            const stockInfoContainer = document.getElementById('stockInfoContainer');
            const addToCartBtn = document.getElementById('addToCartBtn');
            addToCartBtn.disabled = true; // Default olarak butonu disable et

            if (selectedColor && selectedSize) {
                const stockLevel = productVariantsData[selectedColor]?.sizes?.[selectedSize];
                if (stockLevel !== undefined) {
                    if (stockLevel > 0) {
                        stockInfoContainer.innerHTML = `<p class="stock-available">Stokta ${stockLevel} adet mevcut.</p>`;
                        addToCartBtn.disabled = false;
                    } else {
                        stockInfoContainer.innerHTML = '<p class="stock-unavailable">Bu numara için stok bulunmamaktadır.</p>';
                    }
                } else { // Bu durum normalde olmamalı, bedenler productVariantsData'dan geliyor
                    stockInfoContainer.innerHTML = '<p class="stock-unavailable">Stok bilgisi bulunamadı.</p>';
                }
            } else if (selectedColor) {
                 stockInfoContainer.innerHTML = '<p>Lütfen numara seçiniz.</p>';
            } else if (Object.keys(productVariantsData).length > 0) { // Varyant var ama renk seçilmemiş (ilk yükleme)
                 stockInfoContainer.innerHTML = '<p>Lütfen renk seçiniz.</p>';
            }
             else { // Hiç varyant yoksa
                stockInfoContainer.innerHTML = ''; // Mesajı kaldır ya da "Stokta yok" gibi genel bir mesaj göster
                // addToCartBtn.disabled = true; // zaten true
            }
        }

        document.querySelectorAll('#colorSelect .color-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('#colorSelect .color-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                selectedColor = this.dataset.color;
                
                updateSizeOptions(); // Renk değiştiğinde bedenleri ve sonra stok bilgisini güncelle
            });
        });

        // Sayfa ilk yüklendiğinde
        if (Object.keys(productVariantsData).length > 0) {
            if(selectedColor) { // Eğer PHP'den aktif bir renk geldiyse
                updateSizeOptions();
            } else { // PHP'den aktif renk gelmediyse (normalde ilk renk aktif gelir)
                 updateStockInfo(); // Sadece stok mesajını güncelle
            }
        } else {
             updateStockInfo(); // Hiç varyant yoksa uygun mesajı göster
        }
        
        // Activate first thumbnail if available
        const firstThumbnail = document.querySelector('.product-thumbnails img');
        if (firstThumbnail) {
            firstThumbnail.classList.add('active');
        }

        // Initialize tooltips for color buttons
        document.querySelectorAll('.color-btn').forEach(btn => {
            const colorName = btn.dataset.color;
            btn.title = colorName;
        });

        // Sepete ekle butonu için event listener
        document.getElementById('addToCartBtn').addEventListener('click', function() {
            if (selectedColor && selectedSize) {
                <?php if (isset($_SESSION['user']) && isset($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] > 0): ?>
                // Get selected variant ID
                const productId = <?php echo $product['id']; ?>;
                const color = selectedColor;
                const size = selectedSize;
                const quantity = parseInt(document.getElementById('quantity').value) || 1;
                const price = <?php echo $product['price']; ?>;
                
                // Get variant ID (this would normally come from a database query)
                // For demo purposes, let's use an AJAX call to get it
                $.ajax({
                    url: 'get-variant-id.php',
                    type: 'POST',
                    data: {
                        productId: productId,
                        color: color,
                        size: size
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.variantId) {
                            // Add to cart using AJAX
                            $.ajax({
                                url: 'cart.php',
                                type: 'POST',
                                data: {
                                    action: 'add',
                                    urunID: productId,
                                    varyantID: response.variantId,
                                    miktar: quantity,
                                    fiyat: price
                                },
                                dataType: 'json',
                                success: function(cartResponse) {
                                    if (cartResponse.success) {
                                        // Show success message
                                        showToast('Ürün sepete eklendi', 'success');
                                        
                                        // Update cart count in header
                                        updateCartCount(cartResponse.cartCount);
                                        
                                        // Refresh cart preview in header if function exists
                                        if (typeof window.refreshCartPreview === 'function') {
                                            window.refreshCartPreview();
                                        }
                                    } else {
                                        // Kullanıcı hesabı hatası için özel işlem
                                        if (cartResponse.message && cartResponse.message.includes('Geçerli bir kullanıcı hesabı bulunamadı')) {
                                            showToast(cartResponse.message + ' <a href="logout.php" style="color: white; text-decoration: underline;">Yeniden giriş yapmak için tıklayın</a>', 'error');
                                        } else if (cartResponse.message && cartResponse.message.includes('Stokta sadece')) {
                                            // Stok hatası için özel işlem
                                            showToast(cartResponse.message, 'warning');
                                            
                                            // Stokta sadece X adet var mesajından sayıyı çıkart
                                            const stockMatch = cartResponse.message.match(/Stokta sadece (\d+) adet/);
                                            if (stockMatch && stockMatch[1]) {
                                                const availableStock = parseInt(stockMatch[1]);
                                                // Stok bilgisini güncelle
                                                if (availableStock > 0) {
                                                    document.getElementById('stockInfoContainer').innerHTML = 
                                                        `<p class="stock-available">Stokta ${availableStock} adet mevcut.</p>`;
                                                }
                                            }
                                        } else {
                                            showToast(cartResponse.message || 'Sepete eklerken bir hata oluştu', 'error');
                                        }
                                    }
                                },
                                error: function() {
                                    showToast('Sepete eklerken bir hata oluştu', 'error');
                                }
                            });
                        } else {
                            showToast('Seçilen varyant bulunamadı', 'error');
                        }
                    },
                    error: function() {
                        showToast('Seçilen varyant bulunamadı', 'error');
                    }
                });
                <?php else: ?>
                // Save selected variant info to localStorage before showing login modal
                const variantInfo = {
                    productId: <?php echo $product['id']; ?>,
                    color: selectedColor,
                    size: selectedSize,
                    quantity: parseInt(document.getElementById('quantity').value) || 1,
                    timestamp: Date.now()
                };
                localStorage.setItem('pendingCartItem', JSON.stringify(variantInfo));
                
                // Show login modal if user is not logged in
                showToast('Sepete eklemek için lütfen giriş yapın', 'error');
                $('#login-modal').css('display', 'flex');
                <?php endif; ?>
            } else {
                showToast('Lütfen renk ve numara seçiniz', 'error');
            }
        });
        
        // Function to update cart count in the header
        function updateCartCount(count) {
            const cartCountElement = document.querySelector('.cart-count');
            if (cartCountElement) {
                cartCountElement.textContent = count;
            } else {
                const cartLink = document.querySelector('.cart-link');
                if (cartLink) {
                    const countSpan = document.createElement('span');
                    countSpan.className = 'cart-count';
                    countSpan.textContent = count;
                    cartLink.appendChild(countSpan);
                }
            }
        }

        // Clear existing toasts
        function clearAllToasts() {
            const container = document.querySelector('.toast-container');
            if (container) {
                while (container.firstChild) {
                    container.removeChild(container.firstChild);
                }
            }
        }
        
        // Check for pending cart item after login
        function checkPendingCartItem() {
            <?php if (isset($_SESSION['user']) && isset($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] > 0): ?>
            const pendingItem = localStorage.getItem('pendingCartItem');
            if (pendingItem) {
                try {
                    const variantInfo = JSON.parse(pendingItem);
                    
                    // Check if the pending item is for this product and not too old (5 minutes)
                    if (variantInfo.productId === <?php echo $product['id']; ?> && 
                        (Date.now() - variantInfo.timestamp) < 300000) {
                        
                        // Restore the variant selection
                        selectedColor = variantInfo.color;
                        selectedSize = variantInfo.size;
                        
                        // Update UI to reflect the selection
                        document.querySelectorAll('#colorSelect .color-btn').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.color === selectedColor) {
                                btn.classList.add('active');
                            }
                        });
                        
                        updateSizeOptions();
                        
                        // Wait a bit for UI to update, then select the size
                        setTimeout(() => {
                            document.querySelectorAll('#sizeSelect .btn').forEach(btn => {
                                btn.classList.remove('active');
                                if (btn.dataset.size === selectedSize) {
                                    btn.classList.add('active');
                                }
                            });
                            updateStockInfo();
                            
                            // Set quantity
                            document.getElementById('quantity').value = variantInfo.quantity;
                            
                            // Auto-add to cart
                            setTimeout(() => {
                                addToCartAutomatically(variantInfo);
                            }, 500);
                        }, 100);
                    }
                    
                    // Clear the pending item regardless
                    localStorage.removeItem('pendingCartItem');
                } catch (e) {
                    console.error('Error processing pending cart item:', e);
                    localStorage.removeItem('pendingCartItem');
                }
            }
            <?php endif; ?>
        }
        
        // Function to automatically add item to cart
        function addToCartAutomatically(variantInfo) {
            const productId = variantInfo.productId;
            const color = variantInfo.color;
            const size = variantInfo.size;
            const quantity = variantInfo.quantity;
            const price = <?php echo $product['price']; ?>;
            
            // Get variant ID
            $.ajax({
                url: 'get-variant-id.php',
                type: 'POST',
                data: {
                    productId: productId,
                    color: color,
                    size: size
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.variantId) {
                        // Add to cart using AJAX
                        $.ajax({
                            url: 'cart.php',
                            type: 'POST',
                            data: {
                                action: 'add',
                                urunID: productId,
                                varyantID: response.variantId,
                                miktar: quantity,
                                fiyat: price
                            },
                            dataType: 'json',
                            success: function(cartResponse) {
                                if (cartResponse.success) {
                                    // Show success message
                                    showToast('Giriş yaptınız ve ürün sepete eklendi!', 'success');
                                    
                                    // Update cart count in header
                                    updateCartCount(cartResponse.cartCount);
                                    
                                    // Refresh cart preview in header if function exists
                                    if (typeof window.refreshCartPreview === 'function') {
                                        window.refreshCartPreview();
                                    }
                                } else {
                                    if (cartResponse.message && cartResponse.message.includes('Stokta sadece')) {
                                        showToast(cartResponse.message, 'warning');
                                    } else {
                                        showToast(cartResponse.message || 'Sepete eklerken bir hata oluştu', 'error');
                                    }
                                }
                            },
                            error: function() {
                                showToast('Sepete eklerken bir hata oluştu', 'error');
                            }
                        });
                    } else {
                        showToast('Seçilen varyant bulunamadı', 'error');
                    }
                },
                error: function() {
                    showToast('Seçilen varyant bulunamadı', 'error');
                }
            });
        }
        
        // Call checkPendingCartItem when page loads
        $(document).ready(function() {
            checkPendingCartItem();
            
            // Clean up old localStorage items (older than 5 minutes)
            cleanupOldPendingItems();
        });
        
        // Function to clean up old pending cart items
        function cleanupOldPendingItems() {
            try {
                const pendingItem = localStorage.getItem('pendingCartItem');
                if (pendingItem) {
                    const variantInfo = JSON.parse(pendingItem);
                    // If item is older than 5 minutes, remove it
                    if (Date.now() - variantInfo.timestamp > 300000) {
                        localStorage.removeItem('pendingCartItem');
                        console.log('Cleaned up old pending cart item');
                    }
                }
            } catch (e) {
                // If there's any error parsing, just remove the item
                localStorage.removeItem('pendingCartItem');
                console.log('Cleaned up corrupted pending cart item');
            }
        }

        // Yorum Sistemi JavaScript
        // Yıldız rating sistemi
        $('.star-rating input[type="radio"]').change(function() {
            const rating = $(this).val();
            $('.star-rating label').removeClass('selected');
            $(this).nextAll('label').addClass('selected');
        });

        // Yorum formu gönderimi
        $('#review-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                action: 'add_review',
                urunID: $('input[name="urunID"]').val(),
                puan: $('input[name="puan"]:checked').val(),
                baslik: $('#review-title').val(),
                yorum: $('#review-content').val()
            };

            // Puan seçimi kontrolü
            if (!formData.puan) {
                showToast('Lütfen bir puan seçiniz', 'error');
                return;
            }

            // Yorum içeriği kontrolü
            if (!formData.yorum.trim()) {
                showToast('Lütfen yorumunuzu yazınız', 'error');
                return;
            }

            // Form butonunu devre dışı bırak
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Gönderiliyor...');

            $.ajax({
                url: 'review-handler.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast('Yorumunuz başarıyla gönderildi. Onaylandıktan sonra görüntülenecektir.', 'success');
                        
                        // Formu temizle
                        $('#review-form')[0].reset();
                        $('.star-rating label').removeClass('selected');
                        $('input[name="puan"]').prop('checked', false);
                    } else {
                        showToast(response.message || 'Yorum gönderilirken bir hata oluştu', 'error');
                    }
                },
                error: function() {
                    showToast('Yorum gönderilirken bir hata oluştu', 'error');
                },
                complete: function() {
                    // Form butonunu tekrar aktif et
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); // End output buffering ?> 