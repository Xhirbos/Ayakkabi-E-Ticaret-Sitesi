<?php
// register.php
require_once 'dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($fullname && $username && $password) {
        // Kullanıcı adı kontrolü
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Bu kullanıcı adı zaten alınmış!';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (fullname, username, password) VALUES (?, ?, ?)');
            $stmt->execute([$fullname, $username, $hashed]);
            header('Location: login.php?registered=1');
            exit;
        }
    } else {
        $error = 'Lütfen tüm alanları doldurun!';
    }
}
?>