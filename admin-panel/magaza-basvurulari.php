<?php
include 'header.php';
require_once '../dbcon.php';

// Filtre için aktif sekme kontrolü
$active_filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Filtre sorgusunu oluştur
$filter_clause = "";
if ($active_filter == 'pending') {
    $filter_clause = " WHERE basvuruDurumu = 'Beklemede'";
} elseif ($active_filter == 'approved') {
    $filter_clause = " WHERE basvuruDurumu = 'Onaylandi'";
} elseif ($active_filter == 'rejected') {
    $filter_clause = " WHERE basvuruDurumu = 'Reddedildi'";
}

// Mağaza başvurularını getir
$query = "SELECT m.*, p.ad, p.soyad 
         FROM magaza m 
         LEFT JOIN personel p ON m.personelID = p.personelID
         $filter_clause 
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
        header("Location: magaza-basvurulari.php?filter=$active_filter&success=1");
        exit;
    } else {
        // Hata
        $error = "Güncelleme sırasında bir hata oluştu: " . $conn->error;
    }
}
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    Mağaza başvuru durumu başarıyla güncellendi.
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger">
    <?php echo $error; ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mağaza Başvuruları</h1>
        <p class="page-subtitle">Mağaza başvurularını görüntüleyin ve yönetin</p>
    </div>
    <div class="page-actions">
        <div class="d-flex gap-2">
            <a href="?filter=all" class="btn <?php echo $active_filter == 'all' ? 'btn-primary' : 'btn-outline'; ?>">Tümü</a>
            <a href="?filter=pending" class="btn <?php echo $active_filter == 'pending' ? 'btn-primary' : 'btn-outline'; ?>">Bekleyen</a>
            <a href="?filter=approved" class="btn <?php echo $active_filter == 'approved' ? 'btn-primary' : 'btn-outline'; ?>">Onaylanan</a>
            <a href="?filter=rejected" class="btn <?php echo $active_filter == 'rejected' ? 'btn-primary' : 'btn-outline'; ?>">Reddedilen</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-container">
            <table class="table data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mağaza Adı</th>
                        <th>E-posta</th>
                        <th>Telefon</th>
                        <th>Adres</th>
                        <th>Kayıt Tarihi</th>
                        <th>Sorumlu</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($magaza = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $magaza['magazaID']; ?></td>
                                <td><strong><?php echo htmlspecialchars($magaza['magazaAdi']); ?></strong></td>
                                <td><?php echo htmlspecialchars($magaza['eposta']); ?></td>
                                <td><?php echo htmlspecialchars($magaza['telefon']); ?></td>
                                <td><?php echo htmlspecialchars($magaza['adres']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($magaza['kayitTarihi'])); ?></td>
                                <td><?php echo htmlspecialchars($magaza['ad'] . ' ' . $magaza['soyad']); ?></td>
                                <td>
                                    <?php if ($magaza['basvuruDurumu'] == 'Beklemede'): ?>
                                        <span class="badge badge-warning">BEKLEMEDE</span>
                                    <?php elseif ($magaza['basvuruDurumu'] == 'Onaylandi'): ?>
                                        <span class="badge badge-success">ONAYLANDI</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">REDDEDILDI</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline" data-modal-target="#detailModal<?php echo $magaza['magazaID']; ?>">
                                            <i class="fas fa-eye"></i>
                                            Detay
                                        </button>
                                        <?php if ($magaza['basvuruDurumu'] == 'Beklemede'): ?>
                                            <button type="button" class="btn btn-sm btn-success" data-modal-target="#approveModal<?php echo $magaza['magazaID']; ?>">
                                                <i class="fas fa-check"></i>
                                                Onayla
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" data-modal-target="#rejectModal<?php echo $magaza['magazaID']; ?>">
                                                <i class="fas fa-times"></i>
                                                Reddet
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">Mağaza başvurusu bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals -->
<?php if ($result->num_rows > 0): ?>
    <?php $result->data_seek(0); // Reset result pointer ?>
    <?php while ($magaza = $result->fetch_assoc()): ?>
        
        <!-- Detay Modal -->
        <div class="modal" id="detailModal<?php echo $magaza['magazaID']; ?>">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3 class="modal-title">Mağaza Detayları: <?php echo htmlspecialchars($magaza['magazaAdi']); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <p><strong>ID:</strong> <?php echo $magaza['magazaID']; ?></p>
                            <p><strong>Mağaza Adı:</strong> <?php echo htmlspecialchars($magaza['magazaAdi']); ?></p>
                            <p><strong>E-posta:</strong> <?php echo htmlspecialchars($magaza['eposta']); ?></p>
                            <p><strong>Telefon:</strong> <?php echo htmlspecialchars($magaza['telefon']); ?></p>
                        </div>
                        <div>
                            <p><strong>Adres:</strong> <?php echo htmlspecialchars($magaza['adres']); ?></p>
                            <p><strong>Kayıt Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($magaza['kayitTarihi'])); ?></p>
                            <p><strong>Sorumlu Personel:</strong> <?php echo htmlspecialchars($magaza['ad'] . ' ' . $magaza['soyad']); ?></p>
                            <p><strong>Durum:</strong> 
                                <?php if ($magaza['basvuruDurumu'] == 'Beklemede'): ?>
                                    <span class="badge badge-warning">Beklemede</span>
                                <?php elseif ($magaza['basvuruDurumu'] == 'Onaylandi'): ?>
                                    <span class="badge badge-success">Onaylandı</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Reddedildi</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($magaza['basvuruDurumu'] == 'Reddedildi' && !empty($magaza['redNedeni'])): ?>
                        <div class="mt-4">
                            <h4>Ret Nedeni:</h4>
                            <div class="alert alert-danger">
                                <?php echo nl2br(htmlspecialchars($magaza['redNedeni'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">Kapat</button>
                </div>
            </div>
        </div>
        
        <!-- Onaylama Modal -->
        <div class="modal" id="approveModal<?php echo $magaza['magazaID']; ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Mağaza Başvurusunu Onayla</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <p>"<?php echo htmlspecialchars($magaza['magazaAdi']); ?>" mağazasının başvurusunu onaylamak istediğinize emin misiniz?</p>
                        <input type="hidden" name="magazaID" value="<?php echo $magaza['magazaID']; ?>">
                        <input type="hidden" name="durum" value="Onaylandi">
                        <input type="hidden" name="action" value="update">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-close">İptal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i>
                            Onayla
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Reddetme Modal -->
        <div class="modal" id="rejectModal<?php echo $magaza['magazaID']; ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Mağaza Başvurusunu Reddet</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <p>"<?php echo htmlspecialchars($magaza['magazaAdi']); ?>" mağazasının başvurusunu reddetmek istediğinize emin misiniz?</p>
                        <div class="form-group">
                            <label for="redNedeni<?php echo $magaza['magazaID']; ?>" class="form-label">Reddetme Nedeni</label>
                            <textarea class="form-control" id="redNedeni<?php echo $magaza['magazaID']; ?>" name="redNedeni" rows="3" required placeholder="Reddetme nedeninizi açıklayın..."></textarea>
                        </div>
                        <input type="hidden" name="magazaID" value="<?php echo $magaza['magazaID']; ?>">
                        <input type="hidden" name="durum" value="Reddedildi">
                        <input type="hidden" name="action" value="update">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-close">İptal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i>
                            Reddet
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
    <?php endwhile; ?>
<?php endif; ?>

<?php include 'footer.php'; ?> 