-- Carousel table for homepage slider images
CREATE TABLE IF NOT EXISTS `carousel` (
  `carouselID` int(11) NOT NULL AUTO_INCREMENT,
  `baslik` varchar(255) DEFAULT NULL COMMENT 'Carousel slide title',
  `aciklama` text DEFAULT NULL COMMENT 'Carousel slide description',
  `resimURL` varchar(500) NOT NULL COMMENT 'Image URL or path',
  `linkURL` varchar(500) DEFAULT NULL COMMENT 'Optional link when slide is clicked',
  `sira` int(3) NOT NULL DEFAULT 1 COMMENT 'Display order',
  `aktif` tinyint(1) DEFAULT 1 COMMENT 'Active status',
  `olusturmaTarihi` datetime DEFAULT current_timestamp(),
  `guncellemeTarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`carouselID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert some sample carousel data
INSERT INTO `carousel` (`baslik`, `aciklama`, `resimURL`, `linkURL`, `sira`, `aktif`) VALUES
('Yeni Sezon Koleksiyonu', 'En trend ayakkabı modelleri burada!', 'https://plus.unsplash.com/premium_photo-1713200811001-af93d0dcdfc2?w=1200&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxmZWF0dXJlZC1waG90b3MtZmVlZHwyfHx8ZW58MHx8fHx8', 'category.php?id=4', 1, 1),
('Erkek Ayakkabıları', 'Kaliteli ve şık erkek ayakkabı modelleri', 'https://images.unsplash.com/photo-1746023841861-d5fcf4b3d510?w=1200&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxmZWF0dXJlZC1waG90b3MtZmVlZHwxfHx8ZW58MHx8fHx8', 'category.php?id=3', 2, 1),
('Spor Ayakkabıları', 'Konforlu ve dayanıklı spor ayakkabıları', 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=1200&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8c25lYWtlcnN8ZW58MHx8MHx8fDA%3D', 'category.php?id=5', 3, 1); 