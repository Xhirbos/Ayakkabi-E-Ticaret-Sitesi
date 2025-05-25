<?php
// Enable all error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'dbcon.php';
require_once 'cart.php'; // Include cart functions

// Initialize CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Regenerate session ID for security if not done recently
if (!isset($_SESSION['last_session_regenerate']) || ($_SESSION['last_session_regenerate'] < time() - 1800)) {
    session_regenerate_id(true);
    $_SESSION['last_session_regenerate'] = time();
    error_log("Session ID regenerated for security");
}

// Session validation - make sure user session data is consistent
if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
    try {
        // Validate user exists in database
        $checkUserStmt = $pdo->prepare("SELECT musteriID, ad, soyad, eposta, aktif FROM musteri WHERE musteriID = ?");
        $checkUserStmt->execute([$_SESSION['user']['id']]);
        $userInfo = $checkUserStmt->fetch(PDO::FETCH_ASSOC);
        
        // If user not found or not active, clear session
        if (!$userInfo || $userInfo['aktif'] != 1) {
            error_log("Invalid or inactive user in session: " . $_SESSION['user']['id']);
            unset($_SESSION['user']);
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => "Oturum bilgilerinizde sorun var. Lütfen tekrar giriş yapın."
            ];
        } 
        // Update session data if database info has changed
        else if (
            $_SESSION['user']['email'] !== $userInfo['eposta'] || 
            $_SESSION['user']['isim'] !== $userInfo['ad'] || 
            $_SESSION['user']['soyad'] !== $userInfo['soyad']
        ) {
            error_log("Updating session user data from database");
            $_SESSION['user'] = [
                'id' => (int)$userInfo['musteriID'],
                'isim' => $userInfo['ad'],
                'soyad' => $userInfo['soyad'],
                'email' => $userInfo['eposta']
            ];
        }
    } catch (PDOException $e) {
        error_log("Error validating user session: " . $e->getMessage());
    }
}

// Debug output
error_log("Checkout page loaded. Session user ID: " . (isset($_SESSION['user']) ? $_SESSION['user']['id'] : 'Not set'));
error_log("POST data: " . print_r($_POST, true));
error_log("SESSION data: " . print_r($_SESSION, true));
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("HTTP_X_REQUESTED_WITH: " . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : 'not set'));

// Intercept any API-like Ajax requests here before the rest of the script runs
$isApiRequest = (
    $_SERVER['REQUEST_METHOD'] === 'POST' && 
    !(isset($_POST['complete_order']) || isset($_POST['test_checkout'])) && 
    empty($_FILES) &&
    (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
    )
);

// For debugging purposes
error_log("Is API request: " . ($isApiRequest ? 'Yes' : 'No'));

