<?php
require_once '../dbcon.php';
include 'header.php';

// Yorum istatistiklerini al
try {
    $stmtBekleyen = $pdo->prepare("SELECT COUNT(*) as bekleyen FROM yorum WHERE onayDurumu = 'Beklemede'");
    $stmtBekleyen->execute();
    $bekleyenSayisi = $stmtBekleyen->fetch(PDO::FETCH_ASSOC)['bekleyen'];

    $stmtOnaylanan = $pdo->prepare("SELECT COUNT(*) as onaylanan FROM yorum WHERE onayDurumu = 'Onaylandi'");
    $stmtOnaylanan->execute();
    $onaylananSayisi = $stmtOnaylanan->fetch(PDO::FETCH_ASSOC)['onaylanan'];

    $stmtReddedilen = $pdo->prepare("SELECT COUNT(*) as reddedilen FROM yorum WHERE onayDurumu = 'Reddedildi'");
    $stmtReddedilen->execute();
    $reddedilenSayisi = $stmtReddedilen->fetch(PDO::FETCH_ASSOC)['reddedilen'];

    $toplamYorum = $bekleyenSayisi + $onaylananSayisi + $reddedilenSayisi;

} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}
?>

<div class="admin-content">
    <div class="content-header">
        <h1><i class="fas fa-comments"></i> Yorum Yönetimi</h1>
        <p>Müşteri yorumlarını yönetin, onaylayın veya reddedin</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- İstatistik Kartları -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $bekleyenSayisi; ?></h3>
                <p>Bekleyen Yorumlar</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $onaylananSayisi; ?></h3>
                <p>Onaylanan Yorumlar</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $reddedilenSayisi; ?></h3>
                <p>Reddedilen Yorumlar</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $toplamYorum; ?></h3>
                <p>Toplam Yorum</p>
            </div>
        </div>
    </div>

    <!-- Yönetim Kartları -->
    <div class="management-grid">
        <div class="management-card">
            <div class="card-header warning">
                <i class="fas fa-clock"></i>
                <h3>Bekleyen Yorumlar</h3>
            </div>
            <div class="card-content">
                <p>Onay bekleyen <?php echo $bekleyenSayisi; ?> yorum bulunmaktadır.</p>
                <p>Bu yorumlar müşteriler tarafından görüntülenememektedir.</p>
                <a href="bekleyen-yorumlar.php" class="btn btn-warning">
                    <i class="fas fa-eye"></i>
                    Bekleyen Yorumları Görüntüle
                </a>
            </div>
        </div>

        <div class="management-card">
            <div class="card-header success">
                <i class="fas fa-check-circle"></i>
                <h3>Onaylanan Yorumlar</h3>
            </div>
            <div class="card-content">
                <p><?php echo $onaylananSayisi; ?> onaylanmış yorum bulunmaktadır.</p>
                <p>Bu yorumlar müşteriler tarafından görüntülenmektedir.</p>
                <a href="onaylanan-yorumlar.php" class="btn btn-success">
                    <i class="fas fa-eye"></i>
                    Onaylanan Yorumları Görüntüle
                </a>
            </div>
        </div>

        <div class="management-card">
            <div class="card-header danger">
                <i class="fas fa-times-circle"></i>
                <h3>Reddedilen Yorumlar</h3>
            </div>
            <div class="card-content">
                <p><?php echo $reddedilenSayisi; ?> reddedilmiş yorum bulunmaktadır.</p>
                <p>Bu yorumlar müşteriler tarafından görüntülenememektedir.</p>
                <a href="reddedilen-yorumlar.php" class="btn btn-danger">
                    <i class="fas fa-eye"></i>
                    Reddedilen Yorumları Görüntüle
                </a>
            </div>
        </div>
    </div>

    <!-- Hızlı İşlemler -->
    <div class="quick-actions">
        <h3><i class="fas fa-bolt"></i> Hızlı İşlemler</h3>
        <div class="action-buttons">
            <a href="bekleyen-yorumlar.php" class="btn btn-warning">
                <i class="fas fa-clock"></i>
                Bekleyen Yorumları İncele
            </a>
            <button class="btn btn-info" onclick="refreshStats()">
                <i class="fas fa-sync-alt"></i>
                İstatistikleri Yenile
            </button>
        </div>
    </div>
</div>

<script>
function refreshStats() {
    location.reload();
}
</script>

<?php include 'footer.php'; ?> 