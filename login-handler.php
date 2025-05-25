<?php
// Set proper character encoding
header('Content-Type: application/json; charset=utf-8');

// Ensure PHP errors don't break JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Force new session cookie
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

// Start or resume the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    error_log("Started new session with ID: " . session_id());
} else {
    error_log("Using existing session with ID: " . session_id());
}

// Log session information for debugging
error_log("SESSION STATUS: " . session_status() . " (1=PHP_SESSION_NONE, 2=PHP_SESSION_ACTIVE)");
error_log("SESSION ID: " . session_id());
error_log("Current session data: " . (isset($_SESSION['user']) ? json_encode($_SESSION['user']) : 'No user session'));

// Ensure session cookies are secure
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
// Increase session cookie lifetime to reduce session loss issues
ini_set('session.cookie_lifetime', 86400); // 24 hours

require_once 'dbcon.php';

$response = [
    'success' => false,
    'message' => 'İşlem gerçekleştirilemedi.'
];

// Check what action is requested
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'login':
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        if (empty($email) || empty($password)) {
            $response['message'] = 'E-posta ve şifre gereklidir.';
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM musteri WHERE eposta = ? AND aktif = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['sifre'])) {
                // Update last login date
                $updateStmt = $pdo->prepare("UPDATE musteri SET sonGirisTarihi = NOW() WHERE musteriID = ?");
                $updateStmt->execute([$user['musteriID']]);
                
                // Ensure musteriID exists and is valid
                if (empty($user['musteriID']) || !is_numeric($user['musteriID']) || (int)$user['musteriID'] <= 0) {
                    $response['message'] = "Kullanıcı bilgilerinde sorun oluştu. Lütfen tekrar deneyin.";
                    error_log("Invalid musteriID on login: " . ($user['musteriID'] ?: 'empty'));
                    break;
                }
                
                // Set session with proper type casting
                $_SESSION['user'] = [
                    'id' => (int)$user['musteriID'],
                    'isim' => $user['ad'],
                    'soyad' => $user['soyad'],
                    'email' => $user['eposta']
                ];
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                error_log("User logged in with ID: " . $user['musteriID'] . ", Session: " . print_r($_SESSION['user'], true));
                
                $response['success'] = true;
                $response['message'] = "Giriş başarılı! Hoş geldiniz, " . $user['ad'] . ".";
            } else {
                $response['message'] = "E-posta veya şifre hatalı!";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $response['message'] = "Giriş yapılırken bir hata oluştu. Lütfen tekrar deneyin.";
        }
        break;
        
    case 'register':
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $ad = isset($_POST['ad']) ? trim($_POST['ad']) : '';
        $soyad = isset($_POST['soyad']) ? trim($_POST['soyad']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $telefon = isset($_POST['telefon']) ? trim($_POST['telefon']) : '';
        
        // Log values for debugging (mask password)
        error_log("Registration attempt: Email=" . $email . ", Name=" . $ad . " " . $soyad . ", Phone=" . $telefon);
        
        // Validate input
        $errors = [];
        if (empty($ad)) $errors[] = "Ad gereklidir";
        if (empty($soyad)) $errors[] = "Soyad gereklidir";
        if (empty($password)) $errors[] = "Şifre gereklidir";
        if (empty($email)) $errors[] = "E-posta gereklidir";
        if (empty($telefon)) $errors[] = "Telefon gereklidir";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Geçerli bir e-posta adresi giriniz";
        
        if (!empty($errors)) {
            $response['success'] = false;
            $response['message'] = $errors[0]; // Just send the first error for now
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        try {
            // Start a transaction to ensure data consistency
            $pdo->beginTransaction();
            
            // First check if email already exists
            $checkStmt = $pdo->prepare("SELECT musteriID, aktif FROM musteri WHERE eposta = ?");
            $checkStmt->execute([$email]);
            $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // If user exists, show error message instead of updating
            if ($existingUser) {
                $pdo->rollBack();
                $response['success'] = false;
                $response['message'] = "Bu e-posta adresi ile zaten kayıtlı bir hesap bulunmaktadır. Lütfen giriş yapın veya farklı bir e-posta adresi kullanın.";
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Create new user
            $insertStmt = $pdo->prepare("INSERT INTO musteri (eposta, ad, soyad, sifre, telefon, kayitTarihi, aktif) 
                                VALUES (?, ?, ?, ?, ?, NOW(), 1)");
            $insertStmt->execute([$email, $ad, $soyad, $hashed_password, $telefon]);
            
            // Get the new user ID
            $musteriID = $pdo->lastInsertId();
            
            error_log("Created new user: " . $musteriID . ", Email: " . $email);
            
            // Commit the transaction
            $pdo->commit();
            
            // Clear any existing session data
            $_SESSION = array();
            
            // Create a fresh session
            if (session_status() != PHP_SESSION_ACTIVE) {
                session_start();
            } else {
                // If session is already active, regenerate ID for security
                session_regenerate_id(true);
            }
            
            // Set user session data consistently
            $_SESSION['user'] = [
                'id' => (int)$musteriID,
                'isim' => $ad,
                'soyad' => $soyad,
                'email' => $email
            ];
            
            // Don't set cookies to prevent duplicate notifications
            // setcookie('registration_success', '1', time() + 3600, '/');
            // setcookie('user_name', $ad, time() + 3600, '/');
            
            // Create success response
            $response['success'] = true;
            // Don't redirect to homepage, just reload current page
            // $response['redirect'] = 'index.php?registered=1&t=' . time();
            $response['message'] = "Kayıt başarılı! Hoş geldiniz, " . $ad . ".";
            $response['no_toast'] = true; // Add flag to prevent toast in JS
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
            
        } catch (PDOException $e) {
            // Rollback on any error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            error_log("Registration error: " . $e->getMessage());
            
            // Check for duplicate entry MySQL error
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate') !== false) {
                $response['success'] = false;
                $response['message'] = "Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin veya farklı bir e-posta adresi kullanın.";
            } else {
                $response['success'] = false;
                $response['message'] = "Kayıt işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.";
            }
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        break;
        
    default:
        $response['message'] = "Geçersiz işlem";
        break;
}

// Return JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit; 