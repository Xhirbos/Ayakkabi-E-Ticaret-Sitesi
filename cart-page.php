<?php
// Remove error reporting settings
session_start();
require_once 'dbcon.php';
require_once 'cart.php'; // Include cart functions

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => "Sepetinizi görüntülemek için lütfen giriş yapın."
    ];
    
    // Set a flag to open login modal when redirected
    $_SESSION['open_login_modal'] = true;
    
    header('Location: index.php');
    exit;
}

$musteriID = $_SESSION['user']['id'];
$cartItems = [];
$cartTotal = 0;
$cartCount = 0;

try {
    // Check if customer exists
    $checkCustomerStmt = $pdo->prepare("SELECT musteriID, ad, soyad FROM musteri WHERE musteriID = ?");
    $checkCustomerStmt->execute([$musteriID]);
    $customer = $checkCustomerStmt->fetch();

    // First check if customer has any items in cart
    $cartCheckSql = "SELECT COUNT(*) FROM sepet WHERE musteriID = ?";
    $checkStmt = $pdo->prepare($cartCheckSql);
    $checkStmt->execute([$musteriID]);
    $itemCount = (int)$checkStmt->fetchColumn();
    
    // If cart has items
    if ($itemCount > 0) {
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
    }
    
    // Get cart count
    $cartCount = getCartCount($pdo, $musteriID);
    
} catch (PDOException $e) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => "Sepet bilgileri alınırken bir hata oluştu. Lütfen daha sonra tekrar deneyiniz."
    ];
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sepetim - Adım Adım</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-container {
            padding: 30px 0;
        }
        .cart-empty {
            text-align: center;
            padding: 50px 0;
        }
        .cart-empty i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        .cart-empty p {
            color: #777;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        .cart-item {
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        .cart-item-details h5 {
            margin-bottom: 5px;
        }
        .cart-item-details .variant {
            color: #777;
            font-size: 0.9rem;
        }
        .cart-item-price {
            font-weight: bold;
            color: #e63946;
        }
        .cart-item-actions {
            display: flex;
            align-items: center;
        }
        .cart-item-actions .form-control {
            width: 70px;
            text-align: center;
            margin: 0 10px;
        }
        .btn-remove {
            color: #dc3545;
            background: transparent;
            border: none;
            cursor: pointer;
        }
        .cart-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .cart-summary h4 {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .cart-summary .row {
            margin-bottom: 10px;
        }
        .cart-summary .total {
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 20px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .btn-checkout {
            width: 100%;
            margin-top: 15px;
            color: #fff !important;
            font-weight: bold;
        }
        .quantity-control {
            display: flex;
            align-items: center;
        }
        .quantity-control button {
            background: #f1f1f1;
            border: 1px solid #ddd;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .quantity-control input {
            width: 50px;
            text-align: center;
            border: 1px solid #ddd;
            height: 30px;
            margin: 0 5px;
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
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <main class="container cart-container">
        <h1 class="mb-4">Alışveriş Sepetim</h1>
        
        <?php if (empty($cartItems)): ?>
        <div class="cart-empty">
            <i class="fas fa-shopping-cart"></i>
            <h3>Sepetiniz Boş</h3>
            <p>Sepetinizde henüz ürün bulunmamaktadır.</p>
            <a href="index.php" class="btn btn-primary">Alışverişe Başla</a>
        </div>
        <?php else: ?>
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Cart Items -->
                <div class="card mb-4">
                    <div class="card-body">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item" data-id="<?php echo htmlspecialchars($item['sepetID']); ?>">
                            <div class="row align-items-center">
                                <div class="col-md-2 col-3">
                                    <img src="<?php echo htmlspecialchars($item['resimYolu']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['urunAdi']); ?>" 
                                         class="cart-item-image"
                                         onerror="this.src='https://placehold.co/80x80/e63946/white?text=Resim+Yok'">
                                </div>
                                <div class="col-md-4 col-9 cart-item-details">
                                    <h5>
                                        <a href="product-detail.php?id=<?php echo htmlspecialchars($item['urunID']); ?>">
                                            <?php echo htmlspecialchars($item['urunAdi']); ?>
                                        </a>
                                    </h5>
                                    <div class="variant">
                                        <span>Renk: <?php echo htmlspecialchars($item['renkAdi'] ?? 'N/A'); ?></span> | 
                                        <span>Numara: <?php echo htmlspecialchars($item['numara'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-2 col-4 cart-item-price">
                                    <?php echo number_format($item['birimFiyat'], 2, ',', '.'); ?> TL
                                </div>
                                <div class="col-md-2 col-4">
                                    <div class="quantity-control">
                                        <button class="btn-decrease" data-id="<?php echo htmlspecialchars($item['sepetID']); ?>">-</button>
                                        <input type="number" min="1" value="<?php echo htmlspecialchars($item['miktar']); ?>" 
                                               class="quantity-input" data-id="<?php echo htmlspecialchars($item['sepetID']); ?>">
                                        <button class="btn-increase" data-id="<?php echo htmlspecialchars($item['sepetID']); ?>">+</button>
                                    </div>
                                </div>
                                <div class="col-md-2 col-4 text-end">
                                    <div class="cart-item-total">
                                        <?php echo number_format($item['birimFiyat'] * $item['miktar'], 2, ',', '.'); ?> TL
                                    </div>
                                    <button class="btn-remove" data-id="<?php echo htmlspecialchars($item['sepetID']); ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Cart Summary -->
                <div class="cart-summary">
                    <h4>Sipariş Özeti</h4>
                    <div class="row">
                        <div class="col-7">Toplam Ürün</div>
                        <div class="col-5 text-end"><?php echo $cartCount; ?> Adet</div>
                    </div>
                    <div class="row">
                        <div class="col-7">Ara Toplam</div>
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
                    <a href="checkout.php" class="btn btn-success btn-checkout text-white">Siparişi Tamamla</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JavaScript -->
    <script>
        $(document).ready(function() {
            // Catch and handle errors in the JavaScript code
            try {
                // Quantity increase
                $('.btn-increase').click(function() {
                    const id = $(this).data('id');
                    const inputField = $(`.quantity-input[data-id="${id}"]`);
                    const currentValue = parseInt(inputField.val());
                    // Instead of immediately updating the UI, wait for the server response
                    updateCartItem(id, currentValue + 1);
                });

                // Quantity decrease
                $('.btn-decrease').click(function() {
                    const id = $(this).data('id');
                    const inputField = $(`.quantity-input[data-id="${id}"]`);
                    const currentValue = parseInt(inputField.val());
                    if (currentValue > 1) {
                        updateCartItem(id, currentValue - 1);
                    }
                });

                // Quantity manual input
                $('.quantity-input').change(function() {
                    const id = $(this).data('id');
                    const value = parseInt($(this).val());
                    if (value < 1) {
                        $(this).val(1);
                        updateCartItem(id, 1);
                    } else {
                        updateCartItem(id, value);
                    }
                });

                // Remove from cart
                $('.btn-remove').click(function() {
                    const id = $(this).data('id');
                    removeCartItem(id);
                });

                // Update cart item
                function updateCartItem(id, quantity) {
                    $.ajax({
                        url: 'cart.php',
                        type: 'POST',
                        data: {
                            action: 'update',
                            sepetID: id,
                            miktar: quantity
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Clear cart preview cache in header before reload
                                if (typeof window.refreshCartPreview === 'function') {
                                    window.refreshCartPreview();
                                }
                                
                                // Reload the page to show updated cart
                                location.reload();
                            } else {
                                // Display error message in a more user-friendly way
                                showStockAlert(response.message || 'Ürün güncellenirken bir hata oluştu');
                                
                                // If the stock is limited, reflect the available stock in the input field
                                if (response.message && response.message.includes("Stokta sadece")) {
                                    // Extract the available stock number from the message
                                    const stockMatch = response.message.match(/Stokta sadece (\d+) adet/);
                                    if (stockMatch && stockMatch[1]) {
                                        const availableStock = parseInt(stockMatch[1]);
                                        const inputField = $(`.quantity-input[data-id="${id}"]`);
                                        inputField.val(availableStock);
                                        
                                        // Reload after a short delay to reflect the correct value in backend
                                        setTimeout(() => {
                                            location.reload();
                                        }, 2000);
                                    }
                                }
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            showStockAlert('İşlem sırasında bir hata oluştu');
                        }
                    });
                }
                
                // Function to show stock alerts
                function showStockAlert(message) {
                    // Check if an alert already exists and remove it
                    $('.stock-alert').remove();
                    
                    // Create a new alert
                    const alertDiv = $('<div class="alert alert-warning stock-alert" role="alert"></div>');
                    alertDiv.text(message);
                    
                    // Add close button
                    const closeButton = $('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
                    alertDiv.append(closeButton);
                    
                    // Insert at the top of the cart items container
                    $('.card-body').prepend(alertDiv);
                    
                    // Auto close after 5 seconds
                    setTimeout(() => {
                        alertDiv.alert('close');
                    }, 5000);
                }

                // Remove cart item
                function removeCartItem(id) {
                    if (confirm('Bu ürünü sepetten kaldırmak istediğinize emin misiniz?')) {
                        $.ajax({
                            url: 'cart.php',
                            type: 'POST',
                            data: {
                                action: 'remove',
                                sepetID: id
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    // Clear cart preview cache in header before reload
                                    if (typeof window.refreshCartPreview === 'function') {
                                        window.refreshCartPreview();
                                    }
                                    
                                    // Reload the page to show updated cart
                                    location.reload();
                                } else {
                                    alert(response.message || 'Ürün kaldırılırken bir hata oluştu');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', error);
                                alert('İşlem sırasında bir hata oluştu');
                            }
                        });
                    }
                }
            } catch (e) {
                console.error('JavaScript Error:', e);
            }
        });
    </script>
</body>
</html> 