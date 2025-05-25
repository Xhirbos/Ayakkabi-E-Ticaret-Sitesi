<?php
require_once '../dbcon.php';
include 'header.php';

$success = '';
$error = '';

// Yorum durumu değiştirme işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['yorumID'])) {
        $yorumID = (int)$_POST['yorumID'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'onayla') {
                $stmt = $pdo->prepare("UPDATE yorum SET onayDurumu = 'Onaylandi' WHERE yorumID = ?");
                $stmt->execute([$yorumID]);
                $success = "Yorum başarıyla onaylandı.";
            } elseif ($action === 'sil') {
                $stmt = $pdo->prepare("DELETE FROM yorum WHERE yorumID = ?");
                $stmt->execute([$yorumID]);
                $success = "Yorum kalıcı olarak silindi.";
            }
        } catch (PDOException $e) {
            $error = "İşlem sırasında hata oluştu: " . $e->getMessage();
        }
    }
}

// Reddedilen yorumları getir
try {
    $stmt = $pdo->prepare("
        SELECT 
            y.yorumID,
            y.puan,
            y.baslik,
            y.yorum,
            y.olusturmaTarihi,
            y.guncellemeTarihi,
            m.ad,
            m.soyad,
            m.eposta,
            u.urunAdi,
            u.urunID,
            mg.magazaAdi
        FROM yorum y
        JOIN musteri m ON y.musteriID = m.musteriID
        JOIN urun u ON y.urunID = u.urunID
        JOIN magaza mg ON u.magazaID = mg.magazaID
        WHERE y.onayDurumu = 'Reddedildi'
        ORDER BY y.guncellemeTarihi DESC
    ");
    $stmt->execute();
    $reddedilenYorumlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Yorumlar yüklenirken hata oluştu: " . $e->getMessage();
}
?>

<div class="admin-content">
    <div class="content-header">
        <h1><i class="fas fa-times-circle"></i> Reddedilen Yorumlar</h1>
        <p>Reddedilmiş müşteri yorumları</p>
        <a href="yorum-yonetimi.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Geri Dön
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($reddedilenYorumlar)): ?>
        <div class="empty-state">
            <i class="fas fa-times-circle fa-3x"></i>
            <h3>Reddedilen Yorum Yok</h3>
            <p>Şu anda reddedilmiş yorum bulunmamaktadır.</p>
            <a href="yorum-yonetimi.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Yorum Yönetimine Dön
            </a>
        </div>
    <?php else: ?>
        <div class="reviews-container">
            <?php foreach ($reddedilenYorumlar as $yorum): ?>
                <div class="review-card rejected">
                    <div class="review-header">
                        <div class="customer-info">
                            <h4><?php echo htmlspecialchars($yorum['ad'] . ' ' . $yorum['soyad']); ?></h4>
                            <span class="email"><?php echo htmlspecialchars($yorum['eposta']); ?></span>
                            <span class="date">
                                Oluşturulma: <?php echo date('d.m.Y H:i', strtotime($yorum['olusturmaTarihi'])); ?>
                            </span>
                            <span class="date">
                                Reddedilme: <?php echo date('d.m.Y H:i', strtotime($yorum['guncellemeTarihi'])); ?>
                            </span>
                        </div>
                        <div class="rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $yorum['puan'] ? 'filled' : 'empty'; ?>"></i>
                            <?php endfor; ?>
                            <span class="rating-text"><?php echo $yorum['puan']; ?>/5</span>
                        </div>
                    </div>

                    <div class="product-info">
                        <strong>Ürün:</strong> 
                        <a href="../product-detail.php?id=<?php echo $yorum['urunID']; ?>" target="_blank">
                            <?php echo htmlspecialchars($yorum['urunAdi']); ?>
                        </a>
                        <span class="store-name">(<?php echo htmlspecialchars($yorum['magazaAdi']); ?>)</span>
                    </div>

                    <?php if (!empty($yorum['baslik'])): ?>
                        <div class="review-title">
                            <strong><?php echo htmlspecialchars($yorum['baslik']); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="review-content">
                        <?php echo nl2br(htmlspecialchars($yorum['yorum'])); ?>
                    </div>

                    <div class="review-actions">
                        <span class="status-badge rejected">
                            <i class="fas fa-times"></i> Reddedildi
                        </span>
                        
                        <div class="action-buttons">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="yorumID" value="<?php echo $yorum['yorumID']; ?>">
                                <button type="submit" name="action" value="onayla" class="btn btn-success btn-sm"
                                        onclick="return confirm('Bu yorumu onaylamak istediğinize emin misiniz? Yorum tekrar yayınlanacaktır.')">
                                    <i class="fas fa-check"></i> Onayla
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="yorumID" value="<?php echo $yorum['yorumID']; ?>">
                                <button type="submit" name="action" value="sil" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Bu yorumu kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz!')">
                                    <i class="fas fa-trash"></i> Kalıcı Sil
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="warning-box">
            <h3><i class="fas fa-exclamation-triangle"></i> Uyarı</h3>
            <p>Reddedilen yorumlar müşteriler tarafından görüntülenmemektedir.</p>
            <p>Bir yorumu tekrar onaylayabilir veya kalıcı olarak silebilirsiniz.</p>
            <p><strong>Kalıcı silme işlemi geri alınamaz!</strong></p>
        </div>
    <?php endif; ?>
</div>

<style>
.reviews-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.review-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-left: 4px solid #dc3545;
}

.review-card.rejected {
    border-left-color: #dc3545;
    opacity: 0.9;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.customer-info h4 {
    margin: 0 0 5px 0;
    color: #333;
}

.customer-info .email {
    color: #666;
    font-size: 0.9rem;
    display: block;
}

.customer-info .date {
    color: #999;
    font-size: 0.85rem;
    display: block;
    margin-top: 3px;
}

.rating {
    display: flex;
    align-items: center;
    gap: 5px;
}

.rating .fas.fa-star.filled {
    color: #ffc107;
}

.rating .fas.fa-star.empty {
    color: #ddd;
}

.rating-text {
    margin-left: 10px;
    font-weight: bold;
    color: #333;
}

.product-info {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.product-info a {
    color: #007bff;
    text-decoration: none;
}

.product-info a:hover {
    text-decoration: underline;
}

.store-name {
    color: #666;
    font-size: 0.9rem;
}

.review-title {
    margin-bottom: 10px;
    color: #333;
}

.review-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
    line-height: 1.6;
    color: #555;
}

.review-actions {
    display: flex;
    gap: 10px;
    justify-content: space-between;
    align-items: center;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

.warning-box {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 20px;
    margin-top: 30px;
}

.warning-box h3 {
    color: #856404;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.warning-box p {
    color: #856404;
    margin-bottom: 8px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state i {
    color: #dc3545;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 10px;
}
</style>

<?php include 'footer.php'; ?> 