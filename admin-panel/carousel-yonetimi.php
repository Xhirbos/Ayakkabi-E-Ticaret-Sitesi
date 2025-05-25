<?php
// Hata ayıklama için
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'header.php';
require_once '../dbcon.php';

// İşlem mesajları
$message = '';
$message_type = '';

// Carousel ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $baslik = trim($_POST['baslik']);
        $aciklama = trim($_POST['aciklama']);
        $resimURL = trim($_POST['resimURL']);
        $linkURL = trim($_POST['linkURL']);
        $sira = intval($_POST['sira']);
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        
        if (!empty($resimURL)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO carousel (baslik, aciklama, resimURL, linkURL, sira, aktif) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$baslik, $aciklama, $resimURL, $linkURL, $sira, $aktif]);
                $message = "Carousel görseli başarıyla eklendi!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Hata: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Resim URL'si gereklidir!";
            $message_type = "error";
        }
    }
    
    // Carousel güncelleme işlemi
    elseif ($_POST['action'] == 'update') {
        $carouselID = intval($_POST['carouselID']);
        $baslik = trim($_POST['baslik']);
        $aciklama = trim($_POST['aciklama']);
        $resimURL = trim($_POST['resimURL']);
        $linkURL = trim($_POST['linkURL']);
        $sira = intval($_POST['sira']);
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("UPDATE carousel SET baslik = ?, aciklama = ?, resimURL = ?, linkURL = ?, sira = ?, aktif = ? WHERE carouselID = ?");
            $stmt->execute([$baslik, $aciklama, $resimURL, $linkURL, $sira, $aktif, $carouselID]);
            $message = "Carousel görseli başarıyla güncellendi!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Hata: " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    // Carousel silme işlemi
    elseif ($_POST['action'] == 'delete') {
        $carouselID = intval($_POST['carouselID']);
        
        try {
            $stmt = $pdo->prepare("DELETE FROM carousel WHERE carouselID = ?");
            $stmt->execute([$carouselID]);
            $message = "Carousel görseli başarıyla silindi!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Hata: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Carousel verilerini getir
$stmt = $pdo->prepare("SELECT * FROM carousel ORDER BY sira ASC, carouselID ASC");
$stmt->execute();
$carousels = $stmt->fetchAll();

// Kategorileri getir
$kategori_stmt = $pdo->prepare("SELECT kategoriID, kategoriAdi FROM kategori ORDER BY kategoriAdi ASC");
$kategori_stmt->execute();
$kategoriler = $kategori_stmt->fetchAll();
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Carousel Yönetimi</h1>
        <p class="page-subtitle">Anasayfa carousel görsellerini yönetin</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i>
            Yeni Carousel Ekle
        </button>
    </div>
</div>

<!-- Mesaj gösterimi -->
<?php if (!empty($message)): ?>
<div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom: 1.5rem;">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Carousel Listesi -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Carousel Görselleri</h3>
    </div>
    <div class="card-body">
        <?php if (count($carousels) > 0): ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sıra</th>
                        <th>Görsel</th>
                        <th>Başlık</th>
                        <th>Açıklama</th>
                        <th>Link</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carousels as $carousel): ?>
                    <tr>
                        <td><strong><?php echo $carousel['sira']; ?></strong></td>
                        <td>
                            <img src="<?php echo htmlspecialchars($carousel['resimURL']); ?>" 
                                 alt="Carousel Image" 
                                 style="width: 80px; height: 50px; object-fit: cover; border-radius: 4px;">
                        </td>
                        <td><?php echo htmlspecialchars($carousel['baslik'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars(substr($carousel['aciklama'] ?? '', 0, 50)) . (strlen($carousel['aciklama'] ?? '') > 50 ? '...' : ''); ?></td>
                        <td><?php echo htmlspecialchars($carousel['linkURL'] ?? ''); ?></td>
                        <td>
                            <?php if ($carousel['aktif']): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline" onclick="editCarousel(<?php echo htmlspecialchars(json_encode($carousel)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteCarousel(<?php echo $carousel['carouselID']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center" style="padding: 2rem;">
            <i class="fas fa-images" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
            <p>Henüz carousel görseli eklenmemiş.</p>
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i>
                İlk Carousel'ı Ekle
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Carousel Ekleme/Düzenleme Modal -->
<div id="carouselModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="modalTitle">Yeni Carousel Ekle</h3>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="carouselForm" method="post" onsubmit="return validateForm()">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="carouselID" id="carouselID">
                
                <div class="form-group">
                    <label for="baslik">Başlık</label>
                    <input type="text" id="baslik" name="baslik" class="form-control" placeholder="Carousel başlığı (opsiyonel)">
                </div>
                
                <div class="form-group">
                    <label for="aciklama">Açıklama</label>
                    <textarea id="aciklama" name="aciklama" class="form-control" rows="3" placeholder="Carousel açıklaması (opsiyonel)"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="resimURL">Resim URL'si *</label>
                    <input type="text" id="resimURL" name="resimURL" class="form-control" placeholder="https://example.com/image.jpg">
                    <small class="form-text">Resim URL'sini buraya yapıştırın</small>
                </div>
                
                <div class="form-group">
                    <label>Veya Resim Yükle</label>
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Resim dosyasını buraya sürükleyin veya tıklayın</p>
                        <small>Yüklenen resmin URL'si otomatik olarak yukarıdaki alana eklenecektir</small>
                        <input type="file" id="imageUpload" accept="image/*" style="display: none;">
                    </div>
                    <div id="uploadProgress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <span id="progressText">Yükleniyor...</span>
                    </div>
                    <small class="form-text">JPG, PNG, GIF veya WebP formatında, maksimum 5MB</small>
                </div>
                
                <div class="form-group">
                    <label for="linkURL">Link URL'si</label>
                    <div class="link-options">
                        <div class="form-group">
                            <label>
                                <input type="radio" name="linkType" value="category" id="linkTypeCategory" checked>
                                Kategori Sayfası
                            </label>
                            <select id="categorySelect" class="form-control" style="margin-top: 0.5rem;">
                                <option value="">Kategori seçin...</option>
                                <?php foreach ($kategoriler as $kategori): ?>
                                <option value="category.php?id=<?php echo $kategori['kategoriID']; ?>">
                                    <?php echo htmlspecialchars($kategori['kategoriAdi']); ?>
                                </option>
                                <?php endforeach; ?>
                                <option value="index.php">Anasayfa</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="radio" name="linkType" value="custom" id="linkTypeCustom">
                                Özel URL
                            </label>
                            <input type="text" id="customURL" name="customURL" class="form-control" placeholder="https://example.com/page" style="margin-top: 0.5rem;" disabled>
                        </div>
                    </div>
                    <input type="hidden" id="linkURL" name="linkURL">
                    <small class="form-text">Carousel'a tıklandığında gidilecek sayfa</small>
                </div>
                
                <div class="form-group">
                    <label for="sira">Sıra</label>
                    <input type="number" id="sira" name="sira" class="form-control" value="1" min="1" max="999">
                    <small class="form-text">Carousel'ın görüntülenme sırası</small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="aktif" name="aktif" checked>
                        <span class="checkmark"></span>
                        Aktif
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Silme Onay Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Carousel'ı Sil</h3>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Bu carousel görselini silmek istediğinizden emin misiniz?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">İptal</button>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="carouselID" id="deleteCarouselID">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Sil
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Add error handling to functions
function resetUploadArea() {
    try {
        const uploadProgress = document.getElementById('uploadProgress');
        const uploadArea = document.getElementById('uploadArea');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        
        if (uploadProgress && uploadArea && progressFill && progressText) {
            uploadProgress.style.display = 'none';
            uploadArea.style.display = 'block';
            progressFill.style.width = '0%';
            progressText.textContent = 'Yükleniyor...';
            uploadArea.classList.remove('drag-over');
        }
    } catch (error) {
        console.error('Error in resetUploadArea:', error);
    }
}

function updateLinkURL() {
    const linkTypeCategory = document.getElementById('linkTypeCategory');
    const linkTypeCustom = document.getElementById('linkTypeCustom');
    const categorySelect = document.getElementById('categorySelect');
    const customURL = document.getElementById('customURL');
    const linkURL = document.getElementById('linkURL');
    
    if (linkTypeCategory.checked) {
        linkURL.value = categorySelect.value;
    } else if (linkTypeCustom.checked) {
        linkURL.value = customURL.value;
    }
}

function openAddModal() {
    try {
        console.log('openAddModal called');
        
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const carouselID = document.getElementById('carouselID');
        const carouselForm = document.getElementById('carouselForm');
        const aktif = document.getElementById('aktif');
        const carouselModal = document.getElementById('carouselModal');
        const linkTypeCategory = document.getElementById('linkTypeCategory');
        const categorySelect = document.getElementById('categorySelect');
        const customURL = document.getElementById('customURL');
        
        if (!modalTitle || !formAction || !carouselID || !carouselForm || !aktif || !carouselModal) {
            console.error('Required elements not found');
            return;
        }
        
        modalTitle.textContent = 'Yeni Carousel Ekle';
        formAction.value = 'add';
        carouselID.value = '';
        carouselForm.reset();
        aktif.checked = true;
        
        // Reset link options
        linkTypeCategory.checked = true;
        categorySelect.disabled = false;
        customURL.disabled = true;
        categorySelect.value = '';
        customURL.value = '';
        
        resetUploadArea();
        carouselModal.style.display = 'flex';
        console.log('Modal should be visible now');
    } catch (error) {
        console.error('Error in openAddModal:', error);
        alert('Modal açılırken hata oluştu: ' + error.message);
    }
}

function editCarousel(carousel) {
    try {
        console.log('editCarousel called with:', carousel);
        
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const carouselID = document.getElementById('carouselID');
        const baslik = document.getElementById('baslik');
        const aciklama = document.getElementById('aciklama');
        const resimURL = document.getElementById('resimURL');
        const sira = document.getElementById('sira');
        const aktif = document.getElementById('aktif');
        const carouselModal = document.getElementById('carouselModal');
        const linkTypeCategory = document.getElementById('linkTypeCategory');
        const linkTypeCustom = document.getElementById('linkTypeCustom');
        const categorySelect = document.getElementById('categorySelect');
        const customURL = document.getElementById('customURL');
        const linkURL = document.getElementById('linkURL');
        
        if (!modalTitle || !formAction || !carouselID || !baslik || !aciklama || !resimURL || !sira || !aktif || !carouselModal) {
            console.error('Required elements not found for edit');
            return;
        }
        
        modalTitle.textContent = 'Carousel Düzenle';
        formAction.value = 'update';
        carouselID.value = carousel.carouselID;
        baslik.value = carousel.baslik || '';
        aciklama.value = carousel.aciklama || '';
        resimURL.value = carousel.resimURL;
        sira.value = carousel.sira;
        aktif.checked = carousel.aktif == 1;
        
        // Handle link URL
        const currentLink = carousel.linkURL || '';
        linkURL.value = currentLink;
        
        // Check if it's a category link or custom URL
        if (currentLink.includes('category.php?id=') || currentLink === 'index.php') {
            linkTypeCategory.checked = true;
            linkTypeCustom.checked = false;
            categorySelect.disabled = false;
            customURL.disabled = true;
            categorySelect.value = currentLink;
            customURL.value = '';
        } else {
            linkTypeCategory.checked = false;
            linkTypeCustom.checked = true;
            categorySelect.disabled = true;
            customURL.disabled = false;
            categorySelect.value = '';
            customURL.value = currentLink;
        }
        
        resetUploadArea();
        carouselModal.style.display = 'flex';
        console.log('Edit modal should be visible now');
    } catch (error) {
        console.error('Error in editCarousel:', error);
        alert('Düzenleme modalı açılırken hata oluştu: ' + error.message);
    }
}

function deleteCarousel(carouselID) {
    try {
        console.log('deleteCarousel called with ID:', carouselID);
        const deleteCarouselID = document.getElementById('deleteCarouselID');
        const deleteModal = document.getElementById('deleteModal');
        
        if (!deleteCarouselID || !deleteModal) {
            console.error('Delete modal elements not found');
            return;
        }
        
        deleteCarouselID.value = carouselID;
        deleteModal.style.display = 'flex';
    } catch (error) {
        console.error('Error in deleteCarousel:', error);
        alert('Silme modalı açılırken hata oluştu: ' + error.message);
    }
}

function closeModal() {
    try {
        const carouselModal = document.getElementById('carouselModal');
        if (carouselModal) {
            carouselModal.style.display = 'none';
        }
    } catch (error) {
        console.error('Error in closeModal:', error);
    }
}

function closeDeleteModal() {
    try {
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.style.display = 'none';
        }
    } catch (error) {
        console.error('Error in closeDeleteModal:', error);
    }
}

function validateForm() {
    const resimURL = document.getElementById('resimURL');
    
    console.log('Validating form...');
    console.log('resimURL element:', resimURL);
    console.log('resimURL value:', resimURL ? resimURL.value : 'element not found');
    
    if (!resimURL) {
        alert('Resim URL alanı bulunamadı!');
        return false;
    }
    
    const urlValue = resimURL.value.trim();
    console.log('Trimmed URL value:', urlValue);
    
    if (!urlValue) {
        alert('Lütfen bir resim URL\'si girin veya resim yükleyin.');
        resimURL.focus();
        return false;
    }
    
    console.log('Form validation passed');
    return true;
}

// Modal dışına tıklayınca kapatma
window.onclick = function(event) {
    const carouselModal = document.getElementById('carouselModal');
    const deleteModal = document.getElementById('deleteModal');
    if (event.target == carouselModal) {
        closeModal();
    }
    if (event.target == deleteModal) {
        closeDeleteModal();
    }
}

// Image upload functionality
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const imageUpload = document.getElementById('imageUpload');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const resimURL = document.getElementById('resimURL');

    // Link URL handling
    const linkTypeCategory = document.getElementById('linkTypeCategory');
    const linkTypeCustom = document.getElementById('linkTypeCustom');
    const categorySelect = document.getElementById('categorySelect');
    const customURL = document.getElementById('customURL');

    if (!uploadArea || !imageUpload || !uploadProgress || !progressFill || !progressText || !resimURL) {
        console.error('Upload elements not found');
        return;
    }

    // Handle radio button changes
    if (linkTypeCategory) {
        linkTypeCategory.addEventListener('change', function() {
            if (this.checked) {
                categorySelect.disabled = false;
                customURL.disabled = true;
                customURL.value = '';
                updateLinkURL();
            }
        });
    }

    if (linkTypeCustom) {
        linkTypeCustom.addEventListener('change', function() {
            if (this.checked) {
                categorySelect.disabled = true;
                customURL.disabled = false;
                categorySelect.value = '';
                updateLinkURL();
            }
        });
    }

    // Handle category select change
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            if (linkTypeCategory.checked) {
                updateLinkURL();
            }
        });
    }

    // Handle custom URL input
    if (customURL) {
        customURL.addEventListener('input', function() {
            if (linkTypeCustom.checked) {
                updateLinkURL();
            }
        });
    }

    // Handle form submission
    const carouselForm = document.getElementById('carouselForm');
    if (carouselForm) {
        carouselForm.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            if (!validateForm()) {
                console.log('Validation failed, preventing submit');
                e.preventDefault();
                return false;
            }
            console.log('Validation passed, allowing submit');
        });
    }

    // Click to upload
    uploadArea.addEventListener('click', function() {
        imageUpload.click();
    });

    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('drag-over');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload(files[0]);
        }
    });

    // File input change
    imageUpload.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            handleFileUpload(e.target.files[0]);
        }
    });

    function handleFileUpload(file) {
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir.');
            return;
        }

        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('Dosya boyutu çok büyük. Maksimum 5MB olmalıdır.');
            return;
        }

        // Show progress
        uploadProgress.style.display = 'block';
        uploadArea.style.display = 'none';

        // Create FormData
        const formData = new FormData();
        formData.append('carousel_image', file);

        // Upload file
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressFill.style.width = percentComplete + '%';
                progressText.textContent = Math.round(percentComplete) + '% yüklendi';
            }
        });

        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        console.log('Upload successful, setting URL:', response.url);
                        resimURL.value = response.url;
                        
                        // Trigger multiple events to ensure the field is recognized as filled
                        resimURL.dispatchEvent(new Event('input', { bubbles: true }));
                        resimURL.dispatchEvent(new Event('change', { bubbles: true }));
                        
                        // Visual feedback
                        resimURL.style.backgroundColor = '#d1fae5';
                        setTimeout(() => {
                            resimURL.style.backgroundColor = '';
                        }, 2000);
                        
                        progressText.textContent = 'Yükleme tamamlandı!';
                        console.log('URL field value after upload:', resimURL.value);
                        
                        setTimeout(function() {
                            uploadProgress.style.display = 'none';
                            uploadArea.style.display = 'block';
                            progressFill.style.width = '0%';
                        }, 1000);
                    } else {
                        alert('Yükleme hatası: ' + response.message);
                        resetUploadLocal();
                    }
                } catch (e) {
                    alert('Sunucu yanıtı işlenirken hata oluştu.');
                    resetUploadLocal();
                }
            } else {
                alert('Yükleme başarısız. Lütfen tekrar deneyin.');
                resetUploadLocal();
            }
        });

        xhr.addEventListener('error', function() {
            alert('Yükleme sırasında hata oluştu.');
            resetUploadLocal();
        });

        xhr.open('POST', 'carousel-upload.php');
        xhr.send(formData);
    }

    function resetUploadLocal() {
        uploadProgress.style.display = 'none';
        uploadArea.style.display = 'block';
        progressFill.style.width = '0%';
        progressText.textContent = 'Yükleniyor...';
    }
});
</script>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}

