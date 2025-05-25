<?php
// account-settings.php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, redirect to login page if not
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    // Store the current page to redirect back after login
    $_SESSION['redirect_after_login'] = 'account-settings.php';
    $_SESSION['open_login_modal'] = true; // To trigger modal on index.php
    header('Location: index.php');
    exit;
}

// Include database connection
require_once 'dbcon.php';

// Get user information
$userID = $_SESSION['user']['id'];
$stmt = $pdo->prepare("SELECT * FROM musteri WHERE musteriID = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch();

if (!$user) {
    // Handle case where user might have been deleted but session still exists
    unset($_SESSION['user']);
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Kullanıcı bulunamadı. Lütfen tekrar giriş yapın.'];
    $_SESSION['open_login_modal'] = true;
    header('Location: index.php');
    exit;
}

// Get user addresses
$addressStmt = $pdo->prepare("SELECT * FROM musteriadres WHERE musteriID = ? ORDER BY varsayilan DESC, olusturmaTarihi DESC");
$addressStmt->execute([$userID]);
$addresses = $addressStmt->fetchAll();

// Get all city and district information (Consider optimizing if this list becomes very large)
// $illerStmt = $pdo->prepare("SELECT DISTINCT il FROM musteriadres ORDER BY il");
// $illerStmt->execute();
// $iller = $illerStmt->fetchAll(PDO::FETCH_COLUMN);
// For now, we'll let users type city/district, can be enhanced with dropdowns later if needed.

// Handle success/error messages
$successMessage = $_SESSION['settings_success_message'] ?? '';
$errorMessage = $_SESSION['settings_error_message'] ?? '';
unset($_SESSION['settings_success_message'], $_SESSION['settings_error_message']);


// Process profile update
if (isset($_POST['update_profile'])) {
    $ad = trim($_POST['ad']);
    $soyad = trim($_POST['soyad']);
    $telefon = trim($_POST['telefon']);
    
    if (empty($ad) || empty($soyad) || empty($telefon)) {
        $_SESSION['settings_error_message'] = 'Lütfen tüm zorunlu alanları doldurun (Ad, Soyad, Telefon).';
    } else {
        try {
            $updateStmt = $pdo->prepare("UPDATE musteri SET ad = ?, soyad = ?, telefon = ? WHERE musteriID = ?");
            $result = $updateStmt->execute([$ad, $soyad, $telefon, $userID]);
            
            if ($result) {
                $_SESSION['user']['isim'] = $ad; // Update session immediately
                $_SESSION['user']['soyad'] = $soyad;
                $_SESSION['settings_success_message'] = 'Profil bilgileriniz başarıyla güncellendi.';
                // Refresh user data after update
                $stmt = $pdo->prepare("SELECT * FROM musteri WHERE musteriID = ?");
                $stmt->execute([$userID]);
                $user = $stmt->fetch();
            } else {
                $_SESSION['settings_error_message'] = 'Profil güncellenirken bir hata oluştu.';
            }
        } catch (PDOException $e) {
            $_SESSION['settings_error_message'] = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
    header("Location: account-settings.php#profile-section");
    exit;
}

// Process add/update address
if (isset($_POST['save_address'])) {
    $adresID = isset($_POST['adresID']) ? intval($_POST['adresID']) : 0;
    $baslik = trim($_POST['baslik']);
    $adres = trim($_POST['adres']);
    $il = trim($_POST['il']);
    $ilce = trim($_POST['ilce']);
    $postaKodu = trim($_POST['postaKodu']);
    $varsayilan = isset($_POST['varsayilan']) ? 1 : 0;
    
    if (empty($baslik) || empty($adres) || empty($il) || empty($ilce)) {
        $_SESSION['settings_error_message'] = 'Lütfen tüm zorunlu adres alanlarını doldurun (Başlık, Adres, İl, İlçe).';
    } else {
        try {
            if ($varsayilan) {
                $resetDefaultStmt = $pdo->prepare("UPDATE musteriadres SET varsayilan = 0 WHERE musteriID = ?");
                $resetDefaultStmt->execute([$userID]);
            }
            
            if ($adresID > 0) {
                $addressUpdateStmt = $pdo->prepare("
                    UPDATE musteriadres 
                    SET baslik = ?, adres = ?, il = ?, ilce = ?, postaKodu = ?, varsayilan = ?, guncellemeTarihi = NOW() 
                    WHERE adresID = ? AND musteriID = ?
                ");
                $result = $addressUpdateStmt->execute([$baslik, $adres, $il, $ilce, $postaKodu, $varsayilan, $adresID, $userID]);
                $_SESSION['settings_success_message'] = $result ? 'Adres başarıyla güncellendi.' : 'Adres güncellenirken bir hata oluştu.';
            } else {
                $addressInsertStmt = $pdo->prepare("
                    INSERT INTO musteriadres (musteriID, baslik, adres, il, ilce, postaKodu, ulke, varsayilan, olusturmaTarihi, guncellemeTarihi)
                    VALUES (?, ?, ?, ?, ?, ?, 'Türkiye', ?, NOW(), NOW())
                ");
                $result = $addressInsertStmt->execute([$userID, $baslik, $adres, $il, $ilce, $postaKodu, $varsayilan]);
                $_SESSION['settings_success_message'] = $result ? 'Yeni adres başarıyla eklendi.' : 'Adres eklenirken bir hata oluştu.';
            }
        } catch (PDOException $e) {
            $_SESSION['settings_error_message'] = 'Adres veritabanı hatası: ' . $e->getMessage();
        }
    }
    header("Location: account-settings.php#address-section");
    exit;
}

// Process delete address
if (isset($_POST['delete_address'])) {
    $adresID = intval($_POST['adresID_delete']); // Changed name to avoid conflict if JS fails
    
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM musteriadres WHERE adresID = ? AND musteriID = ?");
        $result = $deleteStmt->execute([$adresID, $userID]);
        $_SESSION['settings_success_message'] = $result ? 'Adres başarıyla silindi.' : 'Adres silinirken bir hata oluştu.';
    } catch (PDOException $e) {
        $_SESSION['settings_error_message'] = 'Adres silme veritabanı hatası: ' . $e->getMessage();
    }
    header("Location: account-settings.php#address-section");
    exit;
}

// Process password update
if (isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $_SESSION['settings_error_message'] = 'Lütfen tüm şifre alanlarını doldurun.';
    } elseif ($newPassword !== $confirmPassword) {
        $_SESSION['settings_error_message'] = 'Yeni şifre ve şifre tekrarı eşleşmiyor.';
    } elseif (strlen($newPassword) < 6) {
        $_SESSION['settings_error_message'] = 'Yeni şifre en az 6 karakter olmalıdır.';
    } else {
        if (password_verify($currentPassword, $user['sifre'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePasswordStmt = $pdo->prepare("UPDATE musteri SET sifre = ? WHERE musteriID = ?");
            $result = $updatePasswordStmt->execute([$hashedPassword, $userID]);
            $_SESSION['settings_success_message'] = $result ? 'Şifreniz başarıyla güncellendi.' : 'Şifre güncellenirken bir hata oluştu.';
        } else {
            $_SESSION['settings_error_message'] = 'Mevcut şifreniz hatalı.';
        }
    }
    header("Location: account-settings.php#password-section");
    exit;
}

// Refresh messages after POST operations, in case they were set by post handlers
$successMessage = $_SESSION['settings_success_message'] ?? $successMessage;
$errorMessage = $_SESSION['settings_error_message'] ?? $errorMessage;
unset($_SESSION['settings_success_message'], $_SESSION['settings_error_message']);

// Refresh addresses if they might have changed
$addressStmt = $pdo->prepare("SELECT * FROM musteriadres WHERE musteriID = ? ORDER BY varsayilan DESC, olusturmaTarihi DESC");
$addressStmt->execute([$userID]);
$addresses = $addressStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesap Ayarları - Adım Adım</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* Account dropdown styles */
        .header-button {
            position: relative;
        }
        
        .account-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 200px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 100;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .header-button:hover .account-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        /* Top bar dropdown fix */
        .top-bar-right {
            position: relative;
        }
        
        .top-bar-right span {
            position: relative;
            cursor: pointer;
        }
        
        .top-bar-right span:hover + .account-dropdown,
        .top-bar-right .account-dropdown:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .account-dropdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .account-dropdown ul li {
            border-bottom: 1px solid #f1f1f1;
        }
        
        .account-dropdown ul li:last-child {
            border-bottom: none;
        }
        
        .account-dropdown ul li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .account-dropdown ul li a:hover {
            background: #f9f9f9;
            color: #e63946;
        }
        
        .account-dropdown ul li a i {
            margin-right: 10px;
            color: #e63946;
            width: 16px;
            text-align: center;
        }

        /* Account nav stillerini düzeltme */
        #account-nav .list-group-item-action.active {
            background-color: #e63946 !important;
            border-color: #e63946 !important;
            color: #fff !important;
        }
        
        /* Account sayfası için özel stiller */
        .account-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            margin-bottom: 25px;
        }
        
        /* Modal specific styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1055 !important;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            pointer-events: none;
        }
        
        .modal.show {
            display: block;
        }
        
        .modal-dialog {
            position: relative;
            width: auto;
            margin: 1.75rem auto;
            max-width: 500px;
            pointer-events: auto;
            z-index: 1060 !important;
        }
        
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 3.5rem);
        }
        
        .modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: #fff;
            border-radius: 0.3rem;
            outline: 0;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            z-index: 1060 !important;
        }
        
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040 !important;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-backdrop.show {
            opacity: 0.5;
        }
        
        /* Override z-index to make sure dropdown works properly */
        .account-dropdown {
            z-index: 1000;
        }
        
        /* Make sure modal is visible */
        .modal-open .modal {
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        /* Fix any potential conflicts with Bootstrap's modal styles */
        .modal-open {
            overflow: hidden;
            padding-right: 15px; /* Prevent content shift */
        }

        /* Ensure form controls are clickable */
        .modal .form-control,
        .modal .form-check-input,
        .modal .btn {
            position: relative;
            z-index: 1061 !important;
            pointer-events: auto !important;
        }
        
        /* Modal Fix - Ensure modal works properly */
        .modal-body {
            pointer-events: auto !important;
        }
        
        .modal-header {
            pointer-events: auto !important;
        }
        
        .modal-footer {
            pointer-events: auto !important;
        }
        
        /* Modal Backdrop Fix */
        body.modal-open .modal {
            pointer-events: auto !important;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<!-- Add jQuery script before using any jQuery functions -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Ensure dropdown works by adding event handlers directly
$(document).ready(function() {
    // Top bar dropdown
    $('.top-bar-right > span').hover(
        function() {
            $(this).next('.account-dropdown').css({
                'opacity': '1',
                'visibility': 'visible',
                'transform': 'translateY(0)'
            });
        },
        function() {
            // Don't hide if mouse is still over dropdown
            if (!$(this).next('.account-dropdown').is(':hover')) {
                $(this).next('.account-dropdown').css({
                    'opacity': '0',
                    'visibility': 'hidden',
                    'transform': 'translateY(10px)'
                });
            }
        }
    );
    
    // Keep dropdown visible when hovering over it
    $('.account-dropdown').hover(
        function() {
            $(this).css({
                'opacity': '1',
                'visibility': 'visible',
                'transform': 'translateY(0)'
            });
        },
        function() {
            $(this).css({
                'opacity': '0',
                'visibility': 'hidden',
                'transform': 'translateY(10px)'
            });
        }
    );
    
    // Main header account button dropdown
    $('.header-button#account-btn').hover(
        function() {
            $(this).find('.account-dropdown').css({
                'opacity': '1',
                'visibility': 'visible',
                'transform': 'translateY(0)'
            });
        },
        function() {
            $(this).find('.account-dropdown').css({
                'opacity': '0',
                'visibility': 'hidden',
                'transform': 'translateY(10px)'
            });
        }
    );
});
</script>

<main class="container my-5">
    <h2 class="section-title text-center mb-4">Hesap Ayarlarım</h2>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($successMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($errorMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Sidebar Navigation -->
        <div class="col-lg-3">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>Hesap Yönetimi</h5>
                </div>
                <div class="list-group list-group-flush" id="account-nav">
                    <a href="#profile-section" class="list-group-item list-group-item-action">
                        <i class="fas fa-id-card me-2 text-primary"></i>Profil Bilgileri
                    </a>
                    <a href="#address-section" class="list-group-item list-group-item-action">
                        <i class="fas fa-map-marker-alt me-2 text-primary"></i>Adres Bilgileri
                    </a>
                    <a href="#password-section" class="list-group-item list-group-item-action">
                        <i class="fas fa-lock me-2 text-primary"></i>Şifre Değiştir
                    </a>
                    <a href="orders.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-box me-2 text-primary"></i>Siparişlerim
                    </a>
                    <a href="index.php?logout=1" class="list-group-item list-group-item-action text-danger">
                        <i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Right Content Area -->
        <div class="col-lg-9">
            <!-- Profile Section -->
            <div id="profile-section" class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Profil Bilgileri</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="account-settings.php">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ad" class="form-label">Ad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ad" name="ad" value="<?php echo htmlspecialchars($user['ad']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="soyad" class="form-label">Soyad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="soyad" name="soyad" value="<?php echo htmlspecialchars($user['soyad']); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta Adresi</label>
                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['eposta']); ?>" readonly disabled>
                            <small class="text-muted">E-posta adresiniz değiştirilemez.</small>
                        </div>
                        <div class="mb-3">
                            <label for="telefon" class="form-label">Telefon <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="telefon" name="telefon" value="<?php echo htmlspecialchars($user['telefon']); ?>" placeholder="Örn: 5xxxxxxxxx" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Bilgilerimi Güncelle
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Address Section -->
            <div id="address-section" class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Adres Bilgileri</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addressModal" id="addNewAddressBtn">
                        <i class="fas fa-plus me-1"></i>Yeni Adres Ekle
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($addresses)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Henüz kayıtlı adresiniz bulunmamaktadır.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($addresses as $address): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 <?php echo $address['varsayilan'] ? 'border-primary shadow' : 'shadow-sm'; ?>">
                                        <div class="card-body">
                                            <h6 class="card-title d-flex justify-content-between">
                                                <?php echo htmlspecialchars($address['baslik']); ?>
                                                <?php if ($address['varsayilan']): ?>
                                                    <span class="badge bg-primary">Varsayılan</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="card-text small">
                                                <?php echo nl2br(htmlspecialchars($address['adres'])); ?><br>
                                                <?php echo htmlspecialchars($address['ilce']); ?> / <?php echo htmlspecialchars($address['il']); ?><br>
                                                <?php if (!empty($address['postaKodu'])): ?>
                                                    Posta Kodu: <?php echo htmlspecialchars($address['postaKodu']); ?><br>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($address['ulke'] ?? 'Türkiye'); ?>
                                            </p>
                                        </div>
                                        <div class="card-footer bg-light">
                                            <div class="btn-group btn-group-sm w-100">
                                                <button type="button" class="btn btn-outline-primary edit-address" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#addressModal"
                                                        data-address-id="<?php echo $address['adresID']; ?>"
                                                        data-baslik="<?php echo htmlspecialchars($address['baslik']); ?>"
                                                        data-adres="<?php echo htmlspecialchars($address['adres']); ?>"
                                                        data-il="<?php echo htmlspecialchars($address['il']); ?>"
                                                        data-ilce="<?php echo htmlspecialchars($address['ilce']); ?>"
                                                        data-postakodu="<?php echo htmlspecialchars($address['postaKodu'] ?? ''); ?>"
                                                        data-varsayilan="<?php echo $address['varsayilan']; ?>">
                                                    <i class="fas fa-edit"></i> Düzenle
                                                </button>
                                                <button type="button" class="btn btn-outline-danger delete-address-btn" data-address-id="<?php echo $address['adresID']; ?>" data-address-title="<?php echo htmlspecialchars($address['baslik']); ?>">
                                                    <i class="fas fa-trash"></i> Sil
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Password Change Section -->
            <div id="password-section" class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Şifre Değiştir</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="account-settings.php">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mevcut Şifre <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Yeni Şifre <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password" name="new_password" aria-describedby="passwordHelp" required>
                            <small id="passwordHelp" class="form-text text-muted">Şifre en az 6 karakter olmalıdır.</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Şifre Tekrar <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="update_password" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Şifreyi Güncelle
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Address Modal -->
<div class="modal fade" id="addressModal" tabindex="-1" aria-labelledby="addressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="account-settings.php" id="addressForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addressModalLabel">Adres Bilgileri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalAdresID" name="adresID" value="0">
                    <div class="mb-3">
                        <label for="modalBaslik" class="form-label">Adres Başlığı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modalBaslik" name="baslik" placeholder="Ev, İş vb." required>
                    </div>
                    <div class="mb-3">
                        <label for="modalAdres" class="form-label">Adres <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="modalAdres" name="adres" rows="3" required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modalIl" class="form-label">İl <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modalIl" name="il" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modalIlce" class="form-label">İlçe <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modalIlce" name="ilce" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modalPostaKodu" class="form-label">Posta Kodu</label>
                        <input type="text" class="form-control" id="modalPostaKodu" name="postaKodu">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="modalVarsayilan" name="varsayilan">
                        <label class="form-check-label" for="modalVarsayilan">Varsayılan Adres Olarak Ayarla</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="save_address" class="btn btn-primary"><i class="fas fa-save me-2"></i>Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Delete Address Form -->
<form method="post" id="deleteAddressForm_hidden" action="account-settings.php" class="d-none">
    <input type="hidden" name="adresID_delete" id="adresID_to_delete">
    <input type="hidden" name="delete_address" value="1">
</form>

<?php include 'footer.php'; ?>
<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal first
    const addressModalElement = document.getElementById('addressModal');
    let addressModal;
    
    // Check if Bootstrap 5 Modal is available
    if (typeof bootstrap !== 'undefined') {
        addressModal = new bootstrap.Modal(addressModalElement, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
    }
    
    const addressForm = document.getElementById('addressForm');
    const modalAdresID = document.getElementById('modalAdresID');
    const modalBaslik = document.getElementById('modalBaslik');
    const modalAdres = document.getElementById('modalAdres');
    const modalIl = document.getElementById('modalIl');
    const modalIlce = document.getElementById('modalIlce');
    const modalPostaKodu = document.getElementById('modalPostaKodu');
    const modalVarsayilan = document.getElementById('modalVarsayilan');
    const addressModalLabel = document.getElementById('addressModalLabel');

    // Handle "Yeni Adres Ekle" button
    const addNewAddressBtn = document.getElementById('addNewAddressBtn');
    if (addNewAddressBtn) {
        addNewAddressBtn.addEventListener('click', function() {
            // Reset form fields without preventing default behavior
            addressForm.reset(); 
            modalAdresID.value = '0'; // Important for new address
            addressModalLabel.textContent = 'Yeni Adres Ekle';
            
            // Let Bootstrap handle the modal opening
            // DO NOT call addressModal.show() here
        });
    }

    // Handle "Edit Address" buttons
    document.querySelectorAll('.edit-address').forEach(button => {
        button.addEventListener('click', function() {
            // Fill form fields
            modalAdresID.value = this.dataset.addressId;
            modalBaslik.value = this.dataset.baslik;
            modalAdres.value = this.dataset.adres;
            modalIl.value = this.dataset.il;
            modalIlce.value = this.dataset.ilce;
            modalPostaKodu.value = this.dataset.postakodu;
            modalVarsayilan.checked = parseInt(this.dataset.varsayilan) === 1;
            addressModalLabel.textContent = 'Adres Düzenle';
            
            // Bootstrap will handle modal opening via data-bs attributes
            if (addressModal) {
                addressModal.show();
            }
        });
    });

    // Handle form submission
    addressForm.addEventListener('submit', function(e) {
        // Validate form fields
        const requiredFields = addressForm.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            return false;
        }
        
        // Don't hide modal, form submission will do that
        return true;
    });

    // Handle "Delete Address" buttons
    const hiddenDeleteForm = document.getElementById('deleteAddressForm_hidden');
    const hiddenAdresIDInput = document.getElementById('adresID_to_delete');

    document.querySelectorAll('.delete-address-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const addressId = this.dataset.addressId;
            const addressTitle = this.dataset.addressTitle;
            if (confirm('"' + addressTitle + '" başlıklı adresi silmek istediğinizden emin misiniz?')) {
                hiddenAdresIDInput.value = addressId;
                hiddenDeleteForm.submit();
            }
        });
    });
    
    // Sidebar navigation active state and smooth scroll
    const accountNavLinks = document.querySelectorAll('#account-nav .list-group-item-action');
    const sections = document.querySelectorAll('.col-lg-9 > .card[id]');

    function activateNavLink() {
        let currentSectionId = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop - 150; // Adjust offset as needed
            if (window.scrollY >= sectionTop) {
                currentSectionId = section.getAttribute('id');
            }
        });

        let activeFound = false;
        accountNavLinks.forEach(link => {
            link.classList.remove('active', 'bg-primary', 'text-white');
            if (link.hash === '#' + currentSectionId) {
                link.classList.add('active', 'bg-primary', 'text-white');
                activeFound = true;
            }
        });
        // If no section is actively scrolled to but there's a hash, activate based on hash
        if (!activeFound && window.location.hash) {
             const activeLinkByHash = document.querySelector(`#account-nav a[href="${window.location.hash}"]`);
             if(activeLinkByHash) {
                activeLinkByHash.classList.add('active', 'bg-primary', 'text-white');
             }
        } else if (!activeFound && accountNavLinks.length > 0) {
            // Default to first item if no hash and no section actively scrolled to (e.g. on page load at top)
             const firstValidLink = Array.from(accountNavLinks).find(link => link.getAttribute('href').startsWith('#'));
             if (firstValidLink && (!window.location.hash || window.location.hash === "#")) {
                firstValidLink.classList.add('active', 'bg-primary', 'text-white');
            }
        }
    }
    
    // Smooth scroll for sidebar links
    accountNavLinks.forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetHref = this.getAttribute('href');
            if (targetHref.startsWith('#')) {
                e.preventDefault();
                const targetElement = document.querySelector(targetHref);
                if (targetElement) {
                    // Manually update hash, then scroll, then update active state
                    // history.pushState(null, null, targetHref); // Causes page jump if not handled carefully
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                    // Activate link immediately for better UX, scroll event will refine
                    accountNavLinks.forEach(link => link.classList.remove('active', 'bg-primary', 'text-white'));
                    this.classList.add('active', 'bg-primary', 'text-white');
                }
            }
            // For external links like orders.php or logout, let the default action proceed.
        });
    });

    window.addEventListener('scroll', activateNavLink);
    // Call on load to set initial active state based on hash or top position
    activateNavLink(); 

    // If there's a hash in the URL (e.g., from a form submission redirect), scroll to it.
    if (window.location.hash) {
        const targetElement = document.querySelector(window.location.hash);
        if (targetElement) {
            // Timeout to ensure DOM is fully ready and other scripts have run
            setTimeout(() => {
                targetElement.scrollIntoView({ behavior: 'smooth' });
                activateNavLink(); // Re-check active link after scroll
            }, 100);
        }
    }
});
</script>
</body>
</html> 