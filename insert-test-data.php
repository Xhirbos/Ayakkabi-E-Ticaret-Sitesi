<?php
require_once 'dbcon.php';

echo "<h1>Inserting Test Data</h1>";

// Start transaction
$pdo->beginTransaction();

try {
    // Check if test data already exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM urun");
    $productCount = $stmt->fetchColumn();
    
    if ($productCount > 0) {
        echo "<p>Products already exist in database. Skipping insertion to avoid duplicates.</p>";
    } else {
        // 1. Insert/check categories
        echo "<h2>Inserting Categories</h2>";
        $categories = [
            ['kategoriAdi' => 'Erkek'],
            ['kategoriAdi' => 'Kadın'],
            ['kategoriAdi' => 'Spor'],
            ['kategoriAdi' => 'Çocuk']
        ];
        
        $categoryIds = [];
        foreach ($categories as $category) {
            $stmt = $pdo->prepare("SELECT kategoriID FROM kategori WHERE kategoriAdi = ?");
            $stmt->execute([$category['kategoriAdi']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $categoryIds[$category['kategoriAdi']] = $result['kategoriID'];
                echo "<p>Category {$category['kategoriAdi']} already exists with ID: {$result['kategoriID']}</p>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO kategori (kategoriAdi) VALUES (?)");
                $stmt->execute([$category['kategoriAdi']]);
                $categoryIds[$category['kategoriAdi']] = $pdo->lastInsertId();
                echo "<p>Inserted category {$category['kategoriAdi']} with ID: {$categoryIds[$category['kategoriAdi']]}</p>";
            }
        }
        
        // 2. Insert/check colors
        echo "<h2>Inserting Colors</h2>";
        $colors = [
            ['renkAdi' => 'Siyah', 'renkKodu' => '#000000'],
            ['renkAdi' => 'Beyaz', 'renkKodu' => '#FFFFFF'],
            ['renkAdi' => 'Gri', 'renkKodu' => '#808080'],
            ['renkAdi' => 'Mavi', 'renkKodu' => '#0000FF']
        ];
        
        $colorIds = [];
        foreach ($colors as $color) {
            $stmt = $pdo->prepare("SELECT renkID FROM renk WHERE renkAdi = ?");
            $stmt->execute([$color['renkAdi']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $colorIds[$color['renkAdi']] = $result['renkID'];
                echo "<p>Color {$color['renkAdi']} already exists with ID: {$result['renkID']}</p>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO renk (renkAdi, renkKodu) VALUES (?, ?)");
                $stmt->execute([$color['renkAdi'], $color['renkKodu']]);
                $colorIds[$color['renkAdi']] = $pdo->lastInsertId();
                echo "<p>Inserted color {$color['renkAdi']} with ID: {$colorIds[$color['renkAdi']]}</p>";
            }
        }
        
        // 3. Insert/check sizes
        echo "<h2>Inserting Sizes</h2>";
        $sizes = [
            ['numara' => 36.0, 'ulkeSistemi' => 'TR'],
            ['numara' => 37.0, 'ulkeSistemi' => 'TR'],
            ['numara' => 38.0, 'ulkeSistemi' => 'TR'],
            ['numara' => 39.0, 'ulkeSistemi' => 'TR'],
            ['numara' => 40.0, 'ulkeSistemi' => 'TR'],
            ['numara' => 41.0, 'ulkeSistemi' => 'TR'],
            ['numara' => 42.0, 'ulkeSistemi' => 'TR'],
            ['numara' => 43.0, 'ulkeSistemi' => 'TR'],
            ['numara' => 44.0, 'ulkeSistemi' => 'TR']
        ];
        
        $sizeIds = [];
        foreach ($sizes as $size) {
            $stmt = $pdo->prepare("SELECT bedenID FROM beden WHERE numara = ?");
            $stmt->execute([$size['numara']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $sizeIds[$size['numara']] = $result['bedenID'];
                echo "<p>Size {$size['numara']} already exists with ID: {$result['bedenID']}</p>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO beden (numara, ulkeSistemi) VALUES (?, ?)");
                $stmt->execute([$size['numara'], $size['ulkeSistemi']]);
                $sizeIds[$size['numara']] = $pdo->lastInsertId();
                echo "<p>Inserted size {$size['numara']} with ID: {$sizeIds[$size['numara']]}</p>";
            }
        }
        
        // 4. Check/Insert store
        echo "<h2>Checking Store</h2>";
        $stmt = $pdo->query("SELECT magazaID FROM magaza LIMIT 1");
        $storeData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $storeId = null;
        if ($storeData) {
            $storeId = $storeData['magazaID'];
            echo "<p>Store already exists with ID: {$storeId}</p>";
        } else {
            // Check if personel exists
            $stmt = $pdo->query("SELECT personelID FROM personel LIMIT 1");
            $personelData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$personelData) {
                // Insert default personel if none exists
                $stmt = $pdo->prepare("INSERT INTO personel (ad, soyad, eposta, sifre, rol, telefon, iseBaslamaTarihi) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute(['Admin', 'User', 'admin@test.com', password_hash('admin123', PASSWORD_DEFAULT), 'Admin', '5551234567', date('Y-m-d')]);
                $personelId = $pdo->lastInsertId();
                echo "<p>Created default personel with ID: {$personelId}</p>";
            } else {
                $personelId = $personelData['personelID'];
            }
            
            // Insert store
            $stmt = $pdo->prepare("INSERT INTO magaza (magazaAdi, adres, eposta, sifre, telefon, personelID) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['Test Mağaza', 'Test Adres', 'test@store.com', password_hash('store123', PASSWORD_DEFAULT), '5559876543', $personelId]);
            $storeId = $pdo->lastInsertId();
            echo "<p>Created test store with ID: {$storeId}</p>";
        }
        
        // 5. Insert products
        echo "<h2>Inserting Products</h2>";
        $products = [
            [
                'urunAdi' => 'Nike Air Max 90',
                'urunAciklama' => 'Klasik Nike Air Max 90 model spor ayakkabı',
                'kategoriID' => $categoryIds['Erkek'],
                'temelFiyat' => 1299.99,
                'indirimliFiyat' => 999.99
            ],
            [
                'urunAdi' => 'Adidas Superstar',
                'urunAciklama' => 'Klasik Adidas Superstar model spor ayakkabı',
                'kategoriID' => $categoryIds['Erkek'],
                'temelFiyat' => 1199.99,
                'indirimliFiyat' => 899.99
            ],
            [
                'urunAdi' => 'Puma RS-X',
                'urunAciklama' => 'Modern Puma RS-X model spor ayakkabı',
                'kategoriID' => $categoryIds['Kadın'],
                'temelFiyat' => 1499.99,
                'indirimliFiyat' => 1299.99
            ]
        ];
        
        $productIds = [];
        foreach ($products as $product) {
            $stmt = $pdo->prepare("INSERT INTO urun (urunAdi, urunAciklama, kategoriID, magazaID, temelFiyat, indirimliFiyat, stokTakipTipi, genelStokMiktari) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $product['urunAdi'],
                $product['urunAciklama'],
                $product['kategoriID'],
                $storeId,
                $product['temelFiyat'],
                $product['indirimliFiyat'],
                'Detaylı', // Using detailed inventory tracking
                0 // No general stock since we use variants
            ]);
            
            $productId = $pdo->lastInsertId();
            $productIds[] = $productId;
            echo "<p>Inserted product {$product['urunAdi']} with ID: {$productId}</p>";
            
            // 6. Insert product variants for each product
            echo "<h3>Inserting Variants for {$product['urunAdi']}</h3>";
            
            // Each product will have variants with different colors and sizes
            foreach ($colorIds as $colorName => $colorId) {
                // Not all sizes for all colors to make it more realistic
                $sizesToUse = array_slice($sizes, rand(0, 3), rand(3, 6));
                
                foreach ($sizesToUse as $size) {
                    $sizeId = $sizeIds[$size['numara']];
                    $stock = rand(5, 20);
                    
                    $stmt = $pdo->prepare("INSERT INTO urunvaryant (urunID, renkID, bedenID, stokMiktari, barkod, ekFiyat, durum) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $productId,
                        $colorId,
                        $sizeId,
                        $stock,
                        'BRK'.rand(10000, 99999), // Random barcode
                        rand(0, 100), // Random additional price
                        'Aktif'
                    ]);
                    
                    $variantId = $pdo->lastInsertId();
                    echo "<p>Inserted variant for product ID: {$productId}, Color: {$colorName}, Size: {$size['numara']}, Variant ID: {$variantId}</p>";
                }
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    echo "<h2>All test data inserted successfully!</h2>";
    echo "<p><a href='check-db.php'>View Database Content</a></p>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo "<h2>Error occurred:</h2>";
    echo "<p>{$e->getMessage()}</p>";
}
?> 