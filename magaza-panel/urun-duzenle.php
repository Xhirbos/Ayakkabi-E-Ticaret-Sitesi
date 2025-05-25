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
$stmtKontrol = $pdo->prepare("SELECT * FROM urun WHERE urunID = ? AND magazaID = ?");
$stmtKontrol->execute([$urunID, $magazaID]);
$urun = $stmtKontrol->fetch();

if (!$urun) {
    header('Location: urunler.php');
    exit;
}

// Kategorileri getir
$stmtKategoriler = $pdo->prepare("SELECT kategoriID, kategoriAdi FROM kategori ORDER BY kategoriAdi");
$stmtKategoriler->execute();
$kategoriler = $stmtKategoriler->fetchAll();

// Form gönderildi mi kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $urunAdi = trim($_POST['urunAdi']);
    $urunAciklama = trim($_POST['urunAciklama']);
    $kategoriID = intval($_POST['kategoriID']);
    $temelFiyat = floatval(str_replace(',', '.', $_POST['temelFiyat']));
    $indirimliFiyat = !empty($_POST['indirimliFiyat']) ? floatval(str_replace(',', '.', $_POST['indirimliFiyat'])) : null;
    $stokTakipTipi = 'Detaylı'; // Herzaman Detaylı olarak ayarla
    $genelStokMiktari = 0; // Detaylı stok takibinde genelStokMiktari 0 olmalı
    $minStokSeviyesi = 5; // Sabit değer olarak 5 kullan
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    
    // Veri doğrulama
    $errors = [];
    
    if (empty($urunAdi)) {
        $errors[] = "Ürün adı boş olamaz.";
    }
    
    if ($kategoriID <= 0) {
        $errors[] = "Geçerli bir kategori seçmelisiniz.";
    }
    
    if ($temelFiyat <= 0) {
        $errors[] = "Temel fiyat sıfırdan büyük olmalıdır.";
    }
    
    if ($indirimliFiyat !== null && $indirimliFiyat >= $temelFiyat) {
        $errors[] = "İndirimli fiyat, temel fiyattan düşük olmalıdır.";
    }
    
    // Hata yoksa ürünü güncelle
    if (empty($errors)) {
        try {
            // Ürünü veritabanında güncelle
            $stmt = $pdo->prepare("
                UPDATE urun SET 
                urunAdi = ?, 
                urunAciklama = ?, 
                kategoriID = ?, 
                temelFiyat = ?, 
                indirimliFiyat = ?, 
                stokTakipTipi = ?, 
                genelStokMiktari = ?, 
                minStokSeviyesi = ?, 
                aktif = ?
                WHERE urunID = ? AND magazaID = ?
            ");
            
            $stmt->execute([
                $urunAdi, 
                $urunAciklama, 
                $kategoriID, 
                $temelFiyat, 
                $indirimliFiyat, 
                $stokTakipTipi, 
                $genelStokMiktari, 
                $minStokSeviyesi, 
                $aktif,
                $urunID,
                $magazaID
            ]);
            
            $success = "Ürün başarıyla güncellendi.";
            
            // Ürün bilgilerini yeniden getir
            $stmtKontrol->execute([$urunID, $magazaID]);
            $urun = $stmtKontrol->fetch();
            
        } catch (PDOException $e) {
            $error = "Ürün güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Varyant sayısını getir
$stmtVaryantSayisi = $pdo->prepare("SELECT COUNT(*) as toplam FROM urunvaryant WHERE urunID = ?");
$stmtVaryantSayisi->execute([$urunID]);
$varyantSayisi = $stmtVaryantSayisi->fetch()['toplam'];

// Resim sayısını getir
$stmtResimSayisi = $pdo->prepare("SELECT COUNT(*) as toplam FROM urunresim WHERE urunID = ?");
$stmtResimSayisi->execute([$urunID]);
$resimSayisi = $stmtResimSayisi->fetch()['toplam'];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mağaza Yönetim Paneli - Ürün Düzenle</title>
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
                            <a class="nav-link active" href="urunler.php">
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
                    <h1 class="h2">Ürün Düzenle: <?php echo htmlspecialchars($urun['urunAdi']); ?></h1>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo $err; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-edit"></i> Ürün Bilgileri
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="urunAdi" class="form-label">Ürün Adı *</label>
                                            <input type="text" class="form-control" id="urunAdi" name="urunAdi" required
                                                   value="<?php echo htmlspecialchars($urun['urunAdi']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="kategoriID" class="form-label">Kategori *</label>
                                            <select class="form-select" id="kategoriID" name="kategoriID" required>
                                                <option value="">Kategori Seçin</option>
                                                <?php foreach ($kategoriler as $kategori): ?>
                                                    <option value="<?php echo $kategori['kategoriID']; ?>" 
                                                        <?php echo ($urun['kategoriID'] == $kategori['kategoriID']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($kategori['kategoriAdi']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="urunAciklama" class="form-label">Ürün Açıklaması</label>
                                        <textarea class="form-control" id="urunAciklama" name="urunAciklama" rows="4"><?php echo htmlspecialchars($urun['urunAciklama']); ?></textarea>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="temelFiyat" class="form-label">Temel Fiyat (TL) *</label>
                                            <input type="text" class="form-control" id="temelFiyat" name="temelFiyat" required
                                                   value="<?php echo number_format($urun['temelFiyat'], 2, '.', ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="indirimliFiyat" class="form-label">İndirimli Fiyat (TL)</label>
                                            <input type="text" class="form-control" id="indirimliFiyat" name="indirimliFiyat"
                                                   value="<?php echo $urun['indirimliFiyat'] ? number_format($urun['indirimliFiyat'], 2, '.', '') : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> Varyantlı stok takibi aktif. Stoklar varyant bazında yönetilecektir. Minimum stok seviyesi 5 olarak ayarlanmıştır.
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="aktif" name="aktif" <?php echo ($urun['aktif']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="aktif">Ürün Aktif</label>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <a href="urunler.php" class="btn btn-secondary me-2">İptal</a>
                                        <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header">
                                <i class="fas fa-images"></i> Ürün Görselleri
                            </div>
                            <div class="card-body">
                                <p>Bu ürün için <strong><?php echo $resimSayisi; ?></strong> adet görsel bulunmaktadır.</p>
                                <a href="urun-resim-ekle.php?id=<?php echo $urunID; ?>" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-images"></i> Görselleri Yönet
                                </a>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-tags"></i> Ürün Varyantları
                            </div>
                            <div class="card-body">
                                <p>Bu ürün için <strong><?php echo $varyantSayisi; ?></strong> adet varyant bulunmaktadır.</p>
                                <a href="varyant-yonet.php?id=<?php echo $urunID; ?>" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-tags"></i> Varyantları Yönet
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 