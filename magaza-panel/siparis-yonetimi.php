<?php
// Enable all error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();
require_once('../dbcon.php');

// Oturum kontrolü
if (!isset($_SESSION['magazaID'])) {
    header('Location: index.php');
    exit;
}

$magazaID = $_SESSION['magazaID'];
$message = '';
$messageType = '';

// Kargo takip numarası güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_cargo') {
        $siparisID = (int)$_POST['siparisID'];
        $kargoTakipNo = trim($_POST['kargoTakipNo']);
        $durum = $_POST['durum'];
        
        try {
            // Önce siparişin bu mağazaya ait olduğunu kontrol et
            $checkStmt = $conn->prepare("
                SELECT s.siparisID 
                FROM siparis s
                JOIN siparisdetay sd ON s.siparisID = sd.siparisID
                JOIN urun u ON sd.urunID = u.urunID
                WHERE s.siparisID = ? AND u.magazaID = ?
                LIMIT 1
            ");
            $checkStmt->bind_param("ii", $siparisID, $magazaID);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                // Sipariş durumunu güncelle
                $updateStmt = $conn->prepare("
                    UPDATE siparis 
                    SET kargoTakipNo = ?, durum = ? 
                    WHERE siparisID = ?
                ");
                $updateStmt->bind_param("ssi", $kargoTakipNo, $durum, $siparisID);
                
                if ($updateStmt->execute()) {
                    // Sipariş detaylarındaki ürün durumlarını da güncelle
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
                        case 'IptalEdildi':
                            $detayDurum = 'IadeEdildi';
                            break;
                        default:
                            $detayDurum = 'Beklemede';
                    }
                    
                    // Bu mağazanın ürünleri için sipariş detaylarını güncelle
                    $updateDetayStmt = $conn->prepare("
                        UPDATE siparisdetay sd
                        JOIN urun u ON sd.urunID = u.urunID
                        SET sd.durum = ?
                        WHERE sd.siparisID = ? AND u.magazaID = ?
                    ");
                    $updateDetayStmt->bind_param("sii", $detayDurum, $siparisID, $magazaID);
                    $updateDetayStmt->execute();
                    
                    $message = "Sipariş durumu ve ürün durumları başarıyla güncellendi.";
                    $messageType = "success";
                } else {
                    $message = "Güncelleme sırasında bir hata oluştu.";
                    $messageType = "danger";
                }
            } else {
                $message = "Bu siparişi güncelleme yetkiniz bulunmamaktadır.";
                $messageType = "danger";
            }
        } catch (Exception $e) {
            $message = "Bir hata oluştu: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Mağazanın ürünlerinin sipariş bilgilerini getir
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            s.siparisID,
            s.siparisNo,
            s.siparisTarihi,
            s.toplamTutar,
            s.durum as siparisDurum,
            s.kargoTakipNo,
            m.ad as musteriAd,
            m.soyad as musteriSoyad,
            m.eposta as musteriEposta,
            COUNT(sd.siparisDetayID) as urunSayisi,
            GROUP_CONCAT(DISTINCT u.urunAdi SEPARATOR ', ') as urunler
        FROM siparis s
        JOIN siparisdetay sd ON s.siparisID = sd.siparisID
        JOIN urun u ON sd.urunID = u.urunID
        JOIN musteri m ON s.musteriID = m.musteriID
        WHERE u.magazaID = ?
        GROUP BY s.siparisID
        ORDER BY s.siparisTarihi DESC
    ");
    $stmt->bind_param("i", $magazaID);
    $stmt->execute();
    $result = $stmt->get_result();
    $siparisler = [];
    while ($row = $result->fetch_assoc()) {
        $siparisler[] = $row;
    }
    
    // Her sipariş için detayları da getir
    foreach ($siparisler as &$siparis) {
        $detayStmt = $conn->prepare("
            SELECT sd.*, u.urunAdi, u.temelFiyat,
                   CASE 
                       WHEN sd.varyantID IS NOT NULL THEN CONCAT(r.renkAdi, ' - ', b.numara, ' (', b.ulkeSistemi, ')')
                       ELSE 'Standart'
                   END as varyantBilgi
            FROM siparisdetay sd
            JOIN urun u ON sd.urunID = u.urunID
            LEFT JOIN urunvaryant uv ON sd.varyantID = uv.varyantID
            LEFT JOIN renk r ON uv.renkID = r.renkID
            LEFT JOIN beden b ON uv.bedenID = b.bedenID
            WHERE sd.siparisID = ? AND u.magazaID = ?
            ORDER BY u.urunAdi
        ");
        $detayStmt->bind_param("ii", $siparis['siparisID'], $magazaID);
        $detayStmt->execute();
        $detayResult = $detayStmt->get_result();
        $siparis['detaylar'] = [];
        while ($detayRow = $detayResult->fetch_assoc()) {
            $siparis['detaylar'][] = $detayRow;
        }
    }
} catch (Exception $e) {
    error_log("Sipariş listesi hatası: " . $e->getMessage());
    $siparisler = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Yönetimi - Mağaza Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
        }
        .sidebar-sticky {
            height: calc(100vh - 48px);
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #fff;
            padding: 0.75rem 1rem;
        }
        .sidebar .nav-link:hover {
            color: #f8f9fa;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        main {
            padding-top: 60px;
        }
        .navbar {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .order-card {
            border-radius: 10px;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .order-card:hover {
            transform: translateY(-2px);
        }
        .cargo-form {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Mağaza Yönetim Paneli</a>
            <div class="d-flex">
                <span class="navbar-text me-3">
                    Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['magazaAdi']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Çıkış Yap</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home"></i> Ana Sayfa
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="urunler.php">
                                <i class="fas fa-box"></i> Ürünler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="urun-ekle.php">
                                <i class="fas fa-plus-circle"></i> Ürün Ekle
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="urunler.php">
                                <i class="fas fa-tags"></i> Varyantlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="siparis-yonetimi.php">
                                <i class="fas fa-shipping-fast"></i> Sipariş Yönetimi
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Sipariş Yönetimi</h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($siparisler)): ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        Henüz ürünlerinize ait sipariş bulunmamaktadır.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($siparisler as $siparis): ?>
                            <div class="col-12">
                                <div class="card order-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-0">Sipariş #<?php echo htmlspecialchars($siparis['siparisNo']); ?></h5>
                                            <small class="text-muted">
                                                <?php echo date('d.m.Y H:i', strtotime($siparis['siparisTarihi'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php
                                            $statusClass = '';
                                            $statusText = '';
                                            switch ($siparis['siparisDurum']) {
                                                case 'Hazirlaniyor':
                                                    $statusClass = 'bg-warning text-dark';
                                                    $statusText = 'Hazırlanıyor';
                                                    break;
                                                case 'Kargoda':
                                                    $statusClass = 'bg-info text-white';
                                                    $statusText = 'Kargoda';
                                                    break;
                                                case 'TeslimEdildi':
                                                    $statusClass = 'bg-success text-white';
                                                    $statusText = 'Teslim Edildi';
                                                    break;
                                                case 'IptalEdildi':
                                                    $statusClass = 'bg-danger text-white';
                                                    $statusText = 'İptal Edildi';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-secondary text-white';
                                                    $statusText = $siparis['siparisDurum'];
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?> status-badge">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-user me-2"></i>Müşteri Bilgileri</h6>
                                                <p class="mb-1">
                                                    <strong><?php echo htmlspecialchars($siparis['musteriAd'] . ' ' . $siparis['musteriSoyad']); ?></strong>
                                                </p>
                                                <p class="mb-1 text-muted">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($siparis['musteriEposta']); ?>
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-box me-2"></i>Sipariş Detayları</h6>
                                                <p class="mb-1">
                                                    <strong>Toplam Tutar:</strong> 
                                                    <?php echo number_format($siparis['toplamTutar'], 2, ',', '.'); ?> TL
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Ürün Sayısı:</strong> <?php echo $siparis['urunSayisi']; ?>
                                                </p>
                                                <?php if ($siparis['kargoTakipNo']): ?>
                                                    <p class="mb-1">
                                                        <strong>Kargo Takip No:</strong> 
                                                        <code><?php echo htmlspecialchars($siparis['kargoTakipNo']); ?></code>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <h6><i class="fas fa-list me-2"></i>Ürünler</h6>
                                            <p class="text-muted mb-0">
                                                <?php echo htmlspecialchars($siparis['urunler']); ?>
                                            </p>
                                        </div>

                                        <!-- Sipariş Detayları -->
                                        <?php if (!empty($siparis['detaylar'])): ?>
                                            <div class="mt-3">
                                                <h6><i class="fas fa-box-open me-2"></i>Sipariş Detayları</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Ürün</th>
                                                                <th>Varyant</th>
                                                                <th>Adet</th>
                                                                <th>Birim Fiyat</th>
                                                                <th>Toplam</th>
                                                                <th>Durum</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($siparis['detaylar'] as $detay): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($detay['urunAdi']); ?></td>
                                                                    <td><?php echo htmlspecialchars($detay['varyantBilgi']); ?></td>
                                                                    <td><?php echo $detay['miktar']; ?></td>
                                                                    <td><?php echo number_format($detay['birimFiyat'], 2, ',', '.'); ?> TL</td>
                                                                    <td><?php echo number_format($detay['toplamTutar'], 2, ',', '.'); ?> TL</td>
                                                                    <td>
                                                                        <?php
                                                                        $detayStatusClass = '';
                                                                        $detayStatusText = '';
                                                                        switch ($detay['durum']) {
                                                                            case 'Beklemede':
                                                                                $detayStatusClass = 'bg-secondary text-white';
                                                                                $detayStatusText = 'Beklemede';
                                                                                break;
                                                                            case 'Hazirlaniyor':
                                                                                $detayStatusClass = 'bg-warning text-dark';
                                                                                $detayStatusText = 'Hazırlanıyor';
                                                                                break;
                                                                            case 'Gonderildi':
                                                                                $detayStatusClass = 'bg-info text-white';
                                                                                $detayStatusText = 'Gönderildi';
                                                                                break;
                                                                            case 'TeslimEdildi':
                                                                                $detayStatusClass = 'bg-success text-white';
                                                                                $detayStatusText = 'Teslim Edildi';
                                                                                break;
                                                                            case 'IadeEdildi':
                                                                                $detayStatusClass = 'bg-danger text-white';
                                                                                $detayStatusText = 'İade Edildi';
                                                                                break;
                                                                            default:
                                                                                $detayStatusClass = 'bg-light text-dark';
                                                                                $detayStatusText = $detay['durum'];
                                                                        }
                                                                        ?>
                                                                        <span class="badge <?php echo $detayStatusClass; ?> status-badge">
                                                                            <?php echo $detayStatusText; ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Kargo Güncelleme Formu -->
                                        <div class="cargo-form">
                                            <h6><i class="fas fa-shipping-fast me-2"></i>Sipariş Durumu Güncelle</h6>
                                            <form method="POST" class="row g-3">
                                                <input type="hidden" name="action" value="update_cargo">
                                                <input type="hidden" name="siparisID" value="<?php echo $siparis['siparisID']; ?>">
                                                
                                                <div class="col-md-4">
                                                    <label for="durum_<?php echo $siparis['siparisID']; ?>" class="form-label">Durum</label>
                                                    <select class="form-select" name="durum" id="durum_<?php echo $siparis['siparisID']; ?>" required>
                                                        <option value="Hazirlaniyor" <?php echo $siparis['siparisDurum'] === 'Hazirlaniyor' ? 'selected' : ''; ?>>Hazırlanıyor</option>
                                                        <option value="Kargoda" <?php echo $siparis['siparisDurum'] === 'Kargoda' ? 'selected' : ''; ?>>Kargoda</option>
                                                        <option value="TeslimEdildi" <?php echo $siparis['siparisDurum'] === 'TeslimEdildi' ? 'selected' : ''; ?>>Teslim Edildi</option>
                                                        <option value="IptalEdildi" <?php echo $siparis['siparisDurum'] === 'IptalEdildi' ? 'selected' : ''; ?>>İptal Edildi</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-5">
                                                    <label for="kargoTakipNo_<?php echo $siparis['siparisID']; ?>" class="form-label">Kargo Takip Numarası</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="kargoTakipNo" 
                                                           id="kargoTakipNo_<?php echo $siparis['siparisID']; ?>"
                                                           value="<?php echo htmlspecialchars($siparis['kargoTakipNo'] ?? ''); ?>"
                                                           placeholder="Kargo takip numarasını girin">
                                                </div>
                                                
                                                <div class="col-md-3 d-flex align-items-end">
                                                    <button type="submit" class="btn btn-primary w-100">
                                                        <i class="fas fa-save me-1"></i>Güncelle
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 