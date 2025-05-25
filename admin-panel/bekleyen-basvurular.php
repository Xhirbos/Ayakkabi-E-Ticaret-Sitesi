<?php
include 'header.php';
require_once '../dbcon.php';

// Bekleyen mağaza başvurularını getir
$query = "SELECT m.*, p.ad, p.soyad 
         FROM magaza m 
         LEFT JOIN personel p ON m.personelID = p.personelID
         WHERE m.basvuruDurumu = 'Beklemede'
         ORDER BY m.kayitTarihi DESC";
$result = $conn->query($query);

// Bir başvuruyu güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $magaza_id = $_POST['magazaID'];
    $durum = $_POST['durum'];
    $red_nedeni = isset($_POST['redNedeni']) ? $_POST['redNedeni'] : null;
    
    $update_query = "UPDATE magaza SET basvuruDurumu = ?, redNedeni = ? WHERE magazaID = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssi", $durum, $red_nedeni, $magaza_id);
    
    if ($stmt->execute()) {
        // Başarılı güncelleme
        header("Location: bekleyen-basvurular.php?success=1");
        exit;
    } else {
        // Hata
        $error = "Güncelleme sırasında bir hata oluştu: " . $conn->error;
    }
}
?>
<div class="content-wrapper">
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    Mağaza başvuru durumu başarıyla güncellendi.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="page-header">
    <h1 class="page-title">Bekleyen Mağaza Başvuruları</h1>
    <a href="magaza-basvurulari.php" class="btn btn-primary">Tüm Başvuruları Görüntüle</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mağaza Adı</th>
                        <th>E-posta</th>
                        <th>Telefon</th>
                        <th>Adres</th>
                        <th>Kayıt Tarihi</th>
                        <th>Sorumlu</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($magaza = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $magaza['magazaID']; ?></td>
                                <td><?php echo $magaza['magazaAdi']; ?></td>
                                <td><?php echo $magaza['eposta']; ?></td>
                                <td><?php echo $magaza['telefon']; ?></td>
                                <td><?php echo $magaza['adres']; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($magaza['kayitTarihi'])); ?></td>
                                <td><?php echo $magaza['ad'] . ' ' . $magaza['soyad']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $magaza['magazaID']; ?>">
                                        Detay
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $magaza['magazaID']; ?>">
                                        Onayla
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $magaza['magazaID']; ?>">
                                        Reddet
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Detay Modal -->
                            <div class="modal fade" id="detailModal<?php echo $magaza['magazaID']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Mağaza Detayları: <?php echo $magaza['magazaAdi']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>ID:</strong> <?php echo $magaza['magazaID']; ?></p>
                                                    <p><strong>Mağaza Adı:</strong> <?php echo $magaza['magazaAdi']; ?></p>
                                                    <p><strong>E-posta:</strong> <?php echo $magaza['eposta']; ?></p>
                                                    <p><strong>Telefon:</strong> <?php echo $magaza['telefon']; ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Adres:</strong> <?php echo $magaza['adres']; ?></p>
                                                    <p><strong>Kayıt Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($magaza['kayitTarihi'])); ?></p>
                                                    <p><strong>Sorumlu Personel:</strong> <?php echo $magaza['ad'] . ' ' . $magaza['soyad']; ?></p>
                                                    <p><strong>Durum:</strong> <span class="badge bg-warning">Beklemede</span></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Onaylama Modal -->
                            <div class="modal fade" id="approveModal<?php echo $magaza['magazaID']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Mağaza Başvurusunu Onayla</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="post" action="">
                                            <div class="modal-body">
                                                <p>"<?php echo $magaza['magazaAdi']; ?>" mağazasının başvurusunu onaylamak istediğinize emin misiniz?</p>
                                                <input type="hidden" name="magazaID" value="<?php echo $magaza['magazaID']; ?>">
                                                <input type="hidden" name="durum" value="Onaylandi">
                                                <input type="hidden" name="action" value="update">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                                <button type="submit" class="btn btn-success">Onayla</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Reddetme Modal -->
                            <div class="modal fade" id="rejectModal<?php echo $magaza['magazaID']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Mağaza Başvurusunu Reddet</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="post" action="">
                                            <div class="modal-body">
                                                <p>"<?php echo $magaza['magazaAdi']; ?>" mağazasının başvurusunu reddetmek istediğinize emin misiniz?</p>
                                                <div class="mb-3">
                                                    <label for="redNedeni<?php echo $magaza['magazaID']; ?>" class="form-label">Reddetme Nedeni</label>
                                                    <textarea class="form-control" id="redNedeni<?php echo $magaza['magazaID']; ?>" name="redNedeni" rows="3" required></textarea>
                                                </div>
                                                <input type="hidden" name="magazaID" value="<?php echo $magaza['magazaID']; ?>">
                                                <input type="hidden" name="durum" value="Reddedildi">
                                                <input type="hidden" name="action" value="update">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                                <button type="submit" class="btn btn-danger">Reddet</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Bekleyen mağaza başvurusu bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?> 