<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'dbcon.php';

// Get category ID from URL
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Initialize variables
$category = null;
$products = [];
$error_message = '';

// If we have a valid category ID
if ($category_id > 0) {
    try {
        // Get category info
        $category_sql = "SELECT kategoriID, kategoriAdi FROM kategori WHERE kategoriID = ?";
        $category_stmt = $pdo->prepare($category_sql);
        $category_stmt->execute([$category_id]);
        $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
        
        // If category exists, get its products
        if ($category) {
            $products_sql = "
                SELECT 
                    u.urunID, 
                    u.urunAdi, 
                    u.temelFiyat, 
                    u.indirimliFiyat,
                    COALESCE(
                        (SELECT r.resimURL FROM urunresim r WHERE r.urunID = u.urunID AND r.anaResim = 1 LIMIT 1),
                        (SELECT r.resimURL FROM urunresim r WHERE r.urunID = u.urunID LIMIT 1),
                        'https://placehold.co/300x300/e63946/white?text=Resim+Yok'
                    ) AS resimURL
                FROM 
                    urun u
                WHERE 
                    u.kategoriID = ? AND u.aktif = 1
                ORDER BY 
                    u.olusturmaTarihi DESC
            ";
            
            $products_stmt = $pdo->prepare($products_sql);
            $products_stmt->execute([$category_id]);
            $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Set page title
            $pageTitle = $category['kategoriAdi'] . " - Ürünler";
        } else {
            $error_message = "Kategori bulunamadı.";
            $pageTitle = "Kategori Bulunamadı";
        }
    } catch (PDOException $e) {
        error_log("Category page error: " . $e->getMessage());
        $error_message = "Kategori bilgileri yüklenirken bir hata oluştu.";
        $pageTitle = "Hata";
    }
} else {
    $error_message = "Geçersiz kategori ID'si.";
    $pageTitle = "Geçersiz Kategori";
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        /* Cart Button Styles */
        .cart-button {
            position: relative;
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
            top: 100%;
            right: 0;
            width: 320px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 1000;
            margin-top: 10px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }
        
        .cart-button:hover .cart-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
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
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container my-4">
    <div class="row">
        <?php if ($error_message): ?>
            <div class="col-12">
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
                <a href="index.php" class="btn btn-primary">Ana Sayfaya Dön</a>
            </div>
        <?php elseif ($category): ?>
            <div class="col-12 mb-4">
                <h1><?php echo htmlspecialchars($category['kategoriAdi']); ?></h1>
                <p class="lead">
                    Bu kategoride <?php echo count($products); ?> ürün bulunmaktadır.
                </p>
            </div>
            
            <?php if (empty($products)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        Bu kategoride henüz ürün bulunmamaktadır.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="card h-100 product-card">
                            <a href="product-detail.php?id=<?php echo $product['urunID']; ?>">
                                <img src="<?php echo htmlspecialchars($product['resimURL']); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($product['urunAdi']); ?>">
                            </a>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">
                                    <a href="product-detail.php?id=<?php echo $product['urunID']; ?>" class="product-link">
                                        <?php echo htmlspecialchars($product['urunAdi']); ?>
                                    </a>
                                </h5>
                                <div class="price-container mt-auto">
                                    <?php if (!empty($product['indirimliFiyat']) && $product['indirimliFiyat'] < $product['temelFiyat']): ?>
                                        <span class="current-price"><?php echo number_format($product['indirimliFiyat'], 2, ',', '.'); ?> TL</span>
                                        <span class="original-price"><?php echo number_format($product['temelFiyat'], 2, ',', '.'); ?> TL</span>
                                    <?php else: ?>
                                        <span class="current-price"><?php echo number_format($product['temelFiyat'], 2, ',', '.'); ?> TL</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Authentication JS -->
<script src="auth.js"></script>
<script>
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