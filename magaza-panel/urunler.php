<?php
session_start();
require_once('../dbcon.php');

// Oturum kontrolü
if (!isset($_SESSION['magazaID'])) {
    header('Location: index.php');
    exit;
}

$magazaID = $_SESSION['magazaID'];

// Silme işlemi
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $urunID = intval($_GET['sil']);
    
    // Ürünün bu mağazaya ait olduğunu kontrol et
    $stmtKontrol = $pdo->prepare("SELECT urunID FROM urun WHERE urunID = ? AND magazaID = ?");
    $stmtKontrol->execute([$urunID, $magazaID]);
    
    if ($stmtKontrol->rowCount() > 0) {
        try {
            // Ürün ile ilgili varyantları sil
            $stmtVaryantSil = $pdo->prepare("DELETE FROM urunvaryant WHERE urunID = ?");
            $stmtVaryantSil->execute([$urunID]);
            
            // Ürün ile ilgili resimleri sil
            $stmtResimSil = $pdo->prepare("DELETE FROM urunresim WHERE urunID = ?");
            $stmtResimSil->execute([$urunID]);
            
            // Ürünü sil
            $stmtUrunSil = $pdo->prepare("DELETE FROM urun WHERE urunID = ? AND magazaID = ?");
            $stmtUrunSil->execute([$urunID, $magazaID]);
            
            $success = "Ürün ve ilgili tüm veriler başarıyla silindi.";
        } catch (PDOException $e) {
            $error = "Ürün silinirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error = "Silme yetkisine sahip olmadığınız bir ürünü silmeye çalıştınız.";
    }
}

// Arama ve filtreleme
$where = ["u.magazaID = :magazaID"];
$params = [':magazaID' => $magazaID];

if (isset($_GET['arama']) && !empty($_GET['arama'])) {
    $arama = trim($_GET['arama']);
    $where[] = "(u.urunAdi LIKE :arama OR u.urunAciklama LIKE :arama)";
    $params[':arama'] = "%$arama%";
}

if (isset($_GET['kategori']) && is_numeric($_GET['kategori'])) {
    $where[] = "u.kategoriID = :kategoriID";
    $params[':kategoriID'] = intval($_GET['kategori']);
}

// Sayfalama
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Toplam ürün sayısı
$whereClause = implode(' AND ', $where);
$stmtCount = $pdo->prepare("SELECT COUNT(*) as toplam FROM urun u WHERE $whereClause");
$stmtCount->execute($params);
$totalCount = $stmtCount->fetch()['toplam'];
$totalPages = ceil($totalCount / $perPage);

// Ürünleri getir
$query = "
    SELECT u.*, k.kategoriAdi, 
           (SELECT COUNT(*) FROM urunvaryant WHERE urunID = u.urunID) as varyantSayisi
    FROM urun u
    JOIN kategori k ON u.kategoriID = k.kategoriID
    WHERE $whereClause
    ORDER BY u.olusturmaTarihi DESC
    LIMIT :offset, :perPage
";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$urunler = $stmt->fetchAll();

// Kategorileri getir (filtreleme için)
$stmtKategoriler = $pdo->prepare("SELECT kategoriID, kategoriAdi FROM kategori ORDER BY kategoriAdi");
$stmtKategoriler->execute();
$kategoriler = $stmtKategoriler->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mağaza Yönetim Paneli - Ürünler</title>
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
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
            display: inline-block;
            margin-left: 8px;
        }
        .status-badge.active {
            background-color: #28a745;
            color: white;
        }
        .status-badge.inactive {
            background-color: #dc3545;
            color: white;
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
                    <h1 class="h2">Ürünler</h1>
                    <a href="urun-ekle.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Yeni Ürün Ekle
                    </a>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-search"></i> Ürün Ara ve Filtrele
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-6">
                                <label for="arama" class="form-label">Arama</label>
                                <input type="text" class="form-control" id="arama" name="arama" 
                                       placeholder="Ürün adı veya açıklama ara..."
                                       value="<?php echo isset($_GET['arama']) ? htmlspecialchars($_GET['arama']) : ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="kategori" class="form-label">Kategori</label>
                                <select class="form-select" id="kategori" name="kategori">
                                    <option value="">Tümü</option>
                                    <?php foreach ($kategoriler as $kategori): ?>
                                        <option value="<?php echo $kategori['kategoriID']; ?>" 
                                            <?php echo (isset($_GET['kategori']) && $_GET['kategori'] == $kategori['kategoriID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($kategori['kategoriAdi']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filtrele</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Ürün Adı</th>
                                <th>Kategori</th>
                                <th>Temel Fiyat</th>
                                <th>İndirimli Fiyat</th>
                                <th>Stok Durumu</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($urunler)): ?>
                                <?php foreach ($urunler as $urun): ?>
                                    <tr>
                                        <td><?php echo $urun['urunID']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($urun['urunAdi']); ?>
                                            <span class="status-badge <?php echo $urun['aktif'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $urun['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($urun['kategoriAdi']); ?></td>
                                        <td><?php echo number_format($urun['temelFiyat'], 2, ',', '.'); ?> TL</td>
                                        <td>
                                            <?php 
                                            if ($urun['indirimliFiyat']): 
                                                echo number_format($urun['indirimliFiyat'], 2, ',', '.'); 
                                                echo ' TL';
                                            else:
                                                echo '-';
                                            endif;
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            // stokTakipTipi değerine göre stok durumunu göster
                                            if ($urun['stokTakipTipi'] === 'Detaylı'): 
                                                echo $urun['toplamVaryantSayisi'] > 0 ? 
                                                     $urun['toplamStok'] . ' adet (' . $urun['toplamVaryantSayisi'] . ' varyant)' : 
                                                     'Varyant Yok';
                                            else:
                                                echo $urun['genelStokMiktari'] . ' adet';
                                            endif;
                                            ?>
                                        </td>
                                        <td>
                                            <a href="urun-duzenle.php?id=<?php echo $urun['urunID']; ?>" class="btn btn-primary btn-sm mr-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="varyant-yonet.php?id=<?php echo $urun['urunID']; ?>" class="btn btn-info btn-sm mr-1">
                                                <i class="fas fa-tags"></i>
                                            </a>
                                            <a href="urun-resim-ekle.php?id=<?php echo $urun['urunID']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-images"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">Henüz ürün bulunmamaktadır.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['arama']) ? '&arama=' . urlencode($_GET['arama']) : ''; ?><?php echo isset($_GET['kategori']) ? '&kategori=' . urlencode($_GET['kategori']) : ''; ?>">Önceki</a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['arama']) ? '&arama=' . urlencode($_GET['arama']) : ''; ?><?php echo isset($_GET['kategori']) ? '&kategori=' . urlencode($_GET['kategori']) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['arama']) ? '&arama=' . urlencode($_GET['arama']) : ''; ?><?php echo isset($_GET['kategori']) ? '&kategori=' . urlencode($_GET['kategori']) : ''; ?>">Sonraki</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 