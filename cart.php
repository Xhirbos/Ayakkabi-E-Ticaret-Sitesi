<?php
// Only start session if one is not already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'dbcon.php';

// Function to check stock availability for a variant
function checkStockAvailability($pdo, $varyantID, $requestedQuantity = 1) {
    try {
        $stmt = $pdo->prepare("SELECT stokMiktari FROM urunvaryant WHERE varyantID = ?");
        $stmt->execute([$varyantID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Varyant bulunamadı',
                'available' => 0
            ];
        }
        
        $stockAmount = (int)$result['stokMiktari'];
        
        return [
            'success' => true,
            'available' => $stockAmount,
            'sufficient' => $stockAmount >= $requestedQuantity
        ];
    } catch (PDOException $e) {
        error_log("Error checking stock: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Stok kontrolü sırasında bir hata oluştu',
            'available' => 0
        ];
    }
}

// Function to get current cart quantity for a product variant
function getCurrentCartQuantity($pdo, $musteriID, $varyantID) {
    try {
        $stmt = $pdo->prepare("SELECT miktar FROM sepet WHERE musteriID = ? AND varyantID = ?");
        $stmt->execute([$musteriID, $varyantID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['miktar'] : 0;
    } catch (PDOException $e) {
        error_log("Error getting current cart quantity: " . $e->getMessage());
        return 0;
    }
}

// API endpoint for cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'cartCount' => 0];
    
    // Check if the user is logged in
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        $response['message'] = 'Lütfen önce giriş yapınız';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Log session data for debugging
    error_log("SESSION user data: " . print_r($_SESSION['user'], true));
    
    $musteriID = (int)$_SESSION['user']['id'];
    
    // Additional validation
    if ($musteriID <= 0) {
        // Invalid user ID, clear session and ask to login again
        error_log("Invalid musteriID: " . $musteriID);
        unset($_SESSION['user']);
        $response['message'] = 'Oturum bilgilerinizde bir sorun var. Lütfen yeniden giriş yapın.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Validate the customer exists in the musteri table
    try {
        // Daha detaylı sorgu ile müşteri bilgilerini kontrol et
        $checkUser = $pdo->prepare("SELECT musteriID, ad, soyad, eposta, aktif FROM musteri WHERE musteriID = ?");
        $checkUser->execute([$musteriID]);
        $userInfo = $checkUser->fetch(PDO::FETCH_ASSOC);
        
        if (!$userInfo) {
            // Müşteri kaydı bulunamadı
            error_log("Customer not found in database: ID = {$musteriID}");
            unset($_SESSION['user']);
            $response['message'] = 'Geçerli bir kullanıcı hesabı bulunamadı. Lütfen yeniden giriş yapın.';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($userInfo['aktif'] != 1) {
            // Müşteri hesabı aktif değil
            error_log("Customer account not active: ID = {$musteriID}");
            unset($_SESSION['user']);
            $response['message'] = 'Hesabınız aktif değil. Lütfen müşteri hizmetleri ile iletişime geçin.';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Oturum bilgileri ile veritabanındaki bilgileri karşılaştır
        if ($_SESSION['user']['email'] !== $userInfo['eposta'] || 
            $_SESSION['user']['isim'] !== $userInfo['ad'] || 
            $_SESSION['user']['soyad'] !== $userInfo['soyad']) {
            
            // Oturum bilgileri ile veritabanındaki kullanıcı bilgileri uyuşmuyor,
            // oturumu güncelleyelim
            error_log("Session data mismatch with database. Updating session. ID = {$musteriID}");
            $_SESSION['user'] = [
                'id' => (int)$userInfo['musteriID'],
                'isim' => $userInfo['ad'],
                'soyad' => $userInfo['soyad'],
                'email' => $userInfo['eposta']
            ];
        }
    } catch (PDOException $e) {
        error_log("Error checking user: " . $e->getMessage());
        $response['message'] = 'Kullanıcı doğrulaması sırasında bir hata oluştu.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Check if this is a direct order confirmation request from checkout.php
    if (isset($_POST['complete_order'])) {
        $action = 'confirmCart';
        $_POST['odemeYontemi'] = $_POST['odemeYontemi'] ?? 'KapidaOdeme';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
    }
    
    switch ($action) {
        case 'add':
            $urunID = isset($_POST['urunID']) ? (int)$_POST['urunID'] : 0;
            $varyantID = isset($_POST['varyantID']) ? (int)$_POST['varyantID'] : 0;
            $miktar = isset($_POST['miktar']) ? (int)$_POST['miktar'] : 1;
            $fiyat = isset($_POST['fiyat']) ? (float)$_POST['fiyat'] : 0;
            
            if ($urunID <= 0 || $varyantID <= 0) {
                $response['message'] = 'Geçersiz ürün veya varyant';
                break;
            }
            
            if ($miktar <= 0) {
                $response['message'] = 'Geçersiz miktar';
                break;
            }
            
            // STOK KONTROLÜ - Mevcut sepet miktarını da hesaba kat
            $currentCartQty = getCurrentCartQuantity($pdo, $musteriID, $varyantID);
            $totalRequestedQty = $currentCartQty + $miktar;
            
            // Stok kontrolü yap
            $stockCheck = checkStockAvailability($pdo, $varyantID, $totalRequestedQty);
            
            if (!$stockCheck['success']) {
                $response['message'] = $stockCheck['message'];
                break;
            }
            
            if ($stockCheck['available'] <= 0) {
                $response['message'] = 'Bu ürün tükenmiştir.';
                break;
            }
            
            if (!$stockCheck['sufficient']) {
                // Eğer mevcut stok, toplam istenen miktardan azsa
                if ($stockCheck['available'] > $currentCartQty) {
                    // Eklenebilecek ekstra miktar
                    $addableQty = $stockCheck['available'] - $currentCartQty;
                    $response['message'] = "Stokta sadece {$stockCheck['available']} adet ürün bulunmaktadır. Sepetinize {$addableQty} adet daha ekleyebilirsiniz.";
                    break;
                } else {
                    $response['message'] = "Stokta sadece {$stockCheck['available']} adet ürün bulunmaktadır. Sepetinizde zaten bu miktarda veya daha fazla ürün var.";
                    break;
                }
            }
            
            // Fiyatı veritabanından alarak güncel fiyatı kullanmak daha güvenli olacaktır
            try {
                // Ürün fiyatını veritabanından al
                $priceStmt = $pdo->prepare("SELECT temelFiyat, indirimliFiyat FROM urun WHERE urunID = ?");
                $priceStmt->execute([$urunID]);
                $productPrice = $priceStmt->fetch();
                
                if (!$productPrice) {
                    $response['message'] = 'Ürün bulunamadı';
                    break;
                }
                
                // İndirimli fiyat varsa ve temel fiyattan küçükse onu kullan, yoksa temel fiyatı kullan
                if (isset($productPrice['indirimliFiyat']) && $productPrice['indirimliFiyat'] > 0 && 
                    $productPrice['indirimliFiyat'] < $productPrice['temelFiyat']) {
                    $fiyat = $productPrice['indirimliFiyat'];
                } else {
                    $fiyat = $productPrice['temelFiyat'];
                }
                
                error_log("Using price from database: " . $fiyat . " for product ID: " . $urunID);
                
                // Log detailed debug information
                error_log("Adding item to cart - UserID: " . $musteriID . ", ProductID: " . $urunID . ", VariantID: " . $varyantID . ", Quantity: " . $miktar . ", Price: " . $fiyat);
                
                // Check if this product variant is already in the cart
                $stmt = $pdo->prepare("SELECT sepetID, miktar FROM sepet WHERE musteriID = ? AND urunID = ? AND varyantID = ?");
                $stmt->execute([$musteriID, $urunID, $varyantID]);
                $existingItem = $stmt->fetch();
                
                if ($existingItem) {
                    // Update quantity if already in cart
                    $newMiktar = $existingItem['miktar'] + $miktar;
                    $stmt = $pdo->prepare("UPDATE sepet SET miktar = ?, guncellemeTarihi = NOW() WHERE sepetID = ?");
                    $result = $stmt->execute([$newMiktar, $existingItem['sepetID']]);
                    $response['message'] = 'Ürün sepette güncellendi';
                    error_log("Updated existing cart item, sepetID: " . $existingItem['sepetID'] . ", new quantity: " . $newMiktar . ", Update result: " . ($result ? 'Success' : 'Failed'));
                } else {
                    // Add new item to cart
                    $stmt = $pdo->prepare("INSERT INTO sepet (musteriID, urunID, varyantID, miktar, birimFiyat, eklenmeTarihi, guncellemeTarihi) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                    $result = $stmt->execute([$musteriID, $urunID, $varyantID, $miktar, $fiyat]);
                    $insertId = $pdo->lastInsertId();
                    $response['message'] = 'Ürün sepete eklendi';
                    error_log("Added new item to cart, sepetID: " . $insertId . ", Insert result: " . ($result ? 'Success' : 'Failed'));
                }
                
                // Get updated cart count
                $stmt = $pdo->prepare("SELECT SUM(miktar) as total FROM sepet WHERE musteriID = ?");
                $stmt->execute([$musteriID]);
                $response['cartCount'] = (int)($stmt->fetch()['total'] ?? 0);
                $response['success'] = true;
                error_log("Updated cart count: " . $response['cartCount']);
                
            } catch (PDOException $e) {
                error_log("Error adding to cart: " . $e->getMessage());
                
                // Check for foreign key constraint violation
                if ($e->getCode() == '23000' && strpos($e->getMessage(), 'FOREIGN KEY') !== false) {
                    // Try to identify which foreign key caused the issue
                    if (strpos($e->getMessage(), 'musteriID') !== false) {
                        $response['message'] = 'Müşteri bilgisi bulunamadı. Lütfen tekrar giriş yapınız.';
                    } else if (strpos($e->getMessage(), 'urunID') !== false) {
                        $response['message'] = 'Ürün bilgisi bulunamadı. Lütfen sayfayı yenileyiniz.';
                    } else if (strpos($e->getMessage(), 'varyantID') !== false) {
                        $response['message'] = 'Ürün varyantı bulunamadı. Lütfen başka bir renk/numara seçiniz.';
                    } else {
                        $response['message'] = 'Sepete eklerken bir veri ilişki hatası oluştu.';
                    }
                } else {
                    $response['message'] = 'Sepete eklerken bir hata oluştu.';
                }
                
                // For debugging - log the error details
                error_log("Cart error code: " . $e->getCode());
                error_log("Cart error message: " . $e->getMessage());
            }
            break;
            
        case 'update':
            $sepetID = isset($_POST['sepetID']) ? (int)$_POST['sepetID'] : 0;
            $miktar = isset($_POST['miktar']) ? (int)$_POST['miktar'] : 0;
            
            if ($sepetID <= 0) {
                $response['message'] = 'Geçersiz sepet öğesi';
                break;
            }
            
            try {
                if ($miktar <= 0) {
                    // Remove item if quantity is 0 or negative
                    $stmt = $pdo->prepare("DELETE FROM sepet WHERE sepetID = ? AND musteriID = ?");
                    $stmt->execute([$sepetID, $musteriID]);
                    $response['message'] = 'Ürün sepetten kaldırıldı';
                    $response['success'] = true;
                } else {
                    // Önce sepet öğesinin varyant ID'sini al
                    $stmt = $pdo->prepare("SELECT varyantID, miktar FROM sepet WHERE sepetID = ? AND musteriID = ?");
                    $stmt->execute([$sepetID, $musteriID]);
                    $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$cartItem) {
                        $response['message'] = 'Sepet öğesi bulunamadı';
                        break;
                    }
                    
                    $varyantID = $cartItem['varyantID'];
                    $currentQty = (int)$cartItem['miktar'];
                    
                    // Stok kontrolü yap
                    $stockCheck = checkStockAvailability($pdo, $varyantID, $miktar);
                    
                    if (!$stockCheck['success']) {
                        $response['message'] = $stockCheck['message'];
                        break;
                    }
                    
                    if ($stockCheck['available'] <= 0) {
                        $response['message'] = 'Bu ürün tükenmiştir.';
                        break;
                    }
                    
                    if (!$stockCheck['sufficient']) {
                        // İstenen miktar stoktan fazla, mevcut stok kadar ekle
                        $miktar = $stockCheck['available'];
                        $response['message'] = "Stokta sadece {$stockCheck['available']} adet ürün bulunduğu için miktar güncellendi.";
                    } else {
                        $response['message'] = 'Sepet güncellendi';
                    }
                    
                    // Update quantity
                    $stmt = $pdo->prepare("UPDATE sepet SET miktar = ?, guncellemeTarihi = NOW() WHERE sepetID = ? AND musteriID = ?");
                    $stmt->execute([$miktar, $sepetID, $musteriID]);
                    $response['success'] = true;
                }
                
                // Get updated cart count
                $stmt = $pdo->prepare("SELECT SUM(miktar) as total FROM sepet WHERE musteriID = ?");
                $stmt->execute([$musteriID]);
                $response['cartCount'] = (int)($stmt->fetch()['total'] ?? 0);
                
            } catch (PDOException $e) {
                error_log("Error updating cart: " . $e->getMessage());
                $response['message'] = 'Sepet güncellenirken bir hata oluştu';
            }
            break;
            
        case 'remove':
            $sepetID = isset($_POST['sepetID']) ? (int)$_POST['sepetID'] : 0;
            
            if ($sepetID <= 0) {
                $response['message'] = 'Geçersiz sepet öğesi';
                break;
            }
            
            try {
                $stmt = $pdo->prepare("DELETE FROM sepet WHERE sepetID = ? AND musteriID = ?");
                $stmt->execute([$sepetID, $musteriID]);
                
                // Get updated cart count
                $stmt = $pdo->prepare("SELECT SUM(miktar) as total FROM sepet WHERE musteriID = ?");
                $stmt->execute([$musteriID]);
                $response['cartCount'] = (int)($stmt->fetch()['total'] ?? 0);
                $response['success'] = true;
                $response['message'] = 'Ürün sepetten kaldırıldı';
                
            } catch (PDOException $e) {
                $response['message'] = 'Ürün kaldırılırken bir hata oluştu';
            }
            break;
            
        case 'getCart':
            try {
                // Get cart items with product details and totals in one query
                $stmt = $pdo->prepare("
                    SELECT s.sepetID, s.urunID, s.varyantID, s.miktar, s.birimFiyat, 
                           u.urunAdi, 
                           COALESCE(
                              (SELECT ir.resimURL FROM urunresim ir WHERE ir.urunID = s.urunID ORDER BY ir.sira LIMIT 1),
                              'https://placehold.co/80x80/e63946/white?text=Resim+Yok'
                           ) AS resimYolu,
                           r.renkAdi, b.numara,
                           (SELECT SUM(miktar * birimFiyat) FROM sepet WHERE musteriID = ?) as cartTotal,
                           (SELECT SUM(miktar) FROM sepet WHERE musteriID = ?) as cartCount
                    FROM sepet s
                    JOIN urun u ON s.urunID = u.urunID
                    LEFT JOIN urunvaryant uv ON s.varyantID = uv.varyantID
                    LEFT JOIN renk r ON uv.renkID = r.renkID
                    LEFT JOIN beden b ON uv.bedenID = b.bedenID
                    WHERE s.musteriID = ?
                    ORDER BY s.eklenmeTarihi DESC
                ");
                $stmt->execute([$musteriID, $musteriID, $musteriID]);
                $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Extract totals from first row (if exists)
                $total = 0;
                $count = 0;
                if (!empty($cart)) {
                    $total = (float)($cart[0]['cartTotal'] ?? 0);
                    $count = (int)($cart[0]['cartCount'] ?? 0);
                    
                    // Remove total fields from cart items
                    foreach ($cart as &$item) {
                        unset($item['cartTotal'], $item['cartCount']);
                    }
                }
                
                $response = [
                    'success' => true,
                    'cart' => $cart,
                    'total' => $total,
                    'cartCount' => $count,
                    'message' => ''
                ];
                
            } catch (PDOException $e) {
                $response['message'] = 'Sepet bilgileri alınırken bir hata oluştu';
                $response['error'] = $e->getMessage(); // Debug için hata mesajını ekleyelim
            }
            break;
            
        case 'getCount':
            try {
                // Only get cart count
                $stmt = $pdo->prepare("SELECT SUM(miktar) as count FROM sepet WHERE musteriID = ?");
                $stmt->execute([$musteriID]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                $response = [
                    'success' => true,
                    'cartCount' => (int)$count
                ];
                
            } catch (PDOException $e) {
                $response['message'] = 'Sepet bilgileri alınırken bir hata oluştu';
            }
            break;
            
        case 'confirmCart':
            try {
                // Önce sepetin boş olup olmadığını kontrol et
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sepet WHERE musteriID = ?");
                $stmt->execute([$musteriID]);
                $cartCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($cartCount == 0) {
                    $_SESSION['toast'] = [
                        'type' => 'error',
                        'message' => 'Sepetiniz boş'
                    ];
                    header('Location: cart-page.php');
                    exit;
                }

                // Ödeme yöntemini kontrol et
                $odemeYontemi = isset($_POST['odemeYontemi']) ? $_POST['odemeYontemi'] : '';
                if (!in_array($odemeYontemi, ['KrediKarti', 'Havale', 'KapidaOdeme'])) {
                    $_SESSION['toast'] = [
                        'type' => 'error',
                        'message' => 'Geçerli bir ödeme yöntemi seçiniz'
                    ];
                    header('Location: checkout.php');
                    exit;
                }
                
                // Sepetteki ürünlerin stok durumunu kontrol et
                $stmt = $pdo->prepare("
                    SELECT s.sepetID, s.varyantID, s.miktar, uv.stokMiktari, u.urunAdi
                    FROM sepet s
                    JOIN urunvaryant uv ON s.varyantID = uv.varyantID
                    JOIN urun u ON s.urunID = u.urunID
                    WHERE s.musteriID = ?
                ");
                $stmt->execute([$musteriID]);
                $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stockIssues = [];
                foreach ($cartItems as $item) {
                    if ($item['miktar'] > $item['stokMiktari']) {
                        $stockIssues[] = $item['urunAdi'] . " için yeterli stok yok. Mevcut stok: " . $item['stokMiktari'];
                    }
                }
                
                if (!empty($stockIssues)) {
                    $_SESSION['toast'] = [
                        'type' => 'error',
                        'message' => 'Bazı ürünlerde stok yetersizliği var: ' . implode(', ', $stockIssues)
                    ];
                    header('Location: checkout.php');
                    exit;
                }

                // Müşterinin varsayılan adresini kontrol et
                $stmt = $pdo->prepare("SELECT adresID FROM musteriadres WHERE musteriID = ? AND varsayilan = 1 LIMIT 1");
                $stmt->execute([$musteriID]);
                $defaultAddress = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$defaultAddress) {
                    $_SESSION['toast'] = [
                        'type' => 'error',
                        'message' => 'Lütfen önce bir teslimat adresi ekleyin'
                    ];
                    header('Location: account-settings.php#address-section');
                    exit;
                }

                $adresID = $defaultAddress['adresID'];

                // Sepet toplamını hesapla
                $stmt = $pdo->prepare("
                    SELECT 
                        SUM(miktar * birimFiyat) as toplamTutar,
                        SUM(miktar) as toplamUrun
                    FROM sepet 
                    WHERE musteriID = ?
                ");
                $stmt->execute([$musteriID]);
                $cartInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                $cartTotal = $cartInfo['toplamTutar'];
                $totalItems = $cartInfo['toplamUrun'];

                // Kargo ücreti hesapla (örnek: 100 TL'den fazla alışverişlerde ücretsiz)
                $kargoUcreti = ($cartTotal >= 100) ? 0 : 29.90;

                // İndirim hesapla (örnek: 5 üründen fazla alışverişlerde %5 indirim)
                $indirimOrani = ($totalItems >= 5) ? 0.05 : 0;
                $indirimTutari = $cartTotal * $indirimOrani;

                // Toplam ödeme tutarını hesapla
                $odemeTutari = $cartTotal - $indirimTutari + $kargoUcreti;

                // Sipariş numarası oluştur (YIL + AY + 6 haneli random sayı)
                $siparisNo = date('Ym') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

                // Transaction başlat
                $pdo->beginTransaction();

                try {
                    // Siparişi oluştur
                    $stmt = $pdo->prepare("
                        INSERT INTO siparis (
                            siparisNo, musteriID, adresID, toplamTutar, 
                            indirimTutari, odemeTutari, kargoUcreti,
                            odemeYontemi, durum
                        ) VALUES (
                            ?, ?, ?, ?, 
                            ?, ?, ?,
                            ?, 'Hazirlaniyor'
                        )
                    ");
                    $stmt->execute([
                        $siparisNo,
                        $musteriID,
                        $adresID,
                        $cartTotal,
                        $indirimTutari,
                        $odemeTutari,
                        $kargoUcreti,
                        $odemeYontemi
                    ]);
                    
                    $siparisID = $pdo->lastInsertId();

                    // Sipariş detaylarını ekle
                    $stmt = $pdo->prepare("
                        INSERT INTO siparisdetay (
                            siparisID, urunID, varyantID, birimFiyat, 
                            miktar, toplamTutar, durum
                        ) 
                        SELECT 
                            ?, s.urunID, s.varyantID, s.birimFiyat,
                            s.miktar, (s.miktar * s.birimFiyat), 'Beklemede'
                        FROM sepet s
                        WHERE s.musteriID = ?
                    ");
                    $stmt->execute([$siparisID, $musteriID]);

                    // Sepeti temizle
                    $stmt = $pdo->prepare("DELETE FROM sepet WHERE musteriID = ?");
                    $stmt->execute([$musteriID]);

                    // Transaction'ı onayla
                    $pdo->commit();

                    // Sipariş bilgilerini session'a kaydet
                    $_SESSION['last_order'] = [
                        'siparisNo' => $siparisNo,
                        'odemeTutari' => $odemeTutari,
                        'kargoUcreti' => $kargoUcreti,
                        'indirimTutari' => $indirimTutari
                    ];

                    // Başarılı mesajını session'a kaydet
                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'message' => 'Siparişiniz başarıyla oluşturuldu.'
                    ];

                    // Sipariş başarılı sayfasına yönlendir
                    header('Location: order-success.php?siparisNo=' . urlencode($siparisNo));
                    exit;

                } catch (Exception $e) {
                    // Hata durumunda transaction'ı geri al
                    $pdo->rollBack();
                    throw $e;
                }
                
            } catch (PDOException $e) {
                error_log("Error confirming cart: " . $e->getMessage());
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => 'Sipariş oluşturulurken bir hata oluştu'
                ];
                header('Location: checkout.php');
                exit;
            }
            break;
            
        default:
            $response['message'] = 'Geçersiz işlem';
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Get cart count (used in header)
function getCartCount($pdo, $musteriID) {
    try {
        $stmt = $pdo->prepare("SELECT SUM(miktar) as count FROM sepet WHERE musteriID = ?");
        $stmt->execute([$musteriID]);
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}
?> 