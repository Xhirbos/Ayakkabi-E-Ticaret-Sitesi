<?php
// Enable all error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    // Set session cookie parameters for better security and compatibility
    session_set_cookie_params([
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax' // Add SameSite attribute for better CSRF protection
    ]);
    session_start();
    
    // For debugging
    error_log("Header.php: Starting new session with ID: " . session_id());
} else {
    error_log("Header.php: Using existing session with ID: " . session_id());
}

// Log current session data for debugging
error_log("Header.php: SESSION user data: " . (isset($_SESSION['user']) ? json_encode($_SESSION['user']) : 'No user session'));

require_once 'dbcon.php';

// If cart.php is not already included
if (!function_exists('getCartCount')) {
    require_once 'cart.php';
}

// Get cart count if user is logged in
$cartCount = 0;
if (isset($_SESSION['user'])) {
    $cartCount = getCartCount($pdo, $_SESSION['user']['id']);
}

// Kategorileri veritabanından çek
try {
    $stmtKategoriler = $pdo->prepare("SELECT kategoriID, kategoriAdi FROM kategori ORDER BY kategoriAdi");
    $stmtKategoriler->execute();
    $kategoriler = $stmtKategoriler->fetchAll();
} catch (PDOException $e) {
    error_log("Kategori çekme hatası: " . $e->getMessage());
    $kategoriler = []; // Hata durumunda boş array
}
?>
<header>
    <div class="top-bar">
        <div class="container top-bar-content">
            <div class="top-bar-left">
                <a href="#"><i class="fas fa-phone-alt"></i> 0850 123 45 67</a>
                <a href="#"><i class="fas fa-map-marker-alt"></i> Mağazalar</a>
                <a href="#"><i class="fas fa-box"></i> Sipariş Takibi</a>
            </div>
            <div class="top-bar-right">
                <?php if (!isset($_SESSION['user'])): ?>
                    <a href="#" id="open-login">Giriş Yap / Üye Ol</a>
                    <a href="magaza-panel/index.php"><i class="fas fa-store"></i> Mağaza Girişi</a>
                <?php else: ?>
                    <a href="account-settings.php"><?php echo $_SESSION['user']['isim'] . ' ' . (isset($_SESSION['user']['soyad']) ? $_SESSION['user']['soyad'] : (isset($_SESSION['user']['soyisim']) ? $_SESSION['user']['soyisim'] : '')); ?></a>
                    <a href="magaza-panel/index.php"><i class="fas fa-store"></i> Mağaza Girişi</a>
                <?php endif; ?>
                <a href="#"><i class="fas fa-heart"></i> Favorilerim</a>
            </div>
        </div>
    </div>
    
    <div class="container main-header">
        <a href="index.php" class="logo">ADIM ADIM</a>
        
        <form action="search.php" method="GET" class="search-bar">
            <input type="text" name="q" placeholder="Ne aramıştınız?" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
        
        <div class="header-buttons">
            <div class="header-button" id="account-btn">
                <i class="fas fa-user"></i>
                <?php if (isset($_SESSION['user'])): ?>
                    <span><?php echo $_SESSION['user']['isim'] . ' ' . (isset($_SESSION['user']['soyad']) ? $_SESSION['user']['soyad'] : (isset($_SESSION['user']['soyisim']) ? $_SESSION['user']['soyisim'] : '')); ?></span>
                    <div class="account-dropdown">
                        <ul>
                            <li><a href="orders.php"><i class="fas fa-box"></i> Siparişlerim</a></li>
                            <li><a href="account-settings.php"><i class="fas fa-cog"></i> Hesap Ayarları</a></li>
                            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <span>Hesabım</span>
                <?php endif; ?>
            </div>
            <div class="header-button">
                <i class="fas fa-heart"></i>
                <span>Favorilerim</span>
            </div>
            <div class="header-button cart-button">
                <?php if (isset($_SESSION['user'])): ?>
                <a href="cart-page.php" class="cart-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sepetim</span>
                    <?php if ($cartCount > 0): ?>
                    <span class="cart-count"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
                <?php else: ?>
                <a href="javascript:void(0);" class="cart-link" id="cart-login-trigger">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sepetim</span>
                </a>
                <?php endif; ?>
                <?php if (isset($_SESSION['user']) && $cartCount > 0): ?>
                <div class="cart-dropdown">
                    <div class="cart-dropdown-header">
                        <h5>Sepetim (<?php echo $cartCount; ?> Ürün)</h5>
                    </div>
                    <div class="cart-dropdown-items" id="cart-preview-items">
                        <div class="loading">Yükleniyor...</div>
                    </div>
                    <div class="cart-dropdown-footer">
                        <div class="cart-total" id="cart-preview-total">Toplam: 0,00 TL</div>
                        <a href="cart-page.php" class="btn btn-primary btn-sm">Sepete Git</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <nav class="main-nav">
        <div class="container">
            <ul class="nav-menu">
                <?php foreach ($kategoriler as $kategori): ?>
                    <li><a href="category.php?id=<?php echo $kategori['kategoriID']; ?>"><?php echo $kategori['kategoriAdi']; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>
