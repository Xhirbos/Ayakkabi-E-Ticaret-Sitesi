<?php
session_start();
require_once('../dbcon.php');

// Eğer zaten giriş yapılmışsa, panel ana sayfasına yönlendir
if (isset($_SESSION['magazaID'])) {
    header('Location: dashboard.php');
    exit;
}

$error = "";

// Form gönderildi mi kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eposta = trim($_POST['eposta']);
    $sifre = trim($_POST['sifre']);
    
    if (empty($eposta) || empty($sifre)) {
        $error = "E-posta ve şifre alanlarını doldurunuz.";
    } else {
        // Bilgileri doğrula
        $stmt = $conn->prepare("SELECT * FROM magaza WHERE eposta = ? LIMIT 1");
        $stmt->bind_param("s", $eposta);
        $stmt->execute();
        $result = $stmt->get_result();
        $magaza = $result->fetch_assoc();
        
        if ($magaza) {
            // Mağaza aktif mi kontrol et
            if ($magaza['aktif'] != 1) {
                $error = "Bu mağaza hesabı aktif değil. Lütfen yönetici ile iletişime geçiniz.";
            } 
            // Şifre doğru mu kontrol et
            else if ($sifre === $magaza['sifre']) { // Gerçek uygulamada password_verify kullanmalısınız
                // Başvuru durumunu kontrol et
                if ($magaza['basvuruDurumu'] === 'Beklemede') {
                    $error = "Mağaza başvurunuz hala inceleme aşamasındadır. Onaylandıktan sonra giriş yapabilirsiniz.";
                } 
                else if ($magaza['basvuruDurumu'] === 'Reddedildi') {
                    $error = "Mağaza başvurunuz reddedilmiştir. Detaylı bilgi için bizimle iletişime geçiniz.";
                }
                else {
                    // Oturum bilgilerini kaydet
                    $_SESSION['magazaID'] = $magaza['magazaID'];
                    $_SESSION['magazaAdi'] = $magaza['magazaAdi'];
                    
                    // Ana sayfaya yönlendir
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = "Geçersiz şifre! Lütfen tekrar deneyiniz.";
            }
        } else {
            $error = "Bu e-posta adresi ile kayıtlı mağaza bulunamadı.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mağaza Yönetim Paneli - Giriş</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 450px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo h1 {
            color: #343a40;
        }
        .login-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-logo">
                <h1>Mağaza Paneli</h1>
                <p class="text-muted">Mağaza yönetim paneline giriş yapın</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="eposta" class="form-label">E-posta Adresi</label>
                    <input type="email" class="form-control" id="eposta" name="eposta" value="<?php echo isset($_POST['eposta']) ? htmlspecialchars($_POST['eposta']) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="sifre" class="form-label">Şifre</label>
                    <input type="password" class="form-control" id="sifre" name="sifre" required>
                </div>
                <div class="login-actions">
                    <button type="submit" class="btn btn-primary">Giriş Yap</button>
                    <a href="../magaza-basvuru.php" class="btn btn-outline-secondary">Mağaza Başvurusu Yap</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 