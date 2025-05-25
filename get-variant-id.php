<?php
session_start();
require_once 'dbcon.php';

// API endpoint to get variant ID
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'variantId' => 0, 'message' => ''];
    
    $productId = isset($_POST['productId']) ? (int)$_POST['productId'] : 0;
    $color = isset($_POST['color']) ? $_POST['color'] : '';
    $size = isset($_POST['size']) ? $_POST['size'] : '';
    
    if ($productId <= 0 || empty($color) || empty($size)) {
        $response['message'] = 'Geçersiz ürün, renk veya numara';
        echo json_encode($response);
        exit;
    }
    
    // Log the request parameters for debugging
    error_log("Looking up variant - Product ID: $productId, Color: $color, Size: $size");
    
    try {
        // First, check if the color exists
        $colorCheck = $pdo->prepare("SELECT renkID FROM renk WHERE renkAdi = ?");
        $colorCheck->execute([$color]);
        $colorData = $colorCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$colorData) {
            // Color not found, try a more flexible search
            error_log("Color not found exactly: $color, trying case-insensitive search");
            $colorCheck = $pdo->prepare("SELECT renkID, renkAdi FROM renk WHERE LOWER(renkAdi) = LOWER(?)");
            $colorCheck->execute([$color]);
            $colorData = $colorCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($colorData) {
                $color = $colorData['renkAdi']; // Use the exact case from database
                error_log("Found color with case-insensitive match: " . $colorData['renkAdi']);
            }
        }
        
        // Check if the size exists
        $sizeCheck = $pdo->prepare("SELECT bedenID FROM beden WHERE numara = ?");
        $sizeCheck->execute([$size]);
        $sizeData = $sizeCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$sizeData) {
            // Size not found, try to convert to float
            error_log("Size not found exactly: $size, trying as float");
            $sizeFloat = (float)$size;
            $sizeCheck = $pdo->prepare("SELECT bedenID FROM beden WHERE numara = ?");
            $sizeCheck->execute([$sizeFloat]);
            $sizeData = $sizeCheck->fetch(PDO::FETCH_ASSOC);
        }
        
        // Let's see what variants exist for this product
        $variantCheck = $pdo->prepare("
            SELECT uv.varyantID, r.renkAdi, b.numara 
            FROM urunvaryant uv
            JOIN renk r ON uv.renkID = r.renkID
            JOIN beden b ON uv.bedenID = b.bedenID
            WHERE uv.urunID = ?
        ");
        $variantCheck->execute([$productId]);
        $allVariants = $variantCheck->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($allVariants)) {
            error_log("No variants found for product ID: $productId");
            $response['message'] = 'Bu ürün için varyant bulunamadı';
            echo json_encode($response);
            exit;
        }
        
        error_log("Available variants for product ID $productId: " . json_encode($allVariants));
        
        // Try to get the variant with a more flexible query
        $stmt = $pdo->prepare("
            SELECT uv.varyantID
            FROM urunvaryant uv
            JOIN renk r ON uv.renkID = r.renkID
            JOIN beden b ON uv.bedenID = b.bedenID
            WHERE uv.urunID = ? 
            AND LOWER(r.renkAdi) = LOWER(?) 
            AND b.numara = ?
        ");
        
        // Try with both string and float value for size
        $stmt->execute([$productId, $color, $size]);
        $variant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$variant) {
            $stmt->execute([$productId, $color, (float)$size]);
            $variant = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($variant) {
            $response['success'] = true;
            $response['variantId'] = (int)$variant['varyantID'];
            error_log("Found variant ID: " . $variant['varyantID']);
        } else {
            $response['debug'] = [
                'productId' => $productId,
                'color' => $color,
                'size' => $size,
                'availableVariants' => $allVariants
            ];
            $response['message'] = 'Seçilen renk ve numara için varyant bulunamadı';
            error_log("Variant not found for - Product ID: $productId, Color: $color, Size: $size");
        }
    } catch (PDOException $e) {
        error_log("Database error in get-variant-id.php: " . $e->getMessage());
        $response['message'] = 'Varyant bilgisi alınırken bir hata oluştu';
        $response['error'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// If accessed directly, redirect to home page
header('Location: index.php');
exit;
?> 