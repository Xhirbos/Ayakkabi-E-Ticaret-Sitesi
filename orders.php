<?php
// Enable all error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'dbcon.php';

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => "Siparişlerinizi görüntülemek için lütfen giriş yapın."
    ];
    
    // Set a flag to open login modal when redirected
    $_SESSION['open_login_modal'] = true;
    
    header('Location: index.php');
    exit;
}

$musteriID = $_SESSION['user']['id'];
$orders = [];
$errorMessage = '';
$successMessage = '';

// Check if there's a success toast message
if (isset($_SESSION['toast'])) {
    if ($_SESSION['toast']['type'] === 'success') {
        $successMessage = $_SESSION['toast']['message'];
    } elseif ($_SESSION['toast']['type'] === 'error') {
        $errorMessage = $_SESSION['toast']['message'];
    }
    unset($_SESSION['toast']);
}

// Get orders for the customer
try {
    $orderSql = "
        SELECT s.siparisID, s.siparisNo, s.siparisTarihi, s.toplamTutar, s.durum,
               COUNT(sd.siparisDetayID) as urunSayisi,
               a.baslik as adresBaslik, a.adres, a.il, a.ilce
        FROM siparis s
        JOIN siparisdetay sd ON s.siparisID = sd.siparisID
        JOIN musteriadres a ON s.adresID = a.adresID
        WHERE s.musteriID = ?
        GROUP BY s.siparisID
        ORDER BY s.siparisTarihi DESC
    ";
    $orderStmt = $pdo->prepare($orderSql);
    $orderStmt->execute([$musteriID]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Sipariş bilgileri alınırken hata: " . $e->getMessage());
    $errorMessage = "Sipariş bilgileri alınırken bir hata oluştu.";
}

// Get order details for a specific order
$orderDetails = [];
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $siparisID = (int)$_GET['id'];
    
    try {
        // Verify this order belongs to the logged-in customer
        $checkOrderStmt = $pdo->prepare("SELECT siparisID FROM siparis WHERE siparisID = ? AND musteriID = ?");
        $checkOrderStmt->execute([$siparisID, $musteriID]);
        
        if ($checkOrderStmt->rowCount() > 0) {
            // Get order details
            $detailSql = "
                SELECT sd.siparisDetayID, sd.urunID, sd.birimFiyat, sd.miktar, sd.toplamTutar, sd.durum,
                       u.urunAdi,
                       COALESCE(
                          (SELECT ir.resimURL FROM urunresim ir WHERE ir.urunID = sd.urunID ORDER BY ir.sira LIMIT 1),
                          'https://placehold.co/80x80/e63946/white?text=Resim+Yok'
                       ) AS resimYolu,
                       r.renkAdi, b.numara
                FROM siparisdetay sd
                JOIN urun u ON sd.urunID = u.urunID
                LEFT JOIN urunvaryant uv ON sd.varyantID = uv.varyantID
                LEFT JOIN renk r ON uv.renkID = r.renkID
                LEFT JOIN beden b ON uv.bedenID = b.bedenID
                WHERE sd.siparisID = ?
            ";
            $detailStmt = $pdo->prepare($detailSql);
            $detailStmt->execute([$siparisID]);
            $orderDetails = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get order information
            $orderInfoSql = "
                SELECT s.*, a.baslik as adresBaslik, a.adres, a.il, a.ilce, a.postaKodu, a.ulke
                FROM siparis s
                JOIN musteriadres a ON s.adresID = a.adresID
                WHERE s.siparisID = ? AND s.musteriID = ?
            ";
            $orderInfoStmt = $pdo->prepare($orderInfoSql);
            $orderInfoStmt->execute([$siparisID, $musteriID]);
            $orderInfo = $orderInfoStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $errorMessage = "Bu siparişi görüntüleme yetkiniz bulunmamaktadır.";
        }
    } catch (PDOException $e) {
        error_log("Sipariş detayları alınırken hata: " . $e->getMessage());
        $errorMessage = "Sipariş detayları alınırken bir hata oluştu.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siparişlerim - Adım Adım</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <style>
        .orders-container {
            padding: 30px 0;
        }
        .order-card {
            margin-bottom: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .order-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .order-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-date {
            color: #777;
            font-size: 0.9rem;
        }
        .order-status {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-Hazirlaniyor {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-Kargoda {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .status-TeslimEdildi {
            background-color: #d4edda;
            color: #155724;
        }
        .status-IptalEdildi {
            background-color: #f8d7da;
            color: #721c24;
        }
        .order-body {
            padding: 15px;
        }
        .order-products {
            margin-bottom: 15px;
        }
        .order-address {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
        }
        .order-total {
            font-weight: bold;
            font-size: 1.1rem;
            color: #e63946;
        }
        .order-details-btn {
            text-decoration: none;
        }
        .empty-orders {
            text-align: center;
            padding: 50px 0;
        }
        .empty-orders i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        .empty-orders p {
            color: #777;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        .order-detail-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .order-detail-item:last-child {
            border-bottom: none;
        }
        .order-detail-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        .order-detail-info {
            flex: 1;
        }
        .order-detail-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .order-detail-variant {
            color: #777;
            font-size: 0.8rem;
            margin-bottom: 5px;
        }
        .order-detail-quantity {
            color: #777;
            font-size: 0.9rem;
        }
        .order-detail-price {
            margin-left: 15px;
            text-align: right;
            font-weight: 500;
        }
        .order-info-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .order-info-title {
            font-weight: 500;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .order-info-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .order-info-list li {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .order-info-list li .label {
            color: #777;
        }
        
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
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <main class="container orders-container">
        <?php if (isset($orderInfo) && !empty($orderDetails)): ?>
            <!-- Order Details View -->
            <div class="mb-4">
                <a href="orders.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i>Tüm Siparişlerime Dön
                </a>
            </div>
            
            <h1 class="mb-4">Sipariş Detayı: <?php echo htmlspecialchars($orderInfo['siparisNo']); ?></h1>
            
            <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <!-- Order Items -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-box me-2"></i>Sipariş Ürünleri</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($orderDetails as $item): ?>
                            <div class="order-detail-item">
                                <img src="<?php echo htmlspecialchars($item['resimYolu']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['urunAdi']); ?>" 
                                     class="order-detail-image"
                                     onerror="this.src='https://placehold.co/80x80/e63946/white?text=Resim+Yok'">
                                <div class="order-detail-info">
                                    <div class="order-detail-name"><?php echo htmlspecialchars($item['urunAdi']); ?></div>
                                    <div class="order-detail-variant">
                                        Renk: <?php echo htmlspecialchars($item['renkAdi'] ?? 'N/A'); ?>, 
                                        Numara: <?php echo htmlspecialchars($item['numara'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="order-detail-quantity">
                                        <?php echo htmlspecialchars($item['miktar']); ?> Adet x 
                                        <?php echo number_format($item['birimFiyat'], 2, ',', '.'); ?> TL
                                    </div>
                                    <div class="mt-2 badge <?php echo 'status-' . htmlspecialchars($item['durum']); ?>">
                                        <?php 
                                            $statusLabels = [
                                                'Beklemede' => 'Beklemede',
                                                'Hazirlaniyor' => 'Hazırlanıyor',
                                                'Gonderildi' => 'Gönderildi',
                                                'TeslimEdildi' => 'Teslim Edildi',
                                                'IadeEdildi' => 'İade Edildi'
                                            ];
                                            echo $statusLabels[$item['durum']] ?? $item['durum'];
                                        ?>
                                    </div>
                                </div>
                                <div class="order-detail-price">
                                    <?php echo number_format($item['toplamTutar'], 2, ',', '.'); ?> TL
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Order Info -->
                    <div class="order-info-card">
                        <h5 class="order-info-title">Sipariş Bilgileri</h5>
                        <ul class="order-info-list">
                            <li>
                                <span class="label">Sipariş Numarası:</span>
                                <span class="value"><?php echo htmlspecialchars($orderInfo['siparisNo']); ?></span>
                            </li>
                            <li>
                                <span class="label">Sipariş Tarihi:</span>
                                <span class="value"><?php echo date('d.m.Y H:i', strtotime($orderInfo['siparisTarihi'])); ?></span>
                            </li>
                            <li>
                                <span class="label">Durum:</span>
                                <span class="value badge <?php echo 'status-' . htmlspecialchars($orderInfo['durum']); ?>">
                                    <?php 
                                        $orderStatusLabels = [
                                            'Hazirlaniyor' => 'Hazırlanıyor',
                                            'Kargoda' => 'Kargoda',
                                            'TeslimEdildi' => 'Teslim Edildi',
                                            'IptalEdildi' => 'İptal Edildi'
                                        ];
                                        echo $orderStatusLabels[$orderInfo['durum']] ?? $orderInfo['durum'];
                                    ?>
                                </span>
                            </li>
                            <li>
                                <span class="label">Ödeme Yöntemi:</span>
                                <span class="value">
                                    <?php 
                                        $paymentLabels = [
                                            'KrediKarti' => 'Kredi Kartı',
                                            'Havale' => 'Havale/EFT',
                                            'KapidaOdeme' => 'Kapıda Ödeme'
                                        ];
                                        echo $paymentLabels[$orderInfo['odemeYontemi']] ?? $orderInfo['odemeYontemi'];
                                    ?>
                                </span>
                            </li>
                            <?php if (!empty($orderInfo['kargoTakipNo'])): ?>
                            <li>
                                <span class="label">Kargo Takip No:</span>
                                <span class="value"><?php echo htmlspecialchars($orderInfo['kargoTakipNo']); ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <!-- Delivery Address -->
                    <div class="order-info-card">
                        <h5 class="order-info-title">Teslimat Adresi</h5>
                        <p class="mb-0">
                            <strong><?php echo htmlspecialchars($orderInfo['adresBaslik']); ?></strong><br>
                            <?php echo nl2br(htmlspecialchars($orderInfo['adres'])); ?><br>
                            <?php echo htmlspecialchars($orderInfo['ilce']); ?> / <?php echo htmlspecialchars($orderInfo['il']); ?><br>
                            <?php if (!empty($orderInfo['postaKodu'])): ?>
                                Posta Kodu: <?php echo htmlspecialchars($orderInfo['postaKodu']); ?><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($orderInfo['ulke'] ?? 'Türkiye'); ?>
                        </p>
                    </div>
                    
                    <!-- Order Total -->
                    <div class="order-info-card">
                        <h5 class="order-info-title">Sipariş Özeti</h5>
                        <ul class="order-info-list">
                            <li>
                                <span class="label">Ürünler Toplamı:</span>
                                <span class="value"><?php echo number_format($orderInfo['toplamTutar'], 2, ',', '.'); ?> TL</span>
                            </li>
                            <?php if ($orderInfo['indirimTutari'] > 0): ?>
                            <li>
                                <span class="label">İndirim:</span>
                                <span class="value">-<?php echo number_format($orderInfo['indirimTutari'], 2, ',', '.'); ?> TL</span>
                            </li>
                            <?php endif; ?>
                            <?php if ($orderInfo['kargoUcreti'] > 0): ?>
                            <li>
                                <span class="label">Kargo Ücreti:</span>
                                <span class="value"><?php echo number_format($orderInfo['kargoUcreti'], 2, ',', '.'); ?> TL</span>
                            </li>
                            <?php endif; ?>
                            <li style="font-weight: bold; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">
                                <span class="label">Toplam:</span>
                                <span class="value"><?php echo number_format($orderInfo['odemeTutari'], 2, ',', '.'); ?> TL</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Order List View -->
            <h1 class="mb-4">Siparişlerim</h1>
            
            <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (empty($orders)): ?>
                <div class="empty-orders">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>Henüz Siparişiniz Yok</h3>
                    <p>Siparişiniz bulunmamaktadır. Alışveriş yapmak için ürünleri inceleyebilirsiniz.</p>
                    <a href="index.php" class="btn btn-primary">Alışverişe Başla</a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <h5 class="mb-1">Sipariş #<?php echo htmlspecialchars($order['siparisNo']); ?></h5>
                            <div class="order-date"><?php echo date('d.m.Y H:i', strtotime($order['siparisTarihi'])); ?></div>
                        </div>
                        <div class="order-status <?php echo 'status-' . htmlspecialchars($order['durum']); ?>">
                            <?php 
                                $statusLabels = [
                                    'Hazirlaniyor' => 'Hazırlanıyor',
                                    'Kargoda' => 'Kargoda',
                                    'TeslimEdildi' => 'Teslim Edildi',
                                    'IptalEdildi' => 'İptal Edildi'
                                ];
                                echo $statusLabels[$order['durum']] ?? $order['durum'];
                            ?>
                        </div>
                    </div>
                    <div class="order-body">
                        <div class="order-products">
                            <strong><?php echo htmlspecialchars($order['urunSayisi']); ?></strong> ürün
                        </div>
                        <div class="order-address">
                            <i class="fas fa-map-marker-alt me-1"></i> 
                            <?php echo htmlspecialchars($order['adresBaslik']); ?> - 
                            <?php echo htmlspecialchars($order['ilce']); ?> / <?php echo htmlspecialchars($order['il']); ?>
                        </div>
                    </div>
                    <div class="order-footer">
                        <div class="order-total"><?php echo number_format($order['toplamTutar'], 2, ',', '.'); ?> TL</div>
                        <a href="orders.php?id=<?php echo $order['siparisID']; ?>" class="btn btn-sm btn-outline-primary order-details-btn">
                            <i class="fas fa-eye me-1"></i> Detayları Görüntüle
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html> 