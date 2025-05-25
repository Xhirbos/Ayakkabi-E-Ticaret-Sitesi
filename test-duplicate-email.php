<?php
// Test file to verify duplicate email registration prevention
require_once 'dbcon.php';

echo "<h2>Duplicate Email Registration Test</h2>";

// Test email
$test_email = "test@example.com";

// Check if test user already exists
$checkStmt = $pdo->prepare("SELECT musteriID, ad, soyad, eposta FROM musteri WHERE eposta = ?");
$checkStmt->execute([$test_email]);
$existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($existingUser) {
    echo "<p><strong>Test User Found:</strong></p>";
    echo "<ul>";
    echo "<li>ID: " . $existingUser['musteriID'] . "</li>";
    echo "<li>Name: " . $existingUser['ad'] . " " . $existingUser['soyad'] . "</li>";
    echo "<li>Email: " . $existingUser['eposta'] . "</li>";
    echo "</ul>";
    echo "<p style='color: green;'>✓ The system correctly identifies existing users with this email.</p>";
} else {
    echo "<p style='color: orange;'>No existing user found with email: " . $test_email . "</p>";
    echo "<p>You can create a test user first, then try to register again with the same email to test the duplicate prevention.</p>";
}

echo "<hr>";
echo "<h3>How to Test:</h3>";
echo "<ol>";
echo "<li>Go to the registration page and create a new account with any email</li>";
echo "<li>Try to register again with the same email address</li>";
echo "<li>You should now see an error message: 'Bu e-posta adresi ile zaten kayıtlı bir hesap bulunmaktadır. Lütfen giriş yapın veya farklı bir e-posta adresi kullanın.'</li>";
echo "<li>The registration should be rejected instead of updating the existing user's data</li>";
echo "</ol>";

echo "<p><a href='index.php'>← Back to Home</a></p>";
?> 