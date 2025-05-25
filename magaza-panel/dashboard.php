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

try {
    // Toplam ürün sayısı
    $stmtUrunSayisi = $conn->prepare("SELECT COUNT(*) as toplam FROM urun WHERE magazaID = ?");
    $stmtUrunSayisi->bind_param("i", $magazaID);
    $stmtUrunSayisi->execute();
    $resultUrun = $stmtUrunSayisi->get_result();
    $urunSayisi = $resultUrun->fetch_assoc()['toplam'];

    // Toplam varyant sayısı
    $stmtVaryantSayisi = $conn->prepare("
        SELECT COUNT(*) as toplam 
        FROM urunvaryant v
        JOIN urun u ON v.urunID = u.urunID
        WHERE u.magazaID = ?
    ");
    $stmtVaryantSayisi->bind_param("i", $magazaID);
    $stmtVaryantSayisi->execute();
    $resultVaryant = $stmtVaryantSayisi->get_result();
    $varyantSayisi = $resultVaryant->fetch_assoc()['toplam'];

    // Son eklenen 5 ürün
    $stmtSonUrunler = $conn->prepare("
        SELECT u.*, k.kategoriAdi
        FROM urun u
        JOIN kategori k ON u.kategoriID = k.kategoriID
        WHERE u.magazaID = ?
        ORDER BY u.olusturmaTarihi DESC
        LIMIT 5
    ");
    $stmtSonUrunler->bind_param("i", $magazaID);
    $stmtSonUrunler->execute();
    $resultSonUrunler = $stmtSonUrunler->get_result();
    $sonUrunler = [];
    while ($row = $resultSonUrunler->fetch_assoc()) {
        $sonUrunler[] = $row;
    }
} catch (Exception $e) {
    // Hata durumunda log oluştur ve genel bir hata mesajı göster
    error_log("Dashboard error for magazaID $magazaID: " . $e->getMessage());
    $error = "Veriler yüklenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mağaza Yönetim Paneli - Ana Sayfa</title>
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
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card .card-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.7;
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
                            <a class="nav-link active" href="dashboard.php">
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
                            <a class="nav-link" href="siparis-yonetimi.php">
                                <i class="fas fa-shipping-fast"></i> Sipariş Yönetimi
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Panel Özeti</h1>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card stat-card bg-primary text-white mb-3">
                            <div class="card-body">
                                <div>
                                    <h5 class="card-title">Toplam Ürün</h5>
                                    <h2 class="card-text"><?php echo isset($urunSayisi) ? $urunSayisi : 0; ?></h2>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stat-card bg-success text-white mb-3">
                            <div class="card-body">
                                <div>
                                    <h5 class="card-title">Toplam Varyant</h5>
                                    <h2 class="card-text"><?php echo isset($varyantSayisi) ? $varyantSayisi : 0; ?></h2>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-tags"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h3>Son Eklenen Ürünler</h3>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Ürün Adı</th>
                                <th>Kategori</th>
                                <th>Fiyat</th>
                                <th>Oluşturma Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($sonUrunler) && count($sonUrunler) > 0): ?>
                                <?php foreach ($sonUrunler as $urun): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($urun['urunAdi']); ?></td>
                                        <td><?php echo htmlspecialchars($urun['kategoriAdi']); ?></td>
                                        <td><?php echo number_format($urun['temelFiyat'], 2, ',', '.'); ?> TL</td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($urun['olusturmaTarihi'])); ?></td>
                                        <td>
                                            <a href="urun-duzenle.php?id=<?php echo $urun['urunID']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="varyant-yonet.php?id=<?php echo $urun['urunID']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-tags"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Henüz ürün bulunmamaktadır.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-center mt-4">
                    <a href="urun-ekle.php" class="btn btn-primary me-2">
                        <i class="fas fa-plus-circle"></i> Yeni Ürün Ekle
                    </a>
                    <a href="urunler.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Tüm Ürünleri Görüntüle
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 