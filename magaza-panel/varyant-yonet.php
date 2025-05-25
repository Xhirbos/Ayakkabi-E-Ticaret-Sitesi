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

// Tüm bedenleri getir
$stmtBedenler = $pdo->prepare("SELECT * FROM beden ORDER BY ulkeSistemi, numara");
$stmtBedenler->execute();
$bedenler = $stmtBedenler->fetchAll();

// Tüm renkleri getir
$stmtRenkler = $pdo->prepare("SELECT * FROM renk ORDER BY renkAdi");
$stmtRenkler->execute();
$renkler = $stmtRenkler->fetchAll();

// Mevcut varyantları getir
$stmtVaryantlar = $pdo->prepare("
    SELECT v.*, b.numara, b.ulkeSistemi, r.renkAdi, r.renkKodu
    FROM urunvaryant v
    JOIN beden b ON v.bedenID = b.bedenID
    JOIN renk r ON v.renkID = r.renkID
    WHERE v.urunID = ?
    ORDER BY r.renkAdi, b.numara
");
$stmtVaryantlar->execute([$urunID]);
$varyantlar = $stmtVaryantlar->fetchAll();

// Varyant ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    $renkID = intval($_POST['renkID']);
    $bedenID = intval($_POST['bedenID']);
    $stokMiktari = intval($_POST['stokMiktari']);
    $barkod = !empty($_POST['barkod']) ? trim($_POST['barkod']) : null;
    $ekFiyat = floatval(str_replace(',', '.', $_POST['ekFiyat']));
    
    // Veri doğrulama
    $errors = [];
    
    if ($renkID <= 0) {
        $errors[] = "Geçerli bir renk seçmelisiniz.";
    }
    
    if ($bedenID <= 0) {
        $errors[] = "Geçerli bir beden seçmelisiniz.";
    }
    
    if ($stokMiktari < 0) {
        $errors[] = "Stok miktarı negatif olamaz.";
    }
    
    // Bu renk-beden kombinasyonu zaten var mı kontrolü
    $stmtKombinasyon = $pdo->prepare("
        SELECT varyantID FROM urunvaryant 
        WHERE urunID = ? AND renkID = ? AND bedenID = ?
    ");
    $stmtKombinasyon->execute([$urunID, $renkID, $bedenID]);
    
    if ($stmtKombinasyon->rowCount() > 0) {
        $errors[] = "Bu renk ve beden kombinasyonu için zaten bir varyant bulunmaktadır.";
    }
    
    // Barkod varsa ve benzersiz değilse
    if ($barkod !== null) {
        $stmtBarkod = $pdo->prepare("SELECT varyantID FROM urunvaryant WHERE barkod = ? AND urunID != ?");
        $stmtBarkod->execute([$barkod, $urunID]);
        
        if ($stmtBarkod->rowCount() > 0) {
            $errors[] = "Bu barkod başka bir ürün varyantı için zaten kullanılmaktadır.";
        }
    }
    
    // Hata yoksa varyantı ekle
    if (empty($errors)) {
        try {
            // Mevcut varyant sayısını kontrol et
            $stmtVaryantSay = $pdo->prepare("SELECT COUNT(*) as sayi FROM urunvaryant WHERE urunID = ?");
            $stmtVaryantSay->execute([$urunID]);
            $varyantSayisi = $stmtVaryantSay->fetch(PDO::FETCH_ASSOC)['sayi'];
            
            // Varyantı ekle
            $stmtEkle = $pdo->prepare("
                INSERT INTO urunvaryant (urunID, renkID, bedenID, stokMiktari, barkod, ekFiyat, durum)
                VALUES (?, ?, ?, ?, ?, ?, 'Aktif')
            ");
            
            $stmtEkle->execute([
                $urunID,
                $renkID,
                $bedenID,
                $stokMiktari,
                $barkod,
                $ekFiyat
            ]);
            
            // Eğer bu ilk varyant ise, ürünü aktif duruma getir
            if ($varyantSayisi == 0) {
                $stmtUrunAktif = $pdo->prepare("UPDATE urun SET aktif = 1 WHERE urunID = ?");
                $stmtUrunAktif->execute([$urunID]);
                $success = "Varyant başarıyla eklendi ve ürün aktif duruma getirildi.";
            } else {
                $success = "Varyant başarıyla eklendi.";
            }
            
            // Mevcut varyantları yeniden getir
            $stmtVaryantlar->execute([$urunID]);
            $varyantlar = $stmtVaryantlar->fetchAll();
            
        } catch (PDOException $e) {
            $error = "Varyant eklenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Varyant silme işlemi
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $varyantID = intval($_GET['sil']);
    
    // Varyantın bu ürüne ait olduğunu kontrol et
    $stmtKontrolVaryant = $pdo->prepare("SELECT varyantID FROM urunvaryant WHERE varyantID = ? AND urunID = ?");
    $stmtKontrolVaryant->execute([$varyantID, $urunID]);
    
    if ($stmtKontrolVaryant->rowCount() > 0) {
        try {
            // Varyantı sil
            $stmtSil = $pdo->prepare("DELETE FROM urunvaryant WHERE varyantID = ?");
            $stmtSil->execute([$varyantID]);
            
            // Kalan varyant sayısını kontrol et
            $stmtVaryantSay = $pdo->prepare("SELECT COUNT(*) as sayi FROM urunvaryant WHERE urunID = ?");
            $stmtVaryantSay->execute([$urunID]);
            $varyantSayisi = $stmtVaryantSay->fetch(PDO::FETCH_ASSOC)['sayi'];
            
            // Eğer başka varyant kalmadıysa ürünü pasif yap
            if ($varyantSayisi == 0) {
                $stmtUrunPasif = $pdo->prepare("UPDATE urun SET aktif = 0 WHERE urunID = ?");
                $stmtUrunPasif->execute([$urunID]);
                $success = "Varyant başarıyla silindi. Ürün pasif duruma getirildi.";
            } else {
                $success = "Varyant başarıyla silindi.";
            }
            
            // Mevcut varyantları yeniden getir
            $stmtVaryantlar->execute([$urunID]);
            $varyantlar = $stmtVaryantlar->fetchAll();
            
        } catch (PDOException $e) {
            $error = "Varyant silinirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error = "Silme yetkisine sahip olmadığınız bir varyantı silmeye çalıştınız.";
    }
}

// Varyant durumunu güncelleme işlemi
if (isset($_GET['durum']) && is_numeric($_GET['varyant'])) {
    $varyantID = intval($_GET['varyant']);
    $yeniDurum = $_GET['durum'] === 'aktif' ? 'Aktif' : 'Pasif';
    
    // Varyantın bu ürüne ait olduğunu kontrol et
    $stmtKontrolVaryant = $pdo->prepare("SELECT varyantID FROM urunvaryant WHERE varyantID = ? AND urunID = ?");
    $stmtKontrolVaryant->execute([$varyantID, $urunID]);
    
    if ($stmtKontrolVaryant->rowCount() > 0) {
        try {
            // Durumu güncelle
            $stmtGuncelle = $pdo->prepare("UPDATE urunvaryant SET durum = ? WHERE varyantID = ?");
            $stmtGuncelle->execute([$yeniDurum, $varyantID]);
            
            $success = "Varyant durumu başarıyla güncellendi.";
            
            // Mevcut varyantları yeniden getir
            $stmtVaryantlar->execute([$urunID]);
            $varyantlar = $stmtVaryantlar->fetchAll();
            
        } catch (PDOException $e) {
            $error = "Varyant durumu güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error = "Güncelleme yetkisine sahip olmadığınız bir varyantı güncellemeye çalıştınız.";
    }
}

// Varyant stok güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guncelle_stok'])) {
    $varyantID = intval($_POST['varyantID']);
    $yeniStok = intval($_POST['yeniStok']);
    
    if ($yeniStok < 0) {
        $error = "Stok miktarı negatif olamaz.";
    } else {
        try {
            // Stok güncelle
            $stmtStokGuncelle = $pdo->prepare("UPDATE urunvaryant SET stokMiktari = ? WHERE varyantID = ? AND urunID = ?");
            $stmtStokGuncelle->execute([$yeniStok, $varyantID, $urunID]);
            
            $success = "Stok miktarı başarıyla güncellendi.";
            
            // Mevcut varyantları yeniden getir
            $stmtVaryantlar->execute([$urunID]);
            $varyantlar = $stmtVaryantlar->fetchAll();
            
        } catch (PDOException $e) {
            $error = "Stok güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mağaza Yönetim Paneli - Varyant Yönetimi</title>
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
        .color-box {
            width: 20px;
            height: 20px;
            display: inline-block;
            margin-right: 5px;
            border: 1px solid #ccc;
            vertical-align: middle;
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
                            <a class="nav-link active" href="urunler.php">
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
                    <h1 class="h2">Varyant Yönetimi: <?php echo htmlspecialchars($urun['urunAdi']); ?></h1>
                </div>
                
                <div class="alert alert-info">
                    <strong>Kategori:</strong> <?php echo htmlspecialchars($urun['kategoriAdi']); ?> |
                    <strong>Fiyat:</strong> <?php echo number_format($urun['temelFiyat'], 2, ',', '.'); ?> TL |
                    <strong>Stok Takip Tipi:</strong> <?php echo $urun['stokTakipTipi']; ?>
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
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-plus-circle"></i> Yeni Varyant Ekle
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="renkID" class="form-label">Renk</label>
                                            <select class="form-select" id="renkID" name="renkID" required>
                                                <option value="">Renk Seçin</option>
                                                <?php foreach ($renkler as $renk): ?>
                                                    <option value="<?php echo $renk['renkID']; ?>">
                                                        <?php if ($renk['renkKodu']): ?>
                                                            <span class="color-box" style="background-color: <?php echo $renk['renkKodu']; ?>"></span>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($renk['renkAdi']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="bedenID" class="form-label">Beden</label>
                                            <select class="form-select" id="bedenID" name="bedenID" required>
                                                <option value="">Beden Seçin</option>
                                                <?php foreach ($bedenler as $beden): ?>
                                                    <option value="<?php echo $beden['bedenID']; ?>">
                                                        <?php echo $beden['numara'] . ' (' . $beden['ulkeSistemi'] . ')'; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="stokMiktari" class="form-label">Stok Miktarı</label>
                                            <input type="number" class="form-control" id="stokMiktari" name="stokMiktari" min="0" value="0" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="ekFiyat" class="form-label">Ek Fiyat (TL)</label>
                                            <input type="text" class="form-control" id="ekFiyat" name="ekFiyat" value="0" required>
                                            <div class="form-text">Temel fiyata eklenecek tutar (0 ise ek fiyat yok)</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="barkod" class="form-label">Barkod (Opsiyonel)</label>
                                        <input type="text" class="form-control" id="barkod" name="barkod">
                                    </div>
                                    <button type="submit" name="ekle" class="btn btn-primary">Varyant Ekle</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h3>Mevcut Varyantlar</h3>
                <?php if (count($varyantlar) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Renk</th>
                                    <th>Beden</th>
                                    <th>Stok</th>
                                    <th>Ek Fiyat</th>
                                    <th>Toplam Fiyat</th>
                                    <th>Barkod</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($varyantlar as $varyant): ?>
                                    <tr>
                                        <td><?php echo $varyant['varyantID']; ?></td>
                                        <td>
                                            <?php if ($varyant['renkKodu']): ?>
                                                <span class="color-box" style="background-color: <?php echo $varyant['renkKodu']; ?>"></span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($varyant['renkAdi']); ?>
                                        </td>
                                        <td><?php echo $varyant['numara'] . ' (' . $varyant['ulkeSistemi'] . ')'; ?></td>
                                        <td>
                                            <form method="POST" action="" class="d-flex">
                                                <input type="hidden" name="varyantID" value="<?php echo $varyant['varyantID']; ?>">
                                                <input type="number" class="form-control form-control-sm me-2" name="yeniStok" value="<?php echo $varyant['stokMiktari']; ?>" min="0" style="width: 70px;">
                                                <button type="submit" name="guncelle_stok" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td><?php echo number_format($varyant['ekFiyat'], 2, ',', '.'); ?> TL</td>
                                        <td>
                                            <?php
                                            $toplamFiyat = $urun['temelFiyat'] + $varyant['ekFiyat'];
                                            echo number_format($toplamFiyat, 2, ',', '.') . ' TL';
                                            ?>
                                        </td>
                                        <td><?php echo $varyant['barkod'] ? $varyant['barkod'] : '-'; ?></td>
                                        <td>
                                            <?php if ($varyant['durum'] === 'Aktif'): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($varyant['durum'] === 'Aktif'): ?>
                                                    <a href="?id=<?php echo $urunID; ?>&varyant=<?php echo $varyant['varyantID']; ?>&durum=pasif" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="fas fa-ban"></i> Pasif Yap
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?id=<?php echo $urunID; ?>&varyant=<?php echo $varyant['varyantID']; ?>&durum=aktif" 
                                                       class="btn btn-sm btn-success">
                                                        <i class="fas fa-check"></i> Aktif Yap
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?id=<?php echo $urunID; ?>&sil=<?php echo $varyant['varyantID']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Bu varyantı silmek istediğinize emin misiniz?');">
                                                    <i class="fas fa-trash-alt"></i> Sil
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        Bu ürün için henüz varyant eklenmemiş. Yukarıdaki formu kullanarak varyant ekleyebilirsiniz.
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between mt-4 mb-5">
                    <a href="urun-duzenle.php?id=<?php echo $urunID; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Ürün Bilgilerine Dön
                    </a>
                    <a href="urun-resim-ekle.php?id=<?php echo $urunID; ?>" class="btn btn-primary">
                        <i class="fas fa-images"></i> Ürün Resimlerini Yönet
                    </a>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Renk seçildiğinde renk önizleme kutusu gösterme
        document.addEventListener('DOMContentLoaded', function() {
            const renkSelect = document.getElementById('renkID');
            
            renkSelect.addEventListener('change', function() {
                // Renk seçme mantığını buraya ekleyebiliriz
                // Örneğin: Renk ön izlemesi
            });
        });
    </script>
</body>
</html> 