.modal[style*="flex"] {
    display: flex !important;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
}

.modal-close:hover {
    color: #374151;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.form-control {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 0.875rem;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-text {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 0.5rem;
}

.alert {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.alert-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #f9fafb;
}

.upload-area:hover {
    border-color: #3b82f6;
    background-color: #eff6ff;
}

.upload-area.drag-over {
    border-color: #3b82f6;
    background-color: #dbeafe;
}

.upload-area i {
    font-size: 2rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.upload-area p {
    margin: 0;
    color: #6b7280;
    font-size: 0.875rem;
}

.upload-area small {
    display: block;
    margin-top: 0.5rem;
    color: #9ca3af;
    font-size: 0.75rem;
}

#uploadProgress {
    text-align: center;
    padding: 1rem;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background-color: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-fill {
    height: 100%;
    background-color: #3b82f6;
    transition: width 0.3s ease;
    width: 0%;
}

#progressText {
    font-size: 0.875rem;
    color: #6b7280;
}

.link-options {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 1rem;
    background-color: #f9fafb;
}

.link-options .form-group {
    margin-bottom: 1rem;
}

.link-options .form-group:last-child {
    margin-bottom: 0;
}

.link-options label {
    display: flex;
    align-items: center;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
}

.link-options input[type="radio"] {
    margin-right: 0.5rem;
}

.link-options .form-control:disabled {
    background-color: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
}
</style>

<?php include 'footer.php'; ?> 