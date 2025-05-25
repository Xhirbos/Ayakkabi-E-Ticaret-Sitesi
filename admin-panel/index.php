<?php
// Hata ayıklama için
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Oturum kontrolü
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

// Test için veritabanı bağlantısı
$db_connection_test = false;

// Login işlemi
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once '../dbcon.php';
    
    $eposta = trim($_POST['eposta']);
    $sifre = trim($_POST['sifre']);
    
    if (empty($eposta) || empty($sifre)) {
        $error = "E-posta ve şifre alanlarını doldurun.";
    } else {
        try {
            // Personel tablosundan admin rolüne sahip kullanıcıyı ara
            $stmt = $conn->prepare("SELECT personelID, ad, soyad, sifre FROM personel WHERE eposta = ? AND rol = 'Admin' AND aktif = 1");
            $stmt->bind_param("s", $eposta);
            $stmt->execute();
            $result = $stmt->get_result();
            $db_connection_test = true;
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Gerçek bir sistemde şifre kontrolü şöyle olmalı:
                // if (password_verify($sifre, $user['sifre'])) {
                if ($sifre === $user['sifre']) { // Basit kontrol - geliştirme aşamasında
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $user['personelID'];
                    $_SESSION['admin_name'] = $user['ad'] . ' ' . $user['soyad'];
                    
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Geçersiz şifre.";
                }
            } else {
                $error = "Bu e-posta adresiyle kayıtlı bir admin hesabı bulunamadı.";
            }
            
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli - Giriş</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #007bff;
            color: white;
            text-align: center;
            font-weight: bold;
            padding: 1rem;
        }
        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Admin Paneli Girişi</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($db_connection_test): ?>
                    
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="eposta" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="eposta" name="eposta" value="<?php echo isset($eposta) ? htmlspecialchars($eposta) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="sifre" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="sifre" name="sifre" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Giriş Yap</button>
                </form>
                
                <div class="mt-3">
                    <p class="text-muted small text-center">Admin Paneli</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 