// If this is an API request (AJAX), handle it differently
if ($isApiRequest) {
    error_log("Handling as API request");
    // Return a JSON response
    header('Content-Type: application/json');
    
    // Check if user is logged in for API requests
    if (!isset($_SESSION['user'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Lütfen giriş yapın.',
            'redirect' => 'index.php',
            'cartCount' => 0
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Check for valid action parameter
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Handle specific API actions
    if (!empty($action)) {
        switch ($action) {
            case 'validate_form':
                // Handle form validation request
                echo json_encode([
                    'success' => true,
                    'message' => 'Form validation successful',
                    'cartCount' => getCartCount($pdo, (isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null))
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                // Unknown action
                error_log("API Request: Unknown action specified: {$action}. POST Data: " . print_r($_POST, true) . " Session User ID: " . (isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 'NOT SET'));
                echo json_encode([
                    'success' => false,
                    'message' => 'Bilinmeyen işlem: ' . $action,
                    'cartCount' => getCartCount($pdo, (isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null))
                ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        // No action specified
        error_log("API Request: No action specified (fallback). POST Data: " . print_r($_POST, true) . " Session User ID: " . (isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 'NOT SET') . " Headers: " . print_r(function_exists('getallheaders') ? getallheaders() : [], true));
        echo json_encode([
            'success' => false,
            'message' => 'Bu endpoint için tanımlı bir API işlemi yok.',
            'cartCount' => getCartCount($pdo, (isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null))
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// From here on, treat as a normal web page request
// Check for XMLHttpRequest - if this is an AJAX request, handle differently
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

if ($isAjax) {
    error_log("AJAX request detected - returning proper response");
    // If AJAX request and no user session, return error
    if (!isset($_SESSION['user'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Oturum süreniz dolmuş. Lütfen tekrar giriş yapın.',
            'cartCount' => 0
        ]);
        exit;
    }
}

// Check login status for normal requests
if (!isset($_SESSION['user'])) {
    error_log("User not logged in - redirecting to login");
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => "Sipariş vermek için lütfen giriş yapın."
    ];
    
    // Set a flag to open login modal when redirected
    $_SESSION['open_login_modal'] = true;
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Giriş yapmanız gerekiyor.',
            'redirect' => 'index.php'
        ]);
    } else {
        header('Location: index.php');
    }
    exit;
}

$musteriID = $_SESSION['user']['id'];
$cartItems = [];
$cartTotal = 0;
$cartCount = 0;
$addresses = [];
$errorMessage = '';
$successMessage = '';

// Get customer addresses
try {
    $addressStmt = $pdo->prepare("SELECT * FROM musteriadres WHERE musteriID = ? ORDER BY varsayilan DESC, olusturmaTarihi DESC");
    $addressStmt->execute([$musteriID]);
    $addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Adres bilgileri alınırken hata: " . $e->getMessage());
    $errorMessage = "Adres bilgileri alınırken bir hata oluştu.";
}

// Process order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {
    error_log("Order submission started for customer ID: $musteriID");
    
    // Debug POST data
    error_log("POST data: " . print_r($_POST, true));
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed");
        $errorMessage = "Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.";
    } else {
        $adresID = isset($_POST['adresID']) ? (int)$_POST['adresID'] : 0;
        $odemeYontemi = isset($_POST['odemeYontemi']) ? $_POST['odemeYontemi'] : '';
        
        error_log("Order data: Address ID = $adresID, Payment Method = $odemeYontemi");
        
        if ($adresID <= 0) {
            $errorMessage = "Lütfen bir teslimat adresi seçin.";
            error_log("Error: No delivery address selected");
        } elseif (empty($odemeYontemi)) {
            $errorMessage = "Lütfen bir ödeme yöntemi seçin.";
            error_log("Error: No payment method selected");
        } else {
            // First check if cart has items
            $checkCartSql = "SELECT COUNT(*) as count FROM sepet WHERE musteriID = ?";
            $checkCartStmt = $pdo->prepare($checkCartSql);
            $checkCartStmt->execute([$musteriID]);
            $cartItemCount = (int)$checkCartStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            error_log("Cart item count: $cartItemCount");
            
            if ($cartItemCount === 0) {
                $errorMessage = "Sepetinizde ürün bulunmamaktadır.";
                error_log("Error: No items in cart");
            } else {
                // Verify the address belongs to the customer
                $verifyAddressStmt = $pdo->prepare("SELECT adresID FROM musteriadres WHERE adresID = ? AND musteriID = ?");
                $verifyAddressStmt->execute([$adresID, $musteriID]);
                
                if ($verifyAddressStmt->rowCount() === 0) {
                    $errorMessage = "Geçersiz teslimat adresi.";
                    error_log("Error: Invalid delivery address");
                } else {
                    // Start transaction
                    $pdo->beginTransaction();
                    error_log("Transaction started");
                    
                    try {
                        // Prepare the data for cart.php
                        $_POST['action'] = 'confirmCart';
                        $_POST['odemeYontemi'] = $odemeYontemi;
                        
                        // Include cart.php to handle the order
                        require_once 'cart.php';
                        
                        // If we get here, the order was successful
                        $pdo->commit();
                        
                        // Clear the cart
                        $clearCartStmt = $pdo->prepare("DELETE FROM sepet WHERE musteriID = ?");
                        $clearCartStmt->execute([$musteriID]);
                        
                        // Set success message
                        $_SESSION['toast'] = [
                            'type' => 'success',
                            'message' => 'Siparişiniz başarıyla oluşturuldu.'
                        ];
                        
                        // Redirect to order confirmation page
                        header('Location: order-confirmation.php');
                        exit;
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        error_log("Error processing order: " . $e->getMessage());
                        $errorMessage = "Sipariş işlenirken bir hata oluştu. Lütfen tekrar deneyin.";
                    }
                }
            }
        }
    }
}

// Get cart items
try {
    // Get cart items with product details
    $cartSql = "
        SELECT s.sepetID, s.urunID, s.varyantID, s.miktar, s.birimFiyat, 
               u.urunAdi, 
               COALESCE(
                  (SELECT ir.resimURL FROM urunresim ir WHERE ir.urunID = s.urunID ORDER BY ir.sira LIMIT 1),
                  'https://placehold.co/80x80/e63946/white?text=Resim+Yok'
               ) AS resimYolu,
               r.renkAdi, b.numara
        FROM sepet s
        JOIN urun u ON s.urunID = u.urunID
        LEFT JOIN urunvaryant uv ON s.varyantID = uv.varyantID
        LEFT JOIN renk r ON uv.renkID = r.renkID
        LEFT JOIN beden b ON uv.bedenID = b.bedenID
        WHERE s.musteriID = ?
        ORDER BY s.eklenmeTarihi DESC
    ";
    
    $stmt = $pdo->prepare($cartSql);
    $stmt->execute([$musteriID]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate cart total
    $stmt = $pdo->prepare("SELECT SUM(miktar * birimFiyat) as total FROM sepet WHERE musteriID = ?");
    $stmt->execute([$musteriID]);
    $cartTotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // Get cart count
    $cartCount = 0; // Default cart count
    if (isset($musteriID) && $musteriID > 0) { // Ensure $musteriID is valid before calling
        error_log("[Checkout - Line ~434] Calling getCartCount for musteriID: $musteriID");
        $cartCount = getCartCount($pdo, $musteriID);
    } else {
        error_log("[Checkout - Line ~434] Skipped getCartCount. musteriID was not valid or not set. Value: '" . print_r($musteriID, true) . "'. Session user: " . print_r($_SESSION['user'] ?? 'Not Set', true));
    }
    
    // If cart is empty, redirect to cart page
    if ($cartCount === 0) {
        $_SESSION['toast'] = [
            'type' => 'warning',
            'message' => "Sepetiniz boş. Sipariş verebilmek için sepetinize ürün ekleyin."
        ];
        
        header('Location: cart-page.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Sepet bilgileri alınırken hata: " . $e->getMessage());
    $errorMessage = "Sepet bilgileri alınırken bir hata oluştu.";
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme - Adım Adım</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <style>
        .checkout-container {
            padding: 30px 0;
        }
        .checkout-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .checkout-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .checkout-step.active .step-number {
            background-color: #e63946;
            color: white;
        }
        .checkout-step .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #f8f9fa;
            text-align: center;
            line-height: 30px;
            margin-bottom: 5px;
        }
        .step-title {
            font-size: 0.9rem;
            color: #555;
        }
        .checkout-step.active .step-title {
            color: #e63946;
            font-weight: bold;
        }
        .checkout-step:not(:last-child):after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background-color: #eee;
            z-index: -1;
        }
        .checkout-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            height: 100%;
        }
        .checkout-summary h4 {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .checkout-summary .row {
            margin-bottom: 10px;
        }
        .checkout-summary .total {
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 20px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .checkout-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        .checkout-item {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .checkout-item:last-child {
            border-bottom: none;
        }
        .checkout-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        .checkout-item-details {
            flex: 1;
        }
        .checkout-item-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .checkout-item-variant {
            color: #777;
            font-size: 0.8rem;
        }
        .checkout-item-price {
            text-align: right;
            font-weight: 500;
        }
        .checkout-item-quantity {
            color: #777;
            font-size: 0.9rem;
        }
        .payment-method {
            margin-bottom: 30px;
        }
        .payment-method .form-check {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .payment-method .form-check:hover {
            background-color: #f9f9f9;
        }
        .payment-method .form-check-input:checked ~ .form-check-label {
            font-weight: bold;
        }
        .payment-method .form-check.checked {
            border-color: #e63946;
            background-color: #fff9f9;
        }
        .address-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .address-card:hover {
            background-color: #f9f9f9;
        }
        .address-card.selected {
            border-color: #e63946;
            background-color: #fff9f9;
        }
        .address-card .address-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .address-card .badge {
            background-color: #e63946;
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
        
        .btn-checkout {
            background-color: #e63946;
            border-color: #e63946;
            width: 100%;
            padding: 12px;
            font-weight: 600;
            margin-top: 20px;
        }
        
        .btn-checkout:hover, .btn-checkout:focus {
            background-color: #d62839;
            border-color: #d62839;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <main class="container checkout-container">
        <h1 class="mb-4">Sipariş Onayı</h1>
        
        <!-- Checkout Steps -->
        <div class="checkout-steps">
            <div class="checkout-step">
                <div class="step-number">1</div>
                <div class="step-title">Sepet</div>
            </div>
            <div class="checkout-step active">
                <div class="step-number">2</div>
                <div class="step-title">Ödeme</div>
            </div>
            <div class="checkout-step">
                <div class="step-number">3</div>
                <div class="step-title">Sipariş Tamamlandı</div>
            </div>
        </div>
        
        <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($errorMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($successMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <form method="post" action="checkout.php" id="checkoutForm" accept-charset="UTF-8">
            <input type="hidden" name="complete_order" value="1">
            <!-- Add CSRF token for security -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="checkout_timestamp" value="<?php echo time(); ?>">
            <div class="row">
                <!-- Left Side: Checkout Details -->
                <div class="col-lg-8">
                    <!-- Delivery Address -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Teslimat Adresi</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($addresses)): ?>
                                <div class="alert alert-warning">
                                    <p>Kayıtlı adresiniz bulunmamaktadır. Lütfen önce adres ekleyin.</p>
                                    <a href="account-settings.php#address-section" class="btn btn-primary btn-sm mt-2">Adres Ekle</a>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($addresses as $address): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="address-card" data-address-id="<?php echo $address['adresID']; ?>">
                                            <div class="address-title">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($address['baslik']); ?></h6>
                                                <?php if ($address['varsayilan']): ?>
                                                <span class="badge bg-primary">Varsayılan</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-2 small">
                                                <?php echo nl2br(htmlspecialchars($address['adres'])); ?><br>
                                                <?php echo htmlspecialchars($address['ilce']); ?> / <?php echo htmlspecialchars($address['il']); ?><br>
                                                <?php if (!empty($address['postaKodu'])): ?>
                                                    Posta Kodu: <?php echo htmlspecialchars($address['postaKodu']); ?><br>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($address['ulke'] ?? 'Türkiye'); ?>
                                            </p>
                                            <div class="form-check">
                                                <input class="form-check-input address-radio" type="radio" name="adresID" 
                                                       id="address<?php echo $address['adresID']; ?>" 
                                                       value="<?php echo $address['adresID']; ?>" 
                                                       <?php echo $address['varsayilan'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="address<?php echo $address['adresID']; ?>">
                                                    Bu adresi kullan
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-3">
                                    <a href="account-settings.php#address-section" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i> Yeni Adres Ekle
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Ödeme Yöntemi</h5>
                        </div>
                        <div class="card-body">
                            <div class="payment-method">
                                <div class="form-check mb-3 checked">
                                    <input class="form-check-input payment-radio" type="radio" name="odemeYontemi" id="paymentKrediKarti" value="KrediKarti" checked>
                                    <label class="form-check-label" for="paymentKrediKarti">
                                        <i class="fas fa-credit-card me-2"></i> Kredi Kartı
                                    </label>
                                    <div class="payment-details mt-2 small text-muted">
                                        Güvenli ödeme işleminizi kredi kartı ile gerçekleştirebilirsiniz.
                                    </div>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input payment-radio" type="radio" name="odemeYontemi" id="paymentHavale" value="Havale">
                                    <label class="form-check-label" for="paymentHavale">
                                        <i class="fas fa-university me-2"></i> Havale / EFT
                                    </label>
                                    <div class="payment-details mt-2 small text-muted">
                                        Siparişinizi banka havalesi veya EFT ile ödeyebilirsiniz.
                                    </div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input payment-radio" type="radio" name="odemeYontemi" id="paymentKapida" value="KapidaOdeme">
                                    <label class="form-check-label" for="paymentKapida">
                                        <i class="fas fa-hand-holding-usd me-2"></i> Kapıda Ödeme
                                    </label>
                                    <div class="payment-details mt-2 small text-muted">
                                        Siparişinizi teslim alırken ödeme yapabilirsiniz.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side: Order Summary -->
                <div class="col-lg-4">
                    <div class="checkout-summary">
                        <h4><i class="fas fa-shopping-cart me-2"></i>Sipariş Özeti</h4>
                        
                        <!-- Cart Items -->
                        <div class="checkout-items">
                            <?php foreach ($cartItems as $item): ?>
                            <div class="checkout-item">
                                <img src="<?php echo htmlspecialchars($item['resimYolu']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['urunAdi']); ?>" 
                                     class="checkout-item-image"
                                     onerror="this.src='https://placehold.co/60x60/e63946/white?text=Resim+Yok'">
                                <div class="checkout-item-details">
                                    <div class="checkout-item-name"><?php echo htmlspecialchars($item['urunAdi']); ?></div>
                                    <div class="checkout-item-variant">
                                        <?php echo htmlspecialchars($item['renkAdi'] ?? 'N/A'); ?>, 
                                        <?php echo htmlspecialchars($item['numara'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="checkout-item-quantity"><?php echo htmlspecialchars($item['miktar']); ?> Adet</div>
                                </div>
                                <div class="checkout-item-price">
                                    <?php echo number_format($item['birimFiyat'] * $item['miktar'], 2, ',', '.'); ?> TL
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Order Total -->
                        <div class="row">
                            <div class="col-7">Ürünler Toplamı</div>
                            <div class="col-5 text-end"><?php echo number_format($cartTotal, 2, ',', '.'); ?> TL</div>
                        </div>
                        <div class="row">
                            <div class="col-7">Kargo</div>
                            <div class="col-5 text-end">Ücretsiz</div>
                        </div>
                        <div class="row total">
                            <div class="col-7">Toplam</div>
                            <div class="col-5 text-end"><?php echo number_format($cartTotal, 2, ',', '.'); ?> TL</div>
                        </div>
                        
                        <?php if (!empty($addresses)): ?>
                        <button type="submit" name="complete_order" class="btn btn-primary btn-checkout">
                            <i class="fas fa-check-circle me-2"></i>Siparişi Tamamla
                        </button>
                        <?php else: ?>
                        <a href="account-settings.php#address-section" class="btn btn-primary btn-checkout">
                            <i class="fas fa-map-marker-alt me-2"></i>Adres Ekle
                        </a>
                        <?php endif; ?>
                        
                        <div class="mt-3 text-center">
                            <a href="cart-page.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i> Sepete Dön
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Disable any automatic AJAX form handling first
            $(document).off('submit', '#checkoutForm');
            
            // Address card selection
            $('.address-card').click(function() {
                const addressId = $(this).data('addressId');
                $('.address-card').removeClass('selected');
                $(this).addClass('selected');
                $(`#address${addressId}`).prop('checked', true);
            });
            
            // Initialize selected address card
            const checkedAddressId = $('input[name="adresID"]:checked').val();
            if(checkedAddressId) {
                $(`.address-card[data-address-id="${checkedAddressId}"]`).addClass('selected');
            }
            
            // Payment method selection
            $('.payment-radio').change(function() {
                $('.form-check').removeClass('checked');
                $(this).closest('.form-check').addClass('checked');
            });
            
            // Form validation before submit
            $('#checkoutForm').on('submit', function(e) {
                let isValid = true;
                // Check if address is selected
                if (!$('input[name="adresID"]:checked').length) {
                    alert('Lütfen bir teslimat adresi seçin.');
                    isValid = false;
                }
                // Check if payment method is selected
                if (!$('input[name="odemeYontemi"]:checked').length) {
                    alert('Lütfen bir ödeme yöntemi seçin.');
                    isValid = false;
                }
                if (!isValid) {
                    e.preventDefault(); // Sadece validasyon başarısızsa engelle
                }
                // Validasyon başarılıysa form klasik POST ile gönderilecek
            });
        });
    </script>
</body>
</html> 