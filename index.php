<?php
ob_start(); // Start output buffering to prevent header issues
// Set proper character encoding
header('Content-Type: text/html; charset=utf-8');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'dbcon.php';

// All login/registration handling is now done via AJAX in login-handler.php
// Keep logout functionality
if (isset($_GET['logout'])) {
    // Unset user session
    unset($_SESSION['user']);
    
    // Set toast message
    $_SESSION['toast'] = [
        'type' => 'success',
        'message' => "Çıkış başarılı!"
    ];
    
    // Redirect to homepage
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adım Adım - Ayakkabı E-Ticaret</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* Product link styles */
        .product-info h3 a {
            color: #333;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .product-info h3 a:hover {
            color: #e63946;
        }
        
        .product-img a {
            display: block;
            overflow: hidden;
            border-radius: 8px;
        }
        
        .product-img a img {
            transition: transform 0.3s ease;
        }
        
        .product-img a:hover img {
            transform: scale(1.05);
        }

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

        /* Toast styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 10px;
            overflow: hidden;
            max-width: 350px;
            transform: translateX(100%);
            opacity: 0;
            transition: transform 0.3s, opacity 0.3s;
        }
        
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .toast.success .toast-icon {
            background-color: #4CAF50;
        }
        
        .toast.error .toast-icon {
            background-color: #F44336;
        }
        
        .toast.warning .toast-icon {
            background-color: #FF9800;
        }
        
        .toast-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            color: white;
            font-weight: bold;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .toast-content {
            padding: 15px;
            flex-grow: 1;
            font-size: 14px;
            color: #333;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: #999;
            font-size: 20px;
            cursor: pointer;
            padding: 10px;
        }
        
        .toast-close:hover {
            color: #333;
        }

        /* Make category buttons text visible */
        .category-info .btn {
            background-color: #e63946;
            color: white; /* Ensure text is white and visible */
            font-weight: 500;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .category-info .btn:hover {
            background-color: #d62839;
        }

        /* Carousel styles */
        .carousel {
            margin-bottom: 2rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .carousel-caption {
            background: rgba(0, 0, 0, 0.6);
            border-radius: 8px;
            padding: 1rem;
            bottom: 2rem;
            left: 2rem;
            right: 2rem;
        }
        
        .carousel-caption h5 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: white;
        }
        
        .carousel-caption p {
            font-size: 1rem;
            margin-bottom: 0;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .carousel-item a {
            display: block;
            text-decoration: none;
        }
        
        .carousel-item img {
            transition: transform 0.3s ease;
        }
        
        .carousel-item a:hover img {
            transform: scale(1.02);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <main class="container">
        <?php
        // Fetch active carousel images from database
        $carousel_stmt = $pdo->prepare("SELECT * FROM carousel WHERE aktif = 1 ORDER BY sira ASC, carouselID ASC");
        $carousel_stmt->execute();
        $carousel_images = $carousel_stmt->fetchAll();
        ?>
        
        <?php if (count($carousel_images) > 0): ?>
        <div id="carouselExample" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php foreach ($carousel_images as $index => $carousel): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <?php if (!empty($carousel['linkURL'])): ?>
                        <a href="<?php echo htmlspecialchars($carousel['linkURL']); ?>">
                    <?php endif; ?>
                    
                    <img src="<?php echo htmlspecialchars($carousel['resimURL']); ?>" 
                         class="d-block w-100" 
                         alt="<?php echo htmlspecialchars($carousel['baslik'] ?? 'Carousel Image'); ?>"
                         style="height: 400px; object-fit: cover;">
                    
                    <?php if (!empty($carousel['baslik']) || !empty($carousel['aciklama'])): ?>
                    <div class="carousel-caption d-none d-md-block">
                        <?php if (!empty($carousel['baslik'])): ?>
                            <h5><?php echo htmlspecialchars($carousel['baslik']); ?></h5>
                        <?php endif; ?>
                        <?php if (!empty($carousel['aciklama'])): ?>
                            <p><?php echo htmlspecialchars($carousel['aciklama']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($carousel['linkURL'])): ?>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($carousel_images) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExample" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselExample" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Featured Categories -->
        <section class="featured-categories">
            <h2 class="section-title">Kategoriler</h2>
            <div class="category-grid">
                <div class="category-card">
                    <div class="category-img">
                        <img src="https://placehold.co/300x200/007bff/white?text=Kadın" alt="Kadın Ayakkabıları">
                    </div>
                    <div class="category-info">
                        <h3>Kadın</h3>
                        <a href="category.php?id=4" class="btn">İncele</a>
                    </div>
                </div>
                <div class="category-card">
                    <div class="category-img">
                        <img src="https://placehold.co/300x200/28a745/white?text=Erkek" alt="Erkek Ayakkabıları">
                    </div>
                    <div class="category-info">
                        <h3>Erkek</h3>
                        <a href="category.php?id=3" class="btn">İncele</a>
                    </div>
                </div>
                <div class="category-card">
                    <div class="category-img">
                        <img src="https://placehold.co/300x200/dc3545/white?text=Çocuk" alt="Çocuk Ayakkabıları">
                    </div>
                    <div class="category-info">
                        <h3>Çocuk</h3>
                        <a href="category.php?id=6" class="btn">İncele</a>
                    </div>
                </div>
                <div class="category-card">
                    <div class="category-img">
                        <img src="https://placehold.co/300x200/ffc107/white?text=Spor" alt="Spor Ayakkabıları">
                    </div>
                    <div class="category-info">
                        <h3>Spor</h3>
                        <a href="category.php?id=5" class="btn">İncele</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Products -->
        <section class="featured-products">
            <h2 class="section-title">Öne Çıkan Ürünler</h2>
            <div class="product-grid">
                <?php
                // Fetch featured products from the database with random ordering
                $stmt = $pdo->prepare("
                    SELECT u.*, 
                           COALESCE(
                               (SELECT ir.resimURL FROM urunresim ir WHERE ir.urunID = u.urunID ORDER BY ir.sira LIMIT 1),
                               'https://placehold.co/300x200/e63946/white?text=Resim+Yok'
                           ) AS resimYolu
                    FROM urun u 
                    ORDER BY RAND() 
                    LIMIT 4
                ");
                $stmt->execute();
                $featuredProducts = $stmt->fetchAll();
                
                if (count($featuredProducts) > 0) {
                    foreach ($featuredProducts as $product) {
                        // Calculate discount percentage if there is a discount
                        $discountPercentage = 0;
                        $hasDiscount = false;
                        $displayPrice = isset($product['temelFiyat']) ? $product['temelFiyat'] : 0;
                        
                        if (isset($product['indirimliFiyat']) && $product['indirimliFiyat'] > 0 && $product['indirimliFiyat'] < $product['temelFiyat']) {
                            $discountPercentage = round(100 - (($product['indirimliFiyat'] / $product['temelFiyat']) * 100));
                            $hasDiscount = true;
                            $displayPrice = $product['indirimliFiyat'];
                        }
                        ?>
                        <div class="product-card">
                            <div class="product-img">
                                <a href="product-detail.php?id=<?php echo $product['urunID']; ?>">
                                    <img src="<?php echo htmlspecialchars($product['resimYolu']); ?>" alt="<?php echo htmlspecialchars($product['urunAdi']); ?>">
                                </a>
                                <?php if ($hasDiscount): ?>
                                <span class="discount-badge">%<?php echo $discountPercentage; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h3><a href="product-detail.php?id=<?php echo $product['urunID']; ?>"><?php echo htmlspecialchars($product['urunAdi']); ?></a></h3>
                                <div class="product-brand"><?php echo htmlspecialchars($product['marka'] ?? ''); ?></div>
                                <div class="product-price">
                                    <span class="current-price"><?php echo number_format((float)$displayPrice, 2, ',', '.'); ?> TL</span>
                                    <?php if ($hasDiscount): ?>
                                    <span class="old-price"><?php echo number_format((float)$product['temelFiyat'], 2, ',', '.'); ?> TL</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-actions">
                                    <button class="add-to-cart" data-id="<?php echo $product['urunID']; ?>">Sepete Ekle</button>
                                    <button class="wishlist-btn" data-id="<?php echo $product['urunID']; ?>"><i class="far fa-heart"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<p>Öne çıkan ürün bulunamadı.</p>';
                }
                ?>
            </div>
        </section>

        <!-- Banner -->
        <div class="banner" style="background-image: url('/api/placeholder/1200/200')">
            <div class="banner-content">
                <h2>Yazın En Trend Ayakkabıları</h2>
                <p>Yeni sezonda öne çıkan modelleri keşfedin</p>
                <a href="#" class="btn">Hemen İncele</a>
            </div>
        </div>

    </main>

    <?php include 'footer.php'; ?>
    
    <!-- Login and Registration Modals -->
    <?php if (!isset($_SESSION['user'])): ?>
    <div id="login-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" id="close-login">&times;</span>
            <h2>Giriş Yap</h2>
            <div class="error-messages" id="login-error-container" style="display:none;">
                <p class="error" id="login-error-message"></p>
            </div>
            <form id="login-form" method="post">
                <div class="form-group">
                    <label for="login-email">E-posta Adresi</label>
                    <input type="email" id="login-email" name="email" value="<?php echo isset($_SESSION['login_email']) ? htmlspecialchars($_SESSION['login_email']) : ''; ?>" placeholder="E-posta adresinizi girin" required>
                    <?php unset($_SESSION['login_email']); ?>
                </div>
                <div class="form-group">
                    <label for="login-password">Şifre</label>
                    <input type="password" id="login-password" name="password" placeholder="Şifrenizi girin" required>
                </div>
                <button type="submit">Giriş Yap</button>
            </form>
            <p>Hesabınız yok mu? <a href="#" id="open-register">Üye Ol</a></p>
        </div>
    </div>
    <div id="register-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" id="close-register">&times;</span>
            <h2>Üye Ol</h2>
            <div class="error-messages" id="register-error-container" style="display:none;">
                <p class="error" id="register-error-message"></p>
            </div>
            <form id="register-form" method="post" autocomplete="off">
                <div class="form-group">
                    <label for="register-ad">Ad</label>
                    <input type="text" id="register-ad" name="ad" value="<?php echo isset($_SESSION['register_data']['ad']) ? htmlspecialchars($_SESSION['register_data']['ad']) : ''; ?>" placeholder="Adınızı girin" required>
                </div>
                <div class="form-group">
                    <label for="register-soyad">Soyad</label>
                    <input type="text" id="register-soyad" name="soyad" value="<?php echo isset($_SESSION['register_data']['soyad']) ? htmlspecialchars($_SESSION['register_data']['soyad']) : ''; ?>" placeholder="Soyadınızı girin" required>
                </div>
                <div class="form-group">
                    <label for="register-email">E-posta Adresi</label>
                    <input type="email" id="register-email" name="email" value="<?php echo isset($_SESSION['register_data']['email']) ? htmlspecialchars($_SESSION['register_data']['email']) : ''; ?>" placeholder="E-posta adresinizi girin" required>
                </div>
                <div class="form-group">
                    <label for="register-telefon">Telefon</label>
                    <input type="tel" id="register-telefon" name="telefon" value="<?php echo isset($_SESSION['register_data']['telefon']) ? htmlspecialchars($_SESSION['register_data']['telefon']) : ''; ?>" placeholder="Telefon numaranızı girin" required>
                </div>
                <div class="form-group">
                    <label for="register-password">Şifre</label>
                    <input type="password" id="register-password" name="password" placeholder="Şifrenizi girin" required>
                </div>
                <button type="submit">Üye Ol</button>
            </form>
            <p>Zaten hesabınız var mı? <a href="#" id="open-login2">Giriş Yap</a></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Authentication JS -->
    <script src="auth.js"></script>
    <!-- Custom JavaScript -->
    <script>
    $(document).ready(function() {
        console.log("Document ready"); // Debug
        
        // Ensure the cart login trigger works
        $(document).on('click', '#cart-login-trigger', function(e) {
            console.log("Cart login trigger clicked (index)"); // Debug
            e.preventDefault();
            e.stopPropagation();
            $('#login-modal').css('display', 'flex');
            return false;
        });
        
        // Add to cart functionality for product cards on homepage
        $('.add-to-cart').click(function() {
            <?php if (isset($_SESSION['user'])): ?>
            const productId = $(this).data('id');
            
            // Redirect to product detail page for variant selection
            window.location.href = `product-detail.php?id=${productId}`;
            <?php else: ?>
            // Show login modal if user is not logged in
            showToast('Sepete eklemek için lütfen giriş yapın', 'error');
            $('#login-modal').css('display', 'flex');
            <?php endif; ?>
        });
        
        // Login/Register Modal handlers
        $('#account-btn').click(function() {
            <?php if (!isset($_SESSION['user'])): ?>
            $('#login-modal').css('display', 'flex');
            <?php endif; ?>
        });
        
        $('#close-login').click(function() {
            $('#login-modal').css('display', 'none');
        });
        
        $('#open-register').click(function() {
            $('#login-modal').css('display', 'none');
            $('#register-modal').css('display', 'flex');
        });
        
        $('#close-register').click(function() {
            $('#register-modal').css('display', 'none');
        });
        
        $('#open-login2').click(function() {
            $('#register-modal').css('display', 'none');
            $('#login-modal').css('display', 'flex');
        });
        
        // Close modals when clicking outside
        $(window).click(function(event) {
            if ($(event.target).hasClass('modal')) {
                $('.modal').css('display', 'none');
            }
        });
        
        // Check if login modal should be opened
        <?php if (isset($_SESSION['open_login_modal']) && $_SESSION['open_login_modal']): ?>
        $('#login-modal').css('display', 'flex');
        <?php unset($_SESSION['open_login_modal']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['toast'])): ?>
        // Show toast message if exists in session
        $(document).ready(function() {
            console.log("Toast message from session:", <?php echo json_encode($_SESSION['toast']); ?>);
            showToast('<?php echo addslashes($_SESSION['toast']['message']); ?>', '<?php echo $_SESSION['toast']['type']; ?>');
        });
        <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    });

    // Toast mesajı gösterme fonksiyonu
    function showToast(message, type = 'info') {
        // Önceki aynı tipteki toast'ları temizle
        clearExistingToasts(message, type);
        
        // Toast container kontrolü
        let toastContainer = $('.toast-container');
        if (toastContainer.length === 0) {
            toastContainer = $('<div class="toast-container"></div>');
            $('body').append(toastContainer);
        }
        
        // Toast ID oluştur
        const toastId = 'toast-' + new Date().getTime() + '-' + Math.floor(Math.random() * 1000);
        
        // Toast içeriği oluştur
        let iconHtml = '';
        switch (type) {
            case 'success':
                iconHtml = '<i class="fas fa-check-circle"></i>';
                break;
            case 'error':
                iconHtml = '<i class="fas fa-exclamation-circle"></i>';
                break;
            case 'warning':
                iconHtml = '<i class="fas fa-exclamation-triangle"></i>';
                break;
            default:
                iconHtml = '<i class="fas fa-info-circle"></i>';
        }
        
        // Toast elemanını oluştur
        const toast = $(`
            <div id="${toastId}" class="toast ${type}">
                <div class="toast-icon">${iconHtml}</div>
                <div class="toast-content">${message}</div>
                <button class="toast-close" onclick="closeToast('${toastId}')">&times;</button>
            </div>
        `);
        
        // Toast'u container'a ekle
        toastContainer.append(toast);
        
        // Toast'u göster
        setTimeout(() => {
            toast.addClass('show');
        }, 10);
        
        // 5 saniye sonra kapat
        setTimeout(() => {
            closeToast(toastId);
        }, 5000);
    }

    // Toast'u kapatma fonksiyonu
    function closeToast(toastId) {
        const toast = $(`#${toastId}`);
        toast.removeClass('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }

    // Benzer mesajlı toast'ları temizle
    function clearExistingToasts(message, type) {
        $('.toast').each(function() {
            const toastType = $(this).hasClass(type);
            const toastMessage = $(this).find('.toast-content').text();
            
            if (toastType && toastMessage === message) {
                const toastId = $(this).attr('id');
                closeToast(toastId);
            }
        });
    }

    // Bütün toast'ları temizle
    function clearAllToasts() {
        $('.toast').each(function() {
            const toastId = $(this).attr('id');
            closeToast(toastId);
        });
    }
    </script>
</body>
</html>
<?php ob_end_flush(); // End output buffering ?>