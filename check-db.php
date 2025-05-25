<?php
require_once 'dbcon.php';

echo "<h1>Database Check</h1>";

// Check Urun (Products)
echo "<h2>Products (Urun)</h2>";
$query = "SELECT * FROM urun";
$stmt = $pdo->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($products) > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Kategori</th><th>Fiyat</th></tr>";
    foreach ($products as $product) {
        echo "<tr>";
        echo "<td>{$product['urunID']}</td>";
        echo "<td>{$product['urunAdi']}</td>";
        echo "<td>{$product['kategoriID']}</td>";
        echo "<td>{$product['temelFiyat']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No products found. You need to add products first.</p>";
}

// Check Renk (Colors)
echo "<h2>Colors (Renk)</h2>";
$query = "SELECT * FROM renk";
$stmt = $pdo->query($query);
$colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($colors) > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Color Name</th><th>Color Code</th></tr>";
    foreach ($colors as $color) {
        echo "<tr>";
        echo "<td>{$color['renkID']}</td>";
        echo "<td>{$color['renkAdi']}</td>";
        echo "<td>{$color['renkKodu']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No colors found. You need to add colors to database.</p>";
}

// Check Beden (Sizes)
echo "<h2>Sizes (Beden)</h2>";
$query = "SELECT * FROM beden";
$stmt = $pdo->query($query);
$sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($sizes) > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Size</th><th>System</th></tr>";
    foreach ($sizes as $size) {
        echo "<tr>";
        echo "<td>{$size['bedenID']}</td>";
        echo "<td>{$size['numara']}</td>";
        echo "<td>{$size['ulkeSistemi']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No sizes found. You need to add sizes to database.</p>";
}

// Check UrunVaryant (Product Variants)
echo "<h2>Product Variants (UrunVaryant)</h2>";
$query = "SELECT uv.*, r.renkAdi, b.numara, u.urunAdi 
         FROM urunvaryant uv
         LEFT JOIN renk r ON uv.renkID = r.renkID
         LEFT JOIN beden b ON uv.bedenID = b.bedenID
         LEFT JOIN urun u ON uv.urunID = u.urunID";
$stmt = $pdo->query($query);
$variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($variants) > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Variant ID</th><th>Product ID</th><th>Product Name</th><th>Color</th><th>Size</th><th>Stock</th></tr>";
    foreach ($variants as $variant) {
        echo "<tr>";
        echo "<td>{$variant['varyantID']}</td>";
        echo "<td>{$variant['urunID']}</td>";
        echo "<td>{$variant['urunAdi']}</td>";
        echo "<td>{$variant['renkAdi']}</td>";
        echo "<td>{$variant['numara']}</td>";
        echo "<td>{$variant['stokMiktari']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No product variants found. You need to add product variants to database.</p>";
}
?> 