</header>

<!-- Giriş ve Üye Ol Modal -->
<?php if (!isset($_SESSION['user'])): ?>
<div id="login-modal" class="modal">
    <div class="modal-content login-content">
        <span class="close" id="close-login">&times;</span>
        <h2>Giriş Yap</h2>
        <div class="error-messages" id="login-error-container" style="display:none;">
            <p class="error" id="login-error-message"></p>
        </div>
        <form id="login-form" method="post">
            <div class="form-group">
                <label for="login-email">E-posta Adresi</label>
                <input type="email" id="login-email" name="email" placeholder="E-posta adresinizi girin" required>
            </div>
            <div class="form-group">
                <label for="login-password">Şifre</label>
                <input type="password" id="login-password" name="password" placeholder="Şifrenizi girin" required>
            </div>
            <button type="submit">Giriş Yap</button>
        </form>
        <p class="account-link">Hesabınız yok mu? <a href="#" id="open-register">Üye Ol</a></p>
    </div>
</div>
<div id="register-modal" class="modal">
    <div class="modal-content register-content">
        <span class="close" id="close-register">&times;</span>
        <h2>Üye Ol</h2>
        <div class="error-messages" id="register-error-container" style="display:none;">
            <p class="error" id="register-error-message"></p>
        </div>
        <form id="register-form" method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="register-ad">Ad</label>
                    <input type="text" id="register-ad" name="ad" placeholder="Adınızı girin" required>
                </div>
                <div class="form-group">
                    <label for="register-soyad">Soyad</label>
                    <input type="text" id="register-soyad" name="soyad" placeholder="Soyadınızı girin" required>
                </div>
                <div class="form-group full-width">
                    <label for="register-email">E-posta Adresi</label>
                    <input type="email" id="register-email" name="email" placeholder="E-posta adresinizi girin" required>
                </div>
                <div class="form-group full-width">
                    <label for="register-telefon">Telefon</label>
                    <input type="tel" id="register-telefon" name="telefon" placeholder="Telefon numaranızı girin" required>
                </div>
                <div class="form-group full-width">
                    <label for="register-password">Şifre</label>
                    <input type="password" id="register-password" name="password" placeholder="Şifrenizi girin" required>
                </div>
            </div>
            <button type="submit">Üye Ol</button>
            <p class="account-link">Zaten hesabınız var mı? <a href="#" id="open-login2">Giriş Yap</a></p>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Toast Container for Notifications -->
<div class="toast-container"></div>

<!-- jQuery (if not already included in your main file) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Authentication JS -->
<script src="auth.js"></script>

