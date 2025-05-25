<?php
// Hata ayıklama için
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'header.php';
require_once '../dbcon.php';

// İstatistikleri getir
// Toplam mağaza sayısı
$magaza_query = "SELECT COUNT(*) as toplam_magaza FROM magaza";
$magaza_result = $conn->query($magaza_query);
$toplam_magaza = $magaza_result->fetch_assoc()['toplam_magaza'];

// Başvuru durumlarına göre mağaza sayıları
$magaza_durum_query = "SELECT basvuruDurumu, COUNT(*) as count 
                      FROM magaza 
                      GROUP BY basvuruDurumu";
$magaza_durum_result = $conn->query($magaza_durum_query);

$bekleyen_magaza = 0;
$onaylanan_magaza = 0;
$reddedilen_magaza = 0;

while ($row = $magaza_durum_result->fetch_assoc()) {
    if ($row['basvuruDurumu'] == 'Beklemede') {
        $bekleyen_magaza = $row['count'];
    } else if ($row['basvuruDurumu'] == 'Onaylandi') {
        $onaylanan_magaza = $row['count'];
    } else if ($row['basvuruDurumu'] == 'Reddedildi') {
        $reddedilen_magaza = $row['count'];
    }
}

// Son 5 mağaza başvurusunu getir
$son_basvurular_query = "SELECT * FROM magaza ORDER BY kayitTarihi DESC LIMIT 5";
$son_basvurular_result = $conn->query($son_basvurular_query);
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Mağaza başvurularınızı ve istatistiklerinizi görüntüleyin</p>
    </div>
    <div class="page-actions">
        <a href="magaza-basvurulari.php" class="btn btn-primary">
            <i class="fas fa-store"></i>
            Tüm Başvurular
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Toplam Mağaza</div>
            <div class="stat-icon primary">
                <i class="fas fa-store"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $toplam_magaza; ?></div>
        <div class="stat-change positive">Sistemdeki toplam mağaza sayısı</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Bekleyen Başvurular</div>
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $bekleyen_magaza; ?></div>
        <div class="stat-change">İnceleme bekleyen mağaza sayısı</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Onaylanan Mağazalar</div>
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $onaylanan_magaza; ?></div>
        <div class="stat-change positive">Onaylanmış mağaza sayısı</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Reddedilen Başvurular</div>
            <div class="stat-icon danger">
                <i class="fas fa-times-circle"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $reddedilen_magaza; ?></div>
        <div class="stat-change negative">Reddedilmiş mağaza sayısı</div>
    </div>
</div>

<!-- Content Cards -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Chart Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Başvuru Durumu İstatistikleri</h3>
        </div>
        <div class="card-body">
            <div style="position: relative; height: 300px;">
                <canvas id="magazaDurumChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Applications -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Son Mağaza Başvuruları</h3>
            <a href="magaza-basvurulari.php" class="btn btn-outline">
                <i class="fas fa-arrow-right"></i>
                Tümünü Gör
            </a>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mağaza Adı</th>
                            <th>E-posta</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($son_basvurular_result->num_rows > 0): ?>
                            <?php while ($magaza = $son_basvurular_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($magaza['magazaAdi']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($magaza['eposta']); ?></td>
                                    <td>
                                        <?php if ($magaza['basvuruDurumu'] == 'Beklemede'): ?>
                                            <span class="badge badge-warning">Beklemede</span>
                                        <?php elseif ($magaza['basvuruDurumu'] == 'Onaylandi'): ?>
                                            <span class="badge badge-success">Onaylandı</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Reddedildi</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">Henüz mağaza başvurusu bulunmuyor.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mağaza Durumu Grafik
    const ctx = document.getElementById('magazaDurumChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Beklemede', 'Onaylandı', 'Reddedildi'],
            datasets: [{
                data: [<?php echo $bekleyen_magaza; ?>, <?php echo $onaylanan_magaza; ?>, <?php echo $reddedilen_magaza; ?>],
                backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: {
                            family: 'Inter',
                            size: 12
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include 'footer.php'; ?> 