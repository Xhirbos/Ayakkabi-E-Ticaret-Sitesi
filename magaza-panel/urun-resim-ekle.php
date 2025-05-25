<?php
session_start();
require_once('../dbcon.php');

// Oturum kontrolü
if (!isset($_SESSION['magazaID'])) {
    header('Location: index.php');
    exit;
}

$magazaID = $_SESSION['magazaID'];

// Ürün ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: urunler.php');
    exit;
}

$urunID = intval($_GET['id']);

// Bu ürünün mağazaya ait olduğunu kontrol et
$stmtKontrol = $pdo->prepare("SELECT u.*, k.kategoriAdi FROM urun u JOIN kategori k ON u.kategoriID = k.kategoriID WHERE u.urunID = ? AND u.magazaID = ?");
$stmtKontrol->execute([$urunID, $magazaID]);
$urun = $stmtKontrol->fetch();

if (!$urun) {
    header('Location: urunler.php');
    exit;
}

// Mevcut resimleri getir
$stmtResimler = $pdo->prepare("SELECT * FROM urunresim WHERE urunID = ? ORDER BY sira, resimID");
$stmtResimler->execute([$urunID]);
$resimler = $stmtResimler->fetchAll();

// Resim yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resim'])) {
    $uploads_dir = '../uploads/urunler/';
    
    // Uploads dizini yoksa oluştur
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }
    
    $tmp_name = $_FILES['resim']['tmp_name'];
    $error = $_FILES['resim']['error'];
    
    if ($error === UPLOAD_ERR_OK) {
        $name = basename($_FILES['resim']['name']);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($extension), $allowed_extensions)) {
            // Benzersiz dosya adı oluştur
            $new_file_name = $urunID . '_' . uniqid() . '.' . $extension;
            $upload_path = $uploads_dir . $new_file_name;
            
            if (move_uploaded_file($tmp_name, $upload_path)) {
                try {
                    // Sıra numarasını belirle
                    $stmtSira = $pdo->prepare("SELECT MAX(sira) as maxSira FROM urunresim WHERE urunID = ?");
                    $stmtSira->execute([$urunID]);
                    $maxSira = $stmtSira->fetch();
                    $sira = ($maxSira['maxSira'] !== null) ? $maxSira['maxSira'] + 1 : 1;
                    
                    // İlk resim ise ana resim olarak işaretle
                    $anaResim = (count($resimler) === 0) ? 1 : 0;
                    
                    // Veritabanına kaydet
                    $stmtEkle = $pdo->prepare("
                        INSERT INTO urunresim (urunID, resimURL, sira, anaResim) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmtEkle->execute([$urunID, 'uploads/urunler/' . $new_file_name, $sira, $anaResim]);
                    
                    // Başarılı mesajı
                    $success = "Resim başarıyla yüklendi.";
                    
                    // Mevcut resimleri yeniden getir
                    $stmtResimler->execute([$urunID]);
                    $resimler = $stmtResimler->fetchAll();
                    
                } catch (PDOException $e) {
                    $error = "Resim eklenirken bir hata oluştu: " . $e->getMessage();
                }
            } else {
                $error = "Dosya yüklenirken bir hata oluştu.";
            }
        } else {
            $error = "Sadece JPG, JPEG, PNG ve GIF formatındaki dosyalar yüklenebilir.";
        }
    } else {
        $error = "Dosya yüklenirken bir hata oluştu. Hata kodu: " . $error;
    }
}