<style>
    /* Cart Button Styles */
    .cart-button {
        position: relative;
    }
    
    /* Top bar right section - prevent dropdown */
    .top-bar-right {
        position: relative;
    }
    
    .top-bar-right a {
        text-decoration: none;
    }
    
    /* Disable dropdowns in top bar */
    .top-bar-right .account-dropdown {
        display: none;
    }
    
    .cart-count {
        position: absolute;
        top: -8px;
        right: -8px;
        background-color: #e63946;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
    }
    
    .cart-link {
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    /* Cart Dropdown Styles */
    .cart-dropdown {
        position: absolute;
        top: calc(100% + 5px);
        right: 0;
        width: 320px;
        background: white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-radius: 8px;
        z-index: 1000;
        margin-top: 0;
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: all 0.3s ease;
        pointer-events: auto;
    }
    
    /* Cart button container */
    .cart-button {
        position: relative;
    }
    
    /* Fallback CSS hover */
    .cart-button:hover .cart-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .cart-dropdown-header,
    .cart-dropdown-items,
    .cart-dropdown-footer {
        position: relative;
        z-index: 1001;
    }
    
    .cart-dropdown::before {
        content: '';
        position: absolute;
        top: -10px;
        left: -10px;
        right: -10px;
        height: 20px;
        z-index: 1000;
    }
    
    .cart-dropdown-header {
        padding: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .cart-dropdown-header h5 {
        margin: 0;
        font-size: 16px;
    }
    
    .cart-dropdown-items {
        max-height: 300px;
        overflow-y: auto;
        padding: 0 15px;
    }
    
    .cart-dropdown-item {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid #f1f1f1;
    }
    
    .cart-dropdown-item-img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
        margin-right: 10px;
    }
    
    .cart-dropdown-item-details {
        flex: 1;
    }
    
    .cart-dropdown-item-name {
        font-weight: 500;
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .cart-dropdown-item-variant {
        font-size: 12px;
        color: #777;
    }
    
    .cart-dropdown-item-price {
        font-size: 14px;
        font-weight: 500;
        color: #e63946;
    }
    
    .cart-dropdown-footer {
        padding: 15px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .cart-total {
        font-weight: bold;
        font-size: 16px;
    }
    
    .loading {
        padding: 20px;
        text-align: center;
        color: #777;
    }
    
    .cart-empty-message {
        padding: 20px;
        text-align: center;
        color: #777;
    }
    
    .cart-dropdown-more {
        padding: 10px 0;
        text-align: center;
        color: #777;
        font-size: 12px;
        font-style: italic;
        border-top: 1px solid #f1f1f1;
    }
    
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 25px;
        border: 1px solid #ddd;
        border-radius: 5px;
        width: 400px;
        max-width: 90%;
        position: relative;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        max-height: 90vh;
        overflow-y: auto;
    }
    
    /* Giriş modalı için özel stil */
    #login-modal .modal-content {
        width: 350px;
        padding: 20px;
        max-height: 420px;
        overflow-y: auto;
        margin: 10% auto;
    }
    
    /* Login modalı için başlık */
    #login-modal h2 {
        font-size: 18px;
        margin-bottom: 15px;
        padding-bottom: 5px;
        text-align: center;
        border-bottom: 2px solid #e63946;
    }
    
    /* Login form için kompakt aralıklar */
    #login-modal .form-group {
        margin-bottom: 15px;
    }
    
    #login-modal .form-group label {
        display: block;
        margin-bottom: 4px;
        font-size: 13px;
        font-weight: 500;
    }
    
    #login-modal .form-group input {
        width: 100%;
        padding: 8px 12px;
        font-size: 14px;
        height: 36px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    /* Login formu için buton */
    #login-modal button[type="submit"] {
        padding: 10px;
        font-size: 15px;
        margin: 10px 0 12px;
        height: auto;
        width: 100%;
        background-color: #e63946;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    
    #login-modal button[type="submit"]:hover {
        background-color: #d62839;
    }
    
    /* Login formu için bağlantı stili */
    #login-modal p.account-link {
        text-align: center;
        margin-top: 0;
        font-size: 13px;
    }
    
    /* Üye ol modalı için özel stil - daha küçük boyut */
    #register-modal .modal-content {
        width: 440px;
        padding: 15px 20px;
        max-height: 480px;
        overflow-y: auto;
        margin: 7% auto;
    }
    
    /* Daha küçük ekranlarda dikey alanı verimli kullanmak için */
    @media (max-height: 700px) {
        #register-modal .modal-content {
            margin: 5% auto;
            max-height: 420px;
        }
    }

    .close {
        position: absolute;
        right: 12px;
        top: 6px;
        color: #aaa;
        font-size: 22px;
        font-weight: bold;
        cursor: pointer;
        line-height: 18px;
        width: 18px;
        height: 18px;
        text-align: center;
        z-index: 10;
    }

    .close:hover,
    .close:focus {
        color: #e63946;
        text-decoration: none;
    }

    .modal h2 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
        text-align: center;
        padding-bottom: 10px;
        border-bottom: 2px solid #e63946;
        font-size: 22px;
    }
    
    /* Register modalı için başlık */
    #register-modal h2 {
        font-size: 18px;
        margin-bottom: 12px;
        padding-bottom: 5px;
        text-align: center;
    }

    .error-messages {
        padding: 5px;
        margin-bottom: 6px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    /* Kompakt form grubu stili */
    .form-group.compact {
        margin-bottom: 12px;
    }
    
    .form-group.compact label {
        margin-bottom: 3px;
        font-size: 13px;
    }
    
    .form-group.compact input {
        padding: 8px 10px;
    }
    
    /* Register formu için grid yapısı */
    #register-modal .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    #register-modal .form-grid .full-width {
        grid-column: span 2;
    }
    
    /* Register formu için daha kompakt aralıklar */
    #register-modal .form-group {
        margin-bottom: 0;
    }
    
    #register-modal .form-group label {
        display: block;
        margin-bottom: 3px;
        font-size: 12px;
    }
    
    #register-modal .form-group input {
        width: 100%;
        padding: 7px 10px;
        font-size: 13px;
        height: 32px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .form-group input:focus {
        border-color: #e63946;
        outline: none;
        box-shadow: 0 0 3px rgba(230, 57, 70, 0.3);
    }

    .form-group input.error-input {
        border-color: #e63946;
        background-color: #fff8f8;
    }

    .modal button[type="submit"] {
        width: 100%;
        padding: 12px;
        background-color: #e63946;
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 10px;
        transition: background-color 0.2s;
    }
    
    /* Register formu için daha küçük buton */
    #register-modal button[type="submit"] {
        padding: 8px;
        font-size: 14px;
        margin: 5px 0;
        height: auto;
        width: 100%;
        background-color: #e63946;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .modal button[type="submit"]:hover {
        background-color: #d62839;
    }

    .modal p {
        text-align: center;
        margin-top: 12px;
        color: #666;
        font-size: 14px;
    }
    
    /* Form içi bağlantı stili */
    #register-modal p.account-link {
        text-align: center;
        margin-top: 8px;
        font-size: 12px;
    }
    
    p.account-link a {
        color: #e63946;
        text-decoration: none;
        font-weight: 500;
    }
    
    p.account-link a:hover {
        text-decoration: underline;
    }
    
    #register-modal p {
        margin-top: 10px;
        font-size: 14px;
    }

    .modal p a {
        color: #e63946;
        text-decoration: none;
        font-weight: 500;
    }

    .modal p a:hover {
        text-decoration: underline;
    }
    
    /* Ad ve soyad kısımlarını tek satırda göstermek için */
    #register-modal .row-inputs {
        display: flex;
        gap: 5px;
    }
    
    .row-inputs .form-group {
        flex: 1;
    }
    
    /* Mobil görünüm için uyarlamalar */
    @media (max-width: 480px) {
        .row-inputs {
            flex-direction: column;
            gap: 0;
        }
        
        .modal-content {
            padding: 15px;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-group input {
            padding: 8px;
            font-size: 13px;
        }
        
        .modal h2 {
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .modal button[type="submit"] {
            padding: 10px;
            font-size: 15px;
        }
    }
</style>

<script>
$(document).ready(function() {
    // Load cart preview when hovering over cart button
    <?php if (isset($_SESSION['user']) && $cartCount > 0): ?>
    let cartPreviewLoaded = false;
    let cartPreviewLoading = false;
    let cartHoverTimeout;
    let cartLeaveTimeout;
    
    $('.cart-button, .cart-dropdown').hover(
        function() {
            // Mouse enter - hem buton hem dropdown için
            clearTimeout(cartLeaveTimeout);
            clearTimeout(cartHoverTimeout);
            
            if (!cartPreviewLoaded && !cartPreviewLoading) {
                cartHoverTimeout = setTimeout(function() {
                    loadCartPreview();
                }, 300); // 300ms gecikme ile yükle
            } else {
                // Zaten yüklenmişse hemen göster
                $('.cart-dropdown').css({
                    'opacity': '1',
                    'visibility': 'visible',
                    'transform': 'translateY(0)'
                });
            }
        },
        function() {
            // Mouse leave - gecikmeli kapatma
            clearTimeout(cartHoverTimeout);
            cartLeaveTimeout = setTimeout(function() {
                $('.cart-dropdown').css({
                    'opacity': '0',
                    'visibility': 'hidden',
                    'transform': 'translateY(10px)'
                });
            }, 200); // 200ms gecikme ile kapat
        }
    );
    
    function loadCartPreview() {
        if (cartPreviewLoading) return; // Zaten yükleniyor
        
        cartPreviewLoading = true;
        $('#cart-preview-items').html('<div class="loading">Yükleniyor...</div>');
        
        $.ajax({
            url: 'cart.php',
            type: 'POST',
            data: {
                action: 'getCart'
            },
            dataType: 'json',
            timeout: 5000, // 5 saniye timeout
            success: function(response) {
                console.log('Cart preview response:', response); // Debug
                
                if (response.success && response.cart && response.cart.length > 0) {
                    let html = '';
                    
                    // Display up to 3 items
                    const displayItems = response.cart.slice(0, 3);
                    
                    displayItems.forEach(function(item) {
                        html += `
                            <div class="cart-dropdown-item">
                                <img src="${item.resimYolu || 'https://placehold.co/60x60/e63946/white?text=Resim+Yok'}" 
                                     alt="${item.urunAdi}" 
                                     class="cart-dropdown-item-img"
                                     onerror="this.src='https://placehold.co/60x60/e63946/white?text=Resim+Yok'">
                                <div class="cart-dropdown-item-details">
                                    <div class="cart-dropdown-item-name">${item.urunAdi}</div>
                                    <div class="cart-dropdown-item-variant">
                                        ${item.renkAdi || 'N/A'}, ${item.numara || 'N/A'} - ${item.miktar} Adet
                                    </div>
                                    <div class="cart-dropdown-item-price">
                                        ${(item.birimFiyat * item.miktar).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}).replace('.', ',')} TL
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    if (response.cart.length > 3) {
                        const moreCount = response.cart.length - 3;
                        html += `<div class="cart-dropdown-more">ve ${moreCount} ürün daha...</div>`;
                    }
                    
                    $('#cart-preview-items').html(html);
                    $('#cart-preview-total').text(`Toplam: ${response.total.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}).replace('.', ',')} TL`);
                    cartPreviewLoaded = true; // Cache olarak işaretle
                } else {
                    $('#cart-preview-items').html('<div class="cart-empty-message">Sepetiniz boş</div>');
                    $('#cart-preview-total').text('Toplam: 0,00 TL');
                    cartPreviewLoaded = true;
                }
            },
            error: function(xhr, status, error) {
                console.error("Sepet önizleme hatası:", error, xhr.responseText);
                $('#cart-preview-items').html('<div class="cart-empty-message">Sepet bilgileri alınamadı</div>');
            },
            complete: function() {
                cartPreviewLoading = false;
            }
        });
    }
    
    // Global function to refresh cart preview when cart is updated
    window.refreshCartPreview = function() {
        cartPreviewLoaded = false;
        cartPreviewLoading = false;
        loadCartPreview();
    };
    
    // Global function to update cart count
    window.updateCartCount = function(count) {
        const cartCountElement = document.querySelector('.cart-count');
        if (count > 0) {
            if (cartCountElement) {
                cartCountElement.textContent = count;
                cartCountElement.style.display = 'flex';
            } else {
                const cartLink = document.querySelector('.cart-link');
                if (cartLink) {
                    const countSpan = document.createElement('span');
                    countSpan.className = 'cart-count';
                    countSpan.textContent = count;
                    cartLink.appendChild(countSpan);
                }
            }
        } else {
            if (cartCountElement) {
                cartCountElement.style.display = 'none';
            }
        }
        
        // Also refresh cart preview cache
        if (typeof window.refreshCartPreview === 'function') {
            window.refreshCartPreview();
        }
    };
    
    <?php endif; ?>
});
</script> 