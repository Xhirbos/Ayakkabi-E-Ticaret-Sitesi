<?php
// Hata ayıklama için
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Admin oturum kontrolü
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Aktif sayfa kontrolü
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Header -->
        <header class="admin-header">
            <a href="dashboard.php" class="header-brand">
                <i class="fas fa-shield-alt"></i>
                Admin Panel
            </a>
            
            <nav class="header-nav">
                <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="carousel-yonetimi.php" class="nav-item <?php echo ($current_page == 'carousel-yonetimi.php') ? 'active' : ''; ?>">
                    <i class="fas fa-images"></i>
                    Carousel Yönetimi
                </a>
                <a href="magaza-basvurulari.php" class="nav-item <?php echo (in_array($current_page, ['magaza-basvurulari.php', 'bekleyen-basvurular.php', 'onaylanan-basvurular.php', 'reddedilen-basvurular.php'])) ? 'active' : ''; ?>">
                    <i class="fas fa-store"></i>
                    Mağaza Başvuruları
                </a>
                <a href="yorum-yonetimi.php" class="nav-item <?php echo (in_array($current_page, ['yorum-yonetimi.php', 'bekleyen-yorumlar.php', 'onaylanan-yorumlar.php', 'reddedilen-yorumlar.php'])) ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i>
                    Yorum Yönetimi
                </a>
                <a href="../index.php" target="_blank" class="nav-item">
                    <i class="fas fa-external-link-alt"></i>
                    Siteyi Görüntüle
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Çıkış
                </a>
            </nav>
        </header>
        
        <!-- Main Content -->
        <main class="admin-main"> 