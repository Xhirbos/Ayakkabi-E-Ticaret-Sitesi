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
            if ($action === 'reddet') {
                $stmt = $pdo->prepare("UPDATE yorum SET onayDurumu = 'Reddedildi' WHERE yorumID = ?");
                $stmt->execute([$yorumID]);
                $success = "Yorum başarıyla reddedildi.";
            }
        } catch (PDOException $e) {
            $error = "İşlem sırasında hata oluştu: " . $e->getMessage();
        }
    }
}

// Onaylanan yorumları getir
try {
    $stmt = $pdo->prepare("
        SELECT 
            y.yorumID,
            y.puan,
            y.baslik,
            y.yorum,
            y.yanit,
            y.yanitTarihi,
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
        WHERE y.onayDurumu = 'Onaylandi'
        ORDER BY y.olusturmaTarihi DESC
    ");
    $stmt->execute();
    $onaylananYorumlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Yorumlar yüklenirken hata oluştu: " . $e->getMessage();
}
?>

<div class="admin-content">
    <div class="content-header">
        <h1><i class="fas fa-check-circle"></i> Onaylanan Yorumlar</h1>
        <p>Onaylanmış ve yayında olan müşteri yorumları</p>
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

    <?php if (empty($onaylananYorumlar)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle fa-3x"></i>
            <h3>Onaylanan Yorum Yok</h3>
            <p>Şu anda onaylanmış yorum bulunmamaktadır.</p>
            <a href="yorum-yonetimi.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Yorum Yönetimine Dön
            </a>
        </div>
    <?php else: ?>
        <div class="reviews-container">
            <?php foreach ($onaylananYorumlar as $yorum): ?>
                <div class="review-card approved">
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

                    <?php if (!empty($yorum['yanit'])): ?>
                        <div class="store-reply">
                            <div class="reply-header">
                                <i class="fas fa-reply"></i>
                                Mağaza Yanıtı
                                <?php if ($yorum['yanitTarihi']): ?>
                                    <span class="reply-date"><?php echo date('d.m.Y H:i', strtotime($yorum['yanitTarihi'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="reply-content">
                                <?php echo nl2br(htmlspecialchars($yorum['yanit'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="review-actions">
                        <span class="status-badge approved">
                            <i class="fas fa-check"></i> Onaylandı
                        </span>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="yorumID" value="<?php echo $yorum['yorumID']; ?>">
                            <button type="submit" name="action" value="reddet" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Bu yorumu reddetmek istediğinize emin misiniz? Yorum artık görüntülenmeyecektir.')">
                                <i class="fas fa-times"></i> Reddet
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> Bilgi</h3>
            <p>Onaylanan yorumlar müşteriler tarafından ürün detay sayfalarında görüntülenmektedir.</p>
            <p>Bir yorumu reddederseniz, artık müşteriler tarafından görüntülenmeyecektir.</p>
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
    border-left: 4px solid #28a745;
}

.review-card.approved {
    border-left-color: #28a745;
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
    margin-bottom: 15px;
    line-height: 1.6;
    color: #555;
}

.store-reply {
    background: #e8f5e8;
    border: 1px solid #d4edda;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
}

.reply-header {
    font-weight: bold;
    color: #155724;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.reply-date {
    font-weight: normal;
    color: #666;
    font-size: 0.85rem;
    margin-left: auto;
}

.reply-content {
    color: #155724;
    line-height: 1.6;
}

.review-actions {
    display: flex;
    gap: 10px;
    justify-content: space-between;
    align-items: center;
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

.status-badge.approved {
    background: #d4edda;
    color: #155724;
}

.info-box {
    background: #e7f3ff;
    border: 1px solid #b8daff;
    border-radius: 8px;
    padding: 20px;
    margin-top: 30px;
}

.info-box h3 {
    color: #004085;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-box p {
    color: #004085;
    margin-bottom: 8px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state i {
    color: #28a745;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 10px;
}
</style>

<?php include 'footer.php'; ?> 