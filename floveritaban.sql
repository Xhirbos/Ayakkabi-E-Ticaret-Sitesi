-- --------------------------------------------------------
-- Sunucu:                       127.0.0.1
-- Sunucu sürümü:                10.4.32-MariaDB - mariadb.org binary distribution
-- Sunucu İşletim Sistemi:       Win64
-- HeidiSQL Sürüm:               12.10.0.7000
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- flo için veritabanı yapısı dökülüyor
CREATE DATABASE IF NOT EXISTS `flo` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `flo`;

-- tablo yapısı dökülüyor flo.beden
CREATE TABLE IF NOT EXISTS `beden` (
  `bedenID` int(11) NOT NULL AUTO_INCREMENT,
  `numara` decimal(4,1) NOT NULL,
  `ulkeSistemi` varchar(20) NOT NULL COMMENT 'EU, US, UK vb.',
  `olusturmaTarihi` datetime DEFAULT current_timestamp(),
  `guncellemeTarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`bedenID`),
  UNIQUE KEY `numara` (`numara`,`ulkeSistemi`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.carousel
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.kategori
CREATE TABLE IF NOT EXISTS `kategori` (
  `kategoriID` int(4) NOT NULL AUTO_INCREMENT,
  `kategoriAdi` varchar(100) NOT NULL,
  `olusturmaTarihi` datetime DEFAULT current_timestamp(),
  `guncellemeTarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`kategoriID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.magaza
CREATE TABLE IF NOT EXISTS `magaza` (
  `magazaID` int(8) NOT NULL AUTO_INCREMENT,
  `magazaAdi` varchar(100) NOT NULL,
  `adres` varchar(255) NOT NULL,
  `eposta` varchar(100) NOT NULL,
  `sifre` varchar(255) NOT NULL COMMENT 'Hashlenmiş şifre',
  `telefon` varchar(20) NOT NULL,
  `personelID` int(6) NOT NULL COMMENT 'Mağaza sorumlusu',
  `kayitTarihi` datetime DEFAULT current_timestamp(),
  `aktif` tinyint(1) DEFAULT 1,
  `basvuruDurumu` enum('Beklemede','Onaylandi','Reddedildi') DEFAULT 'Beklemede',
  `redNedeni` text DEFAULT NULL,
  PRIMARY KEY (`magazaID`),
  UNIQUE KEY `eposta` (`eposta`),
  UNIQUE KEY `eposta_2` (`eposta`,`telefon`),
  KEY `personelID` (`personelID`),
  CONSTRAINT `magaza_ibfk_1` FOREIGN KEY (`personelID`) REFERENCES `personel` (`personelID`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.musteri
CREATE TABLE IF NOT EXISTS `musteri` (
  `musteriID` int(5) NOT NULL AUTO_INCREMENT,
  `eposta` varchar(100) NOT NULL,
  `ad` varchar(50) NOT NULL,
  `soyad` varchar(50) NOT NULL,
  `adres` varchar(1000) DEFAULT NULL,
  `sifre` varchar(255) NOT NULL COMMENT 'Hashlenmiş şifre',
  `telefon` varchar(20) NOT NULL,
  `kayitTarihi` datetime DEFAULT current_timestamp(),
  `sonGirisTarihi` datetime DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`musteriID`),
  UNIQUE KEY `eposta` (`eposta`),
  UNIQUE KEY `eposta_2` (`eposta`,`telefon`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.musteriadres
CREATE TABLE IF NOT EXISTS `musteriadres` (
  `adresID` int(11) NOT NULL AUTO_INCREMENT,
  `musteriID` int(5) NOT NULL,
  `baslik` varchar(50) NOT NULL COMMENT 'Ev, İş vb.',
  `adres` text NOT NULL,
  `il` varchar(50) NOT NULL,
  `ilce` varchar(50) NOT NULL,
  `postaKodu` varchar(20) DEFAULT NULL,
  `ulke` varchar(50) DEFAULT 'Türkiye',
  `varsayilan` tinyint(1) DEFAULT 0,
  `olusturmaTarihi` datetime DEFAULT current_timestamp(),
  `guncellemeTarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`adresID`),
  KEY `musteriID` (`musteriID`),
  CONSTRAINT `musteriadres_ibfk_1` FOREIGN KEY (`musteriID`) REFERENCES `musteri` (`musteriID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.personel
CREATE TABLE IF NOT EXISTS `personel` (
  `personelID` int(6) NOT NULL AUTO_INCREMENT,
  `ad` varchar(50) NOT NULL,
  `soyad` varchar(50) NOT NULL,
  `eposta` varchar(100) NOT NULL,
  `sifre` varchar(255) NOT NULL COMMENT 'Hashlenmiş şifre',
  `rol` enum('Admin','Moderator','Personel') NOT NULL DEFAULT 'Personel',
  `telefon` varchar(20) NOT NULL,
  `iseBaslamaTarihi` date NOT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`personelID`),
  UNIQUE KEY `eposta` (`eposta`),
  UNIQUE KEY `eposta_2` (`eposta`,`telefon`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.renk
CREATE TABLE IF NOT EXISTS `renk` (
  `renkID` int(11) NOT NULL AUTO_INCREMENT,
  `renkAdi` varchar(50) NOT NULL,
  `renkKodu` varchar(20) DEFAULT NULL,
  `olusturmaTarihi` datetime DEFAULT current_timestamp(),
  `guncellemeTarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`renkID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.sepet
CREATE TABLE IF NOT EXISTS `sepet` (
  `sepetID` int(8) NOT NULL AUTO_INCREMENT,
  `musteriID` int(5) NOT NULL,
  `urunID` int(7) NOT NULL,
  `varyantID` int(11) DEFAULT NULL COMMENT 'Varyantlı ürünler için',
  `miktar` int(11) NOT NULL DEFAULT 1,
  `birimFiyat` decimal(10,2) NOT NULL,
  `eklenmeTarihi` datetime DEFAULT current_timestamp(),
  `guncellemeTarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sepetID`),
  UNIQUE KEY `musteriID` (`musteriID`,`urunID`,`varyantID`),
  KEY `urunID` (`urunID`),
  KEY `varyantID` (`varyantID`),
  CONSTRAINT `sepet_ibfk_1` FOREIGN KEY (`musteriID`) REFERENCES `musteri` (`musteriID`),
  CONSTRAINT `sepet_ibfk_2` FOREIGN KEY (`urunID`) REFERENCES `urun` (`urunID`),
  CONSTRAINT `sepet_ibfk_3` FOREIGN KEY (`varyantID`) REFERENCES `urunvaryant` (`varyantID`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.siparis
CREATE TABLE IF NOT EXISTS `siparis` (
  `siparisID` int(8) NOT NULL AUTO_INCREMENT,
  `siparisNo` varchar(20) NOT NULL,
  `musteriID` int(5) NOT NULL,
  `adresID` int(11) NOT NULL,
  `siparisTarihi` datetime DEFAULT current_timestamp(),
  `toplamTutar` decimal(12,2) NOT NULL,
  `indirimTutari` decimal(10,2) DEFAULT 0.00,
  `odemeTutari` decimal(12,2) NOT NULL,
  `kargoUcreti` decimal(10,2) DEFAULT 0.00,
  `odemeYontemi` enum('KrediKarti','Havale','KapidaOdeme') NOT NULL,
  `durum` enum('Hazirlaniyor','Kargoda','TeslimEdildi','IptalEdildi') DEFAULT 'Hazirlaniyor',
  `kargoTakipNo` varchar(50) DEFAULT NULL,
  `iptalAciklamasi` text DEFAULT NULL,
  PRIMARY KEY (`siparisID`),
  UNIQUE KEY `siparisNo` (`siparisNo`),
  KEY `musteriID` (`musteriID`),
  KEY `adresID` (`adresID`),
  CONSTRAINT `siparis_ibfk_1` FOREIGN KEY (`musteriID`) REFERENCES `musteri` (`musteriID`),
  CONSTRAINT `siparis_ibfk_2` FOREIGN KEY (`adresID`) REFERENCES `musteriadres` (`adresID`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.siparisdetay
CREATE TABLE IF NOT EXISTS `siparisdetay` (
  `siparisDetayID` int(9) NOT NULL AUTO_INCREMENT,
  `siparisID` int(8) NOT NULL,
  `urunID` int(7) NOT NULL,
  `varyantID` int(11) DEFAULT NULL COMMENT 'Varyantlı ürünler için',
  `birimFiyat` decimal(10,2) NOT NULL,
  `indirimliFiyat` decimal(10,2) DEFAULT NULL,
  `miktar` int(11) NOT NULL DEFAULT 1,
  `toplamTutar` decimal(12,2) NOT NULL,
  `durum` enum('Beklemede','Hazirlaniyor','Gonderildi','TeslimEdildi','IadeEdildi') DEFAULT 'Beklemede',
  PRIMARY KEY (`siparisDetayID`),
  KEY `siparisID` (`siparisID`),
  KEY `urunID` (`urunID`),
  KEY `varyantID` (`varyantID`),
  CONSTRAINT `siparisdetay_ibfk_1` FOREIGN KEY (`siparisID`) REFERENCES `siparis` (`siparisID`),
  CONSTRAINT `siparisdetay_ibfk_2` FOREIGN KEY (`urunID`) REFERENCES `urun` (`urunID`),
  CONSTRAINT `siparisdetay_ibfk_3` FOREIGN KEY (`varyantID`) REFERENCES `urunvaryant` (`varyantID`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.urun
CREATE TABLE IF NOT EXISTS `urun` (
  `urunID` int(7) NOT NULL AUTO_INCREMENT,
  `urunAdi` varchar(100) NOT NULL,
  `urunAciklama` text DEFAULT NULL,
  `kategoriID` int(4) NOT NULL,
  `magazaID` int(8) NOT NULL,
  `temelFiyat` decimal(10,2) NOT NULL,
  `indirimliFiyat` decimal(10,2) DEFAULT NULL,
  `stokTakipTipi` enum('Basit','Detaylı') NOT NULL DEFAULT 'Basit',
  `genelStokMiktari` int(11) DEFAULT 0 COMMENT 'Basit stok takibi için',
  `minStokSeviyesi` int(11) DEFAULT 5,
  `olusturmaTarihi` datetime DEFAULT current_timestamp(),
  `guncellemeTarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `aktif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`urunID`),
  KEY `kategoriID` (`kategoriID`),
  KEY `magazaID` (`magazaID`),
  CONSTRAINT `urun_ibfk_1` FOREIGN KEY (`kategoriID`) REFERENCES `kategori` (`kategoriID`),
  CONSTRAINT `urun_ibfk_2` FOREIGN KEY (`magazaID`) REFERENCES `magaza` (`magazaID`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.urunresim
CREATE TABLE IF NOT EXISTS `urunresim` (
  `resimID` int(9) NOT NULL AUTO_INCREMENT,
  `urunID` int(7) NOT NULL,
  `varyantID` int(11) DEFAULT NULL COMMENT 'Varyanta özel resimler için',
  `resimURL` varchar(255) NOT NULL,
  `sira` int(2) NOT NULL DEFAULT 1,
  `anaResim` tinyint(1) DEFAULT 0,
  `olusturmaTarihi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`resimID`),
  KEY `urunID` (`urunID`),
  KEY `varyantID` (`varyantID`),
  CONSTRAINT `urunresim_ibfk_1` FOREIGN KEY (`urunID`) REFERENCES `urun` (`urunID`),
  CONSTRAINT `urunresim_ibfk_2` FOREIGN KEY (`varyantID`) REFERENCES `urunvaryant` (`varyantID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.urunvaryant
CREATE TABLE IF NOT EXISTS `urunvaryant` (
  `varyantID` int(11) NOT NULL AUTO_INCREMENT,
  `urunID` int(7) NOT NULL,
  `renkID` int(11) NOT NULL,
  `bedenID` int(11) NOT NULL,
  `stokMiktari` int(11) NOT NULL DEFAULT 0,
  `barkod` varchar(50) DEFAULT NULL,
  `ekFiyat` decimal(10,2) DEFAULT 0.00,
  `durum` enum('Aktif','Pasif') DEFAULT 'Aktif',
  `olusturmaTarihi` datetime DEFAULT current_timestamp(),
  `guncellemeTarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`varyantID`),
  UNIQUE KEY `urunID` (`urunID`,`renkID`,`bedenID`),
  UNIQUE KEY `barkod` (`barkod`),
  KEY `renkID` (`renkID`),
  KEY `bedenID` (`bedenID`),
  CONSTRAINT `urunvaryant_ibfk_1` FOREIGN KEY (`urunID`) REFERENCES `urun` (`urunID`),
  CONSTRAINT `urunvaryant_ibfk_2` FOREIGN KEY (`renkID`) REFERENCES `renk` (`renkID`),
  CONSTRAINT `urunvaryant_ibfk_3` FOREIGN KEY (`bedenID`) REFERENCES `beden` (`bedenID`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tablo yapısı dökülüyor flo.yorum
CREATE TABLE IF NOT EXISTS `yorum` (
  `yorumID` int(3) NOT NULL AUTO_INCREMENT,
  `musteriID` int(5) NOT NULL,
  `urunID` int(7) NOT NULL,
  `siparisDetayID` int(9) DEFAULT NULL COMMENT 'Satın alınan ürün için',
  `puan` tinyint(4) NOT NULL CHECK (`puan` between 1 and 5),
  `baslik` varchar(100) DEFAULT NULL,
  `yorum` text NOT NULL,
  `yanit` text DEFAULT NULL,
  `yanitTarihi` datetime DEFAULT NULL,
  `onayDurumu` enum('Beklemede','Onaylandi','Reddedildi') DEFAULT 'Beklemede',
  `olusturmaTarihi` datetime DEFAULT current_timestamp(),
  `guncellemeTarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`yorumID`),
  KEY `musteriID` (`musteriID`),
  KEY `urunID` (`urunID`),
  KEY `siparisDetayID` (`siparisDetayID`),
  CONSTRAINT `yorum_ibfk_1` FOREIGN KEY (`musteriID`) REFERENCES `musteri` (`musteriID`),
  CONSTRAINT `yorum_ibfk_2` FOREIGN KEY (`urunID`) REFERENCES `urun` (`urunID`),
  CONSTRAINT `yorum_ibfk_3` FOREIGN KEY (`siparisDetayID`) REFERENCES `siparisdetay` (`siparisDetayID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Veri çıktısı seçilmemişti

-- tetikleyici yapısı dökülüyor flo.after_siparisdetay_cancel
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER after_siparisdetay_cancel
AFTER UPDATE ON SiparisDetay
FOR EACH ROW
BEGIN
    IF NEW.durum = 'IadeEdildi' AND OLD.durum != 'IadeEdildi' THEN
        IF NEW.varyantID IS NOT NULL THEN
            -- Varyantlı ürün stok geri ekleme
            UPDATE UrunVaryant 
            SET stokMiktari = stokMiktari + NEW.miktar
            WHERE varyantID = NEW.varyantID;
        ELSE
            -- Varyantsız ürün stok geri ekleme
            UPDATE Urun 
            SET genelStokMiktari = genelStokMiktari + NEW.miktar
            WHERE urunID = NEW.urunID;
        END IF;
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- tetikleyici yapısı dökülüyor flo.after_siparisdetay_insert
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER after_siparisdetay_insert
AFTER INSERT ON SiparisDetay
FOR EACH ROW
BEGIN
    IF NEW.varyantID IS NOT NULL THEN
        -- Varyantlı ürün stok güncelleme
        UPDATE UrunVaryant 
        SET stokMiktari = stokMiktari - NEW.miktar
        WHERE varyantID = NEW.varyantID;
    ELSE
        -- Varyantsız ürün stok güncelleme
        UPDATE Urun 
        SET genelStokMiktari = genelStokMiktari - NEW.miktar
        WHERE urunID = NEW.urunID;
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
