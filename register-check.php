<?php
// Oturum başlat
session_start();

// Veritabanı bağlantısı
require_once 'dbcon.php';

// Aktif oturum bilgilerini göster
echo "<h2>Aktif Oturum Bilgileri</h2>";
if (isset($_SESSION['user'])) {
    echo "<p><strong>Oturum Durumu:</strong> Aktif</p>";
    echo "<p><strong>Kullanıcı ID:</strong> " . htmlspecialchars($_SESSION['user']['id']) . "</p>";
    echo "<p><strong>İsim:</strong> " . htmlspecialchars($_SESSION['user']['isim']) . "</p>";
    echo "<p><strong>Soyad:</strong> " . htmlspecialchars($_SESSION['user']['soyad']) . "</p>";
    echo "<p><strong>E-posta:</strong> " . htmlspecialchars($_SESSION['user']['email']) . "</p>";
    
    // Veritabanından kullanıcıyı kontrol et
    try {
        $stmt = $pdo->prepare("SELECT * FROM musteri WHERE musteriID = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<h2>Veritabanı Kullanıcı Bilgileri</h2>";
            echo "<p><strong>Kullanıcı ID:</strong> " . htmlspecialchars($user['musteriID']) . "</p>";
            echo "<p><strong>İsim:</strong> " . htmlspecialchars($user['ad']) . "</p>";
            echo "<p><strong>Soyad:</strong> " . htmlspecialchars($user['soyad']) . "</p>";
            echo "<p><strong>E-posta:</strong> " . htmlspecialchars($user['eposta']) . "</p>";
            echo "<p><strong>Aktif:</strong> " . ($user['aktif'] ? 'Evet' : 'Hayır') . "</p>";
            echo "<p><strong>Kayıt Tarihi:</strong> " . htmlspecialchars($user['kayitTarihi']) . "</p>";
            
            if ($_SESSION['user']['email'] !== $user['eposta'] || 
                $_SESSION['user']['isim'] !== $user['ad'] || 
                $_SESSION['user']['soyad'] !== $user['soyad']) {
                echo "<p style='color:red'><strong>UYARI:</strong> Oturum bilgileri ile veritabanı bilgileri arasında tutarsızlık var!</p>";
            } else {
                echo "<p style='color:green'><strong>Doğrulama:</strong> Oturum bilgileri veritabanı ile uyumlu.</p>";
            }
        } else {
            echo "<p style='color:red'><strong>HATA:</strong> Kullanıcı veritabanında bulunamadı!</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'><strong>Veritabanı Hatası:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p><strong>Oturum Durumu:</strong> Aktif Değil (Giriş yapılmamış)</p>";
}

// Oturum yenileme bağlantıları
echo "<hr>";
echo "<p><a href='logout.php'>Oturumu Kapat</a></p>";
echo "<p><a href='index.php'>Ana Sayfaya Dön</a></p>";
?> 