<?php
// Hata ayıklama için
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sadece veritabanı bağlantısını dahil et
require_once 'dbcon.php';

$success = false;
$error = "";

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $magazaAdi = trim($_POST['magazaAdi']);
    $adres = trim($_POST['adres']);
    $eposta = trim($_POST['eposta']);
    $sifre = trim($_POST['sifre']);
    $sifreOnay = trim($_POST['sifreOnay']);
    $telefon = trim($_POST['telefon']);
    
    // Boş alan kontrolü
    if (empty($magazaAdi) || empty($adres) || empty($eposta) || empty($sifre) || empty($telefon)) {
        $error = "Lütfen tüm alanları doldurunuz.";
    } 
    // Şifre onay kontrolü
    else if ($sifre !== $sifreOnay) {
        $error = "Şifreler eşleşmiyor.";
    } 
    // E-posta formatı kontrolü
    else if (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçerli bir e-posta adresi giriniz.";
    } 
    else {
        // E-posta adresi zaten kayıtlı mı kontrol et
        $check_stmt = $conn->prepare("SELECT * FROM magaza WHERE eposta = ? LIMIT 1");
        $check_stmt->bind_param("s", $eposta);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->fetch_assoc()) {
            $error = "Bu e-posta adresi ile kayıtlı bir mağaza zaten bulunmaktadır.";
        } else {
            try {
                // Geçici olarak personelID sabit bir değer kullanılıyor (gerçek uygulamada değişmeli)
                $personelID = 2; // personel tablosundan var olan bir ID
                $basvuruDurumu = 'Beklemede';
                
                // Mağaza kaydını oluştur
                $stmt = $conn->prepare("INSERT INTO magaza (magazaAdi, adres, eposta, sifre, telefon, personelID, basvuruDurumu) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $magazaAdi, $adres, $eposta, $sifre, $telefon, $personelID, $basvuruDurumu);
                
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Başvuru kaydedilirken bir hata oluştu.";
                }
            } catch (Exception $e) {
                $error = "Başvuru sırasında bir hata oluştu: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mağaza Başvurusu - ADIM ADIM</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .navbar {
        background-color: #fff;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    .navbar-brand {
        font-size: 24px;
        font-weight: bold;
        color: #e63946 !important;
    }
    
    .store-application-form {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .store-application-form .card-header {
        padding: 20px;
        background-color: #e63946 !important;
        color: white !important;
        border-bottom: none;
    }
    
    .store-application-form .card-body {
        padding: 30px;
    }
    
    .store-application-form .form-label {
        font-weight: 500;
        margin-bottom: 8px;
        color: #333;
    }
    
    .store-application-form .form-control {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 12px 15px;
        margin-bottom: 10px;
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    
    .store-application-form .form-control:focus {
        border-color: #e63946;
        box-shadow: 0 0 0 0.2rem rgba(230, 57, 70, 0.25);
    }
    
    .store-application-form .btn-primary {
        background-color: #e63946;
        border: none;
        padding: 12px 20px;
        font-weight: 500;
        transition: background-color 0.3s;
    }
    
    .store-application-form .btn-primary:hover {
        background-color: #d62839;
    }
    
    .store-application-form .btn-outline-secondary {
        border-color: #6c757d;
        color: #6c757d;
        padding: 12px 20px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .store-application-form .btn-outline-secondary:hover {
        background-color: #6c757d;
        color: white;
    }
    
    .form-check-input:checked {
        background-color: #e63946;
        border-color: #e63946;
    }
    
    .footer {
        background-color: #f8f9fa;
        padding: 15px 0;
        margin-top: 40px;
        text-align: center;
        color: #6c757d;
        font-size: 14px;
    }
    </style>
</head>
<body>
    <!-- Basit navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">ADIM ADIM</a>
            <a href="magaza-panel/index.php" class="btn btn-outline-primary ms-auto">Mağaza Girişi</a>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow store-application-form">
                    <div class="card-header bg-primary">
                        <h3 class="mb-0">Mağaza Başvurusu</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <h4 class="alert-heading">Başvurunuz Alındı!</h4>
                                <p>Mağaza başvurunuz başarıyla alınmıştır. Başvurunuz incelendikten sonra size e-posta ile bilgi verilecektir.</p>
                                <hr>
                                <p class="mb-0">Başvurunuz onaylandıktan sonra mağaza panelinize giriş yapabileceksiniz.</p>
                                <a href="magaza-panel/index.php" class="btn btn-primary mt-3">Mağaza Giriş Sayfasına Dön</a>
                            </div>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="magazaAdi" class="form-label">Mağaza Adı *</label>
                                    <input type="text" class="form-control" id="magazaAdi" name="magazaAdi" value="<?php echo isset($_POST['magazaAdi']) ? htmlspecialchars($_POST['magazaAdi']) : ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="adres" class="form-label">Adres *</label>
                                    <textarea class="form-control" id="adres" name="adres" rows="3" required><?php echo isset($_POST['adres']) ? htmlspecialchars($_POST['adres']) : ''; ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="eposta" class="form-label">E-posta *</label>
                                    <input type="email" class="form-control" id="eposta" name="eposta" value="<?php echo isset($_POST['eposta']) ? htmlspecialchars($_POST['eposta']) : ''; ?>" required>
                                    <div class="form-text">Bu e-posta adresi mağaza giriş işlemleri için kullanılacaktır.</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sifre" class="form-label">Şifre *</label>
                                        <input type="password" class="form-control" id="sifre" name="sifre" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="sifreOnay" class="form-label">Şifre Tekrar *</label>
                                        <input type="password" class="form-control" id="sifreOnay" name="sifreOnay" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="telefon" class="form-label">Telefon *</label>
                                    <input type="text" class="form-control" id="telefon" name="telefon" value="<?php echo isset($_POST['telefon']) ? htmlspecialchars($_POST['telefon']) : ''; ?>" required>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="sozlesme" name="sozlesme" required>
                                    <label class="form-check-label" for="sozlesme">Kullanım şartlarını ve gizlilik politikasını okudum, kabul ediyorum. *</label>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Başvuruyu Gönder</button>
                                    <a href="magaza-panel/index.php" class="btn btn-outline-secondary">Giriş Sayfasına Dön</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>© 2023 ADIM ADIM - Tüm Hakları Saklıdır.</p>
        </div>
    </footer>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 