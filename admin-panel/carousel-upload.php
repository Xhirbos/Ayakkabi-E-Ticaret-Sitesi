<?php
// Hata ayıklama için
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Admin oturum kontrolü
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']);
    exit;
}

if (!isset($_FILES['carousel_image']) || $_FILES['carousel_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Dosya yükleme hatası']);
    exit;
}

$file = $_FILES['carousel_image'];
$uploadDir = '../uploads/carousel/';

// Dosya türü kontrolü
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$fileType = $file['type'];

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir.']);
    exit;
}

// Dosya boyutu kontrolü (5MB max)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'Dosya boyutu çok büyük. Maksimum 5MB olmalıdır.']);
    exit;
}

// Dosya adını güvenli hale getir
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'carousel_' . time() . '_' . uniqid() . '.' . $fileExtension;
$filePath = $uploadDir . $fileName;

// Dosyayı yükle
if (move_uploaded_file($file['tmp_name'], $filePath)) {
    $fileUrl = 'uploads/carousel/' . $fileName;
    echo json_encode([
        'success' => true, 
        'message' => 'Dosya başarıyla yüklendi',
        'url' => $fileUrl
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Dosya yükleme başarısız']);
}
?> 