<?php
session_start();
$siparisNo = isset($_GET['siparisNo']) ? htmlspecialchars($_GET['siparisNo']) : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sipariş Başarılı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4 class="alert-heading">Siparişiniz Başarıyla Tamamlandı!</h4>
            <p>Sipariş Numaranız: <strong><?php echo $siparisNo; ?></strong></p>
            <hr>
            <a href="orders.php" class="btn btn-primary">Siparişlerim</a>
            <a href="index.php" class="btn btn-secondary">Ana Sayfa</a>
        </div>
    </div>
</body>
</html> 