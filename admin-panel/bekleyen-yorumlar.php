<?php
require_once '../dbcon.php';
include 'header.php';

$success = '';
$error = '';

// Yorum onay/red işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['yorumID'])) {
        $yorumID = (int)$_POST['yorumID'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'onayla') {
                $stmt = $pdo->prepare("UPDATE yorum SET onayDurumu = 'Onaylandi' WHERE yorumID = ?");
                $stmt->execute([$yorumID]);
                $success = "Yorum başarıyla onaylandı.";
            } elseif ($action === 'reddet') {
                $stmt = $pdo->prepare("UPDATE yorum SET onayDurumu = 'Reddedildi' WHERE yorumID = ?");
                $stmt->execute([$yorumID]);
                $success = "Yorum başarıyla reddedildi.";
            }
        } catch (PDOException $e) {
            $error = "İşlem sırasında hata oluştu: " . $e->getMessage();
        }
    }
}

// Bekleyen yorumları getir
try {
    $stmt = $pdo->prepare("
        SELECT 
            y.yorumID,
            y.puan,
            y.baslik,
            y.yorum,
            y.olusturmaTarihi,
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
        WHERE y.onayDurumu = 'Beklemede'
        ORDER BY y.olusturmaTarihi DESC
    ");
    $stmt->execute();
    $bekleyenYorumlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Yorumlar yüklenirken hata oluştu: " . $e->getMessage();
}
?>

<div class="admin-content">
    <div class="content-header">
        <h1><i class="fas fa-clock"></i> Bekleyen Yorumlar</h1>
        <p>Onay bekleyen müşteri yorumları</p>
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

    <?php if (empty($bekleyenYorumlar)): ?>
        <div class="empty-state">
            <i class="fas fa-comments fa-3x"></i>
            <h3>Bekleyen Yorum Yok</h3>
            <p>Şu anda onay bekleyen yorum bulunmamaktadır.</p>
            <a href="yorum-yonetimi.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Yorum Yönetimine Dön
            </a>
        </div>
    <?php else: ?>
        <div class="reviews-container">
            <?php foreach ($bekleyenYorumlar as $yorum): ?>
                <div class="review-card pending">
                    <div class="review-header">
                        <div class="customer-info">
                            <h4><?php echo htmlspecialchars($yorum['ad'] . ' ' . $yorum['soyad']); ?></h4>
                            <span class="email"><?php echo htmlspecialchars($yorum['eposta']); ?></span>
                            <span class="date"><?php echo date('d.m.Y H:i', strtotime($yorum['olusturmaTarihi'])); ?></span>
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
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="yorumID" value="<?php echo $yorum['yorumID']; ?>">
                            <button type="submit" name="action" value="onayla" class="btn btn-success" 
                                    onclick="return confirm('Bu yorumu onaylamak istediğinize emin misiniz?')">
                                <i class="fas fa-check"></i> Onayla
                            </button>
                        </form>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="yorumID" value="<?php echo $yorum['yorumID']; ?>">
                            <button type="submit" name="action" value="reddet" class="btn btn-danger"
                                    onclick="return confirm('Bu yorumu reddetmek istediğinize emin misiniz?')">
                                <i class="fas fa-times"></i> Reddet
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="bulk-actions">
            <h3>Toplu İşlemler</h3>
            <p>Gelecek güncellemede toplu onay/red işlemleri eklenecektir.</p>
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
    border-left: 4px solid #ffc107;
}

.review-card.pending {
    border-left-color: #ffc107;
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
    margin-top: 5px;
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
    margin-bottom: 20px;
    line-height: 1.6;
    color: #555;
}

.review-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.bulk-actions {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-top: 30px;
    text-align: center;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state i {
    color: #ddd;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 10px;
}
</style>

<?php include 'footer.php'; ?> 