// Resim silme işlemi
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $resimID = intval($_GET['sil']);
    
    try {
        // Önce resim bilgilerini al
        $stmtResimBilgi = $pdo->prepare("SELECT resimURL, anaResim FROM urunresim WHERE resimID = ? AND urunID = ?");
        $stmtResimBilgi->execute([$resimID, $urunID]);
        $resimBilgi = $stmtResimBilgi->fetch();
        
        if ($resimBilgi) {
            // Resmi veritabanından sil
            $stmtResimSil = $pdo->prepare("DELETE FROM urunresim WHERE resimID = ?");
            $stmtResimSil->execute([$resimID]);
            
            // Eğer ana resimse, başka bir resmi ana resim yap
            if ($resimBilgi['anaResim'] == 1) {
                $stmtYeniAnaResim = $pdo->prepare("
                    UPDATE urunresim 
                    SET anaResim = 1 
                    WHERE urunID = ? 
                    ORDER BY sira ASC 
                    LIMIT 1
                ");
                $stmtYeniAnaResim->execute([$urunID]);
            }
            
            // Dosyayı sil (opsiyonel)
            $resimYolu = '../' . $resimBilgi['resimURL'];
            if (file_exists($resimYolu)) {
                unlink($resimYolu);
            }
            
            $success = "Resim başarıyla silindi.";
            
            // Mevcut resimleri yeniden getir
            $stmtResimler->execute([$urunID]);
            $resimler = $stmtResimler->fetchAll();
        }
    } catch (PDOException $e) {
        $error = "Resim silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Ana resim ayarlama işlemi
if (isset($_GET['ana']) && is_numeric($_GET['ana'])) {
    $resimID = intval($_GET['ana']);
    
    try {
        // Tüm resimleri ana resim değil olarak işaretle
        $stmtResetAnaResim = $pdo->prepare("UPDATE urunresim SET anaResim = 0 WHERE urunID = ?");
        $stmtResetAnaResim->execute([$urunID]);
        
        // Seçilen resmi ana resim olarak işaretle
        $stmtAnaResimYap = $pdo->prepare("UPDATE urunresim SET anaResim = 1 WHERE resimID = ? AND urunID = ?");
        $stmtAnaResimYap->execute([$resimID, $urunID]);
        
        $success = "Ana resim başarıyla güncellendi.";
        
        // Mevcut resimleri yeniden getir
        $stmtResimler->execute([$urunID]);
        $resimler = $stmtResimler->fetchAll();
    } catch (PDOException $e) {
        $error = "Ana resim güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mağaza Yönetim Paneli - Ürün Resimleri</title>
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
        .product-image {
            height: 150px;
            width: 150px;
            object-fit: cover;
            border: 1px solid #ddd;
        }
        .image-actions {
            margin-top: 10px;
        }
        .badge-corner {
            position: absolute;
            top: 0;
            right: 0;
            padding: 5px 10px;
            border-top-right-radius: 4px;
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
                            <a class="nav-link" href="siparis-yonetimi.php">
                                <i class="fas fa-shipping-fast"></i> Sipariş Yönetimi
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Ürün Resimleri: <?php echo htmlspecialchars($urun['urunAdi']); ?></h1>
                </div>
                
                <div class="alert alert-info">
                    <strong>Kategori:</strong> <?php echo htmlspecialchars($urun['kategoriAdi']); ?> |
                    <strong>Fiyat:</strong> <?php echo number_format($urun['temelFiyat'], 2, ',', '.'); ?> TL
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-upload"></i> Yeni Resim Yükle
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="resim" class="form-label">Resim Seçin</label>
                                        <input type="file" class="form-control" id="resim" name="resim" accept="image/*" required>
                                        <div class="form-text">Maksimum dosya boyutu: 5MB. İzin verilen formatlar: JPG, JPEG, PNG, GIF</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Resmi Yükle</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h3>Mevcut Resimler</h3>
                <div class="row">
                    <?php if (count($resimler) > 0): ?>
                        <?php foreach ($resimler as $resim): ?>
                            <div class="col-md-3 mb-4">
                                <div class="card position-relative">
                                    <?php if ($resim['anaResim']): ?>
                                        <span class="badge bg-success badge-corner">Ana Resim</span>
                                    <?php endif; ?>
                                    <img src="../<?php echo htmlspecialchars($resim['resimURL']); ?>" alt="Ürün Resmi" class="card-img-top" style="height: 200px; object-fit: cover;">
                                    <div class="card-body">
                                        <p class="card-text">Sıra: <?php echo $resim['sira']; ?></p>
                                        <div class="d-flex justify-content-between">
                                            <?php if (!$resim['anaResim']): ?>
                                                <a href="?id=<?php echo $urunID; ?>&ana=<?php echo $resim['resimID']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-star"></i> Ana Yap
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-success btn-sm" disabled>
                                                    <i class="fas fa-star"></i> Ana Resim
                                                </button>
                                            <?php endif; ?>
                                            <a href="?id=<?php echo $urunID; ?>&sil=<?php echo $resim['resimID']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Bu resmi silmek istediğinize emin misiniz?');">
                                                <i class="fas fa-trash-alt"></i> Sil
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-warning">Bu ürün için henüz resim eklenmemiş.</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex justify-content-between mt-4 mb-5">
                    <a href="urun-duzenle.php?id=<?php echo $urunID; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Ürün Bilgilerine Dön
                    </a>
                    <a href="varyant-yonet.php?id=<?php echo $urunID; ?>" class="btn btn-primary">
                        <i class="fas fa-tags"></i> Varyantları Yönet
                    </a>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 