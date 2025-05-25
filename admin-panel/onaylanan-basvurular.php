<?php
include 'header.php';
require_once '../dbcon.php';

// Onaylanan mağaza başvurularını getir
$query = "SELECT m.*, p.ad, p.soyad 
         FROM magaza m 
         LEFT JOIN personel p ON m.personelID = p.personelID
         WHERE m.basvuruDurumu = 'Onaylandi'
         ORDER BY m.kayitTarihi DESC";
$result = $conn->query($query);
?>
<div class="content-wrapper">
<div class="page-header">
    <h1 class="page-title">Onaylanan Mağaza Başvuruları</h1>
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
                                                    <p><strong>Durum:</strong> <span class="badge bg-success">Onaylandı</span></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Onaylanan mağaza başvurusu bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include 'footer.php'; ?> 