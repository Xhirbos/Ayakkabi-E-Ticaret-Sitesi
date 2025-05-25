<?php
session_start();
require_once 'dbcon.php';

// Simple authentication - you should implement proper admin authentication
$admin_password = 'admin123'; // Change this to a secure password

if ($_POST['action'] === 'login' && $_POST['password'] === $admin_password) {
    $_SESSION['admin_logged_in'] = true;
}

if ($_POST['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
}

// Update order status
if ($_POST['action'] === 'update_status' && isset($_SESSION['admin_logged_in'])) {
    try {
        $siparisDetayID = (int)$_POST['siparisDetayID'];
        $newStatus = $_POST['newStatus'];
        
        $stmt = $pdo->prepare("UPDATE siparisdetay SET durum = ? WHERE siparisDetayID = ?");
        $stmt->execute([$newStatus, $siparisDetayID]);
        
        $success_message = "Sipariş durumu başarıyla güncellendi!";
    } catch (Exception $e) {
        $error_message = "Hata: " . $e->getMessage();
    }
}

// Get all orders
$orders = [];
if (isset($_SESSION['admin_logged_in'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.siparisID,
                s.siparisNo,
                s.siparisTarihi,
                s.durum as siparisDurum,
                sd.siparisDetayID,
                sd.durum as detayDurum,
                u.urunAdi,
                m.ad,
                m.soyad,
                m.eposta
            FROM siparis s
            JOIN siparisdetay sd ON s.siparisID = sd.siparisID
            JOIN urun u ON sd.urunID = u.urunID
            JOIN musteri m ON s.musteriID = m.musteriID
            ORDER BY s.siparisTarihi DESC
        ");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_message = "Siparişler yüklenirken hata: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-Beklemede { background-color: #fff3cd; color: #856404; }
        .status-Hazirlaniyor { background-color: #d1ecf1; color: #0c5460; }
        .status-Gonderildi { background-color: #cce5ff; color: #004085; }
        .status-TeslimEdildi { background-color: #d4edda; color: #155724; }
        .status-IadeEdildi { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Sipariş Yönetimi</h1>
        
        <?php if (!isset($_SESSION['admin_logged_in'])): ?>
            <!-- Login Form -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Admin Girişi</h5>
                            <form method="post">
                                <input type="hidden" name="action" value="login">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Şifre:</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Giriş Yap</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Admin Panel -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Sipariş Listesi</h2>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-outline-secondary">Çıkış Yap</button>
                </form>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sipariş No</th>
                            <th>Tarih</th>
                            <th>Müşteri</th>
                            <th>Ürün</th>
                            <th>Sipariş Durumu</th>
                            <th>Detay Durumu</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['siparisNo']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($order['siparisTarihi'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($order['ad'] . ' ' . $order['soyad']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($order['eposta']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($order['urunAdi']); ?></td>
                                <td>
                                    <span class="badge status-<?php echo $order['siparisDurum']; ?>">
                                        <?php echo $order['siparisDurum']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge status-<?php echo $order['detayDurum']; ?>">
                                        <?php echo $order['detayDurum']; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="siparisDetayID" value="<?php echo $order['siparisDetayID']; ?>">
                                        <select name="newStatus" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                            <option value="Beklemede" <?php echo $order['detayDurum'] === 'Beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                            <option value="Hazirlaniyor" <?php echo $order['detayDurum'] === 'Hazirlaniyor' ? 'selected' : ''; ?>>Hazırlanıyor</option>
                                            <option value="Gonderildi" <?php echo $order['detayDurum'] === 'Gonderildi' ? 'selected' : ''; ?>>Gönderildi</option>
                                            <option value="TeslimEdildi" <?php echo $order['detayDurum'] === 'TeslimEdildi' ? 'selected' : ''; ?>>Teslim Edildi</option>
                                            <option value="IadeEdildi" <?php echo $order['detayDurum'] === 'IadeEdildi' ? 'selected' : ''; ?>>İade Edildi</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Güncelle</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-info mt-4">
                <h5>Önemli Bilgi:</h5>
                <p>Müşterilerin ürün yorumu yapabilmesi için sipariş detay durumunun <strong>"Teslim Edildi"</strong> olması gerekmektedir.</p>
                <p>Sipariş durumunu "Teslim Edildi" olarak güncelledikten sonra müşteriler ürün detay sayfasında yorum yapabileceklerdir.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 