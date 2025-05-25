// Common authentication functions
$(document).ready(function() {
    // Clear browser cache on page load to avoid session issues
    if (performance.navigation.type == 1) { // If page is refreshed
        // Clear localStorage items related to registration
        localStorage.removeItem('last_register_token');
        console.log('Cleared registration tokens on page refresh');
    }
    
    // Remove cookie-based registration success toast - will be handled by header.php
    
    // Handle login form submission via AJAX
    $('#login-form').submit(function(e) {
        e.preventDefault();
        
        // Hide previous error messages
        $('#login-error-container').hide();
        
        const email = $('#login-email').val();
        const password = $('#login-password').val();
        
        // Validate form
        if (!email || !password) {
            $('#login-error-message').text('Lütfen e-posta ve şifrenizi giriniz');
            $('#login-error-container').show();
            return false;
        }
        
        // Submit login via AJAX
        $.ajax({
            url: 'login-handler.php',
            type: 'POST',
            data: {
                email: email,
                password: password,
                action: 'login'
            },
            dataType: 'json',
            success: function(response) {
                console.log('Login response:', response);
                if (response.success) {
                    // Show success message if showToast function exists
                    if (typeof showToast === 'function') {
                        showToast(response.message, 'success');
                    } else {
                        console.log('Success:', response.message);
                    }
                    
                    // Hide login modal
                    $('#login-modal').hide();
                    
                    // Reload page after successful login
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error in the modal
                    $('#login-error-message').text(response.message);
                    $('#login-error-container').show();
                    
                    // Also show as toast if function exists
                    if (typeof showToast === 'function') {
                        showToast(response.message, 'error');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Login error:', error);
                console.error('Response:', xhr.responseText);
                
                // Show a generic error message
                $('#login-error-message').text('Giriş işlemi sırasında bir hata oluştu');
                $('#login-error-container').show();
                
                if (typeof showToast === 'function') {
                    showToast('Giriş işlemi sırasında bir hata oluştu', 'error');
                }
            }
        });
        
        return false;
    });
    
    // Handle registration form submission via AJAX
    $('#register-form').submit(function(e) {
        e.preventDefault();
        
        // Clear all existing toasts
        if (typeof clearAllToasts === 'function') {
            clearAllToasts();
        }
        
        // Reset any previous error states and hide messages
        $('.alert').hide();
        $('#register-error-container').hide();
        $('#register-email').removeClass('error-input');
        
        // Basic client-side validation
        const email = $('#register-form [name="email"]').val().trim();
        const ad = $('#register-form [name="ad"]').val().trim();
        const soyad = $('#register-form [name="soyad"]').val().trim();
        const password = $('#register-form [name="password"]').val().trim();
        const telefon = $('#register-form [name="telefon"]').val().trim();
        
        // Simple validation
        if (!email || !ad || !soyad || !password || !telefon) {
            $('#register-error-message').text('Lütfen tüm alanları doldurun');
            $('#register-error-container').show();
            return false;
        }
        
        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            $('#register-error-message').text('Geçerli bir e-posta adresi giriniz');
            $('#register-error-container').show();
            $('#register-email').addClass('error-input').focus();
            return false;
        }
        
        // Disable submit button to prevent double submissions
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('İşleniyor...');
        
        // Get form data for AJAX
        const formData = $(this).serialize() + '&action=register';
        
        // Submit registration via AJAX
        $.ajax({
            url: 'login-handler.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            cache: false,
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            },
            success: function(response) {
                console.log('Registration response:', response);
                
                if (response.success) {
                    // Show success toast before redirecting
                    if (typeof showToast === 'function') {
                        showToast(response.message, 'success');
                    }
                    
                    // First hide registration modal
                    $('#register-modal').hide();
                    
                    // Clean up any error containers that might be showing
                    $('#register-error-container').hide();
                    $('.alert').hide();
                    
                    // Clear form data to prevent resubmission
                    $('#register-form')[0].reset();
                    
                    // Forcefully close all modals
                    $('.modal').hide();
                    
                    // Just reload the current page instead of redirecting to homepage
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000); // Show toast for 2 seconds then reload
                } else {
                    // Re-enable submit button on error
                    submitBtn.prop('disabled', false).text('Üye Ol');
                    
                    // Show error in the modal only - no toast to prevent duplicates
                    $('#register-error-message').text(response.message);
                    $('#register-error-container').show();
                    
                    // Don't show toast for registration errors to prevent duplicates
                    // if (typeof showToast === 'function') {
                    //     showToast(response.message, 'error');
                    // }
                }
            },
            error: function(xhr, status, error) {
                // Re-enable submit button on error
                submitBtn.prop('disabled', false).text('Üye Ol');
                
                console.error('Registration error:', error);
                console.error('Response:', xhr.responseText);
                
                // Try to parse the response to get more meaningful error
                let errorMessage = 'Kayıt işlemi sırasında bir hata oluştu';
                try {
                    const jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse && jsonResponse.message) {
                        errorMessage = jsonResponse.message;
                    }
                } catch (e) {
                    // If we can't parse JSON, use default error message
                }
                
                // Show error in the modal
                $('#register-error-message').text(errorMessage);
                $('#register-error-container').show();
                
                // Don't show toast for registration errors to prevent duplicates
                // if (typeof showToast === 'function') {
                //     showToast(errorMessage, 'error');
                // }
            }
        });
        
        return false;
    });
    
    // Modal handlers for login/register
    $('#close-login').click(function() {
        $('#login-modal').hide();
    });
    
    $('#open-register').click(function() {
        $('#login-modal').hide();
        // Ekranın üst kısmında göster
        $('#register-modal').css('top', '0').show();
        window.scrollTo(0, 0); // Sayfayı en üste kaydır
    });
    
    $('#close-register').click(function() {
        $('#register-modal').hide();
    });
    
    $('#open-login2').click(function() {
        $('#register-modal').hide();
        $('#login-modal').show();
    });
    
    // Account button events for login/register modals
    $('#open-login, #account-btn:not(.logged-in)').click(function() {
        $('#login-modal').show();
    });
    
    // Cart login trigger - important: this needs to be direct and prevent any navigation
    $('#cart-login-trigger').click(function(e) {
        console.log("Cart login trigger clicked"); // Debug
        e.preventDefault();
        e.stopPropagation();
        $('#login-modal').show();
        return false;
    });
    
    // Open login modal when a specific URL hash is present
    if (window.location.hash === '#login') {
        $('#login-modal').show();
    } else if (window.location.hash === '#register') {
        // Ekranın üst kısmında göster
        $('#register-modal').css('top', '0').show();
        window.scrollTo(0, 0); // Sayfayı en üste kaydır
    }
    
    // Close modals when clicking outside
    $(window).click(function(event) {
        if ($(event.target).hasClass('modal')) {
            $('.modal').hide();
        }
    });
    
    // Basic toast function if not already defined
    if (typeof window.showToast !== 'function') {
        window.showToast = function(message, type = 'success') {
            // Clear any existing toast with the same message and type to prevent duplicates
            clearExistingToasts(message, type);
            
            console.log("Showing toast:", message, type);
            
            // Create toast container if it doesn't exist
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container';
                document.body.appendChild(container);
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.setAttribute('data-message', message);
            toast.setAttribute('data-type', type);
            
            // Create icon based on type
            const icon = document.createElement('div');
            icon.className = 'toast-icon';
            icon.innerHTML = type === 'success' ? '✓' : '!';
            
            // Create content
            const content = document.createElement('div');
            content.className = 'toast-content';
            content.innerHTML = message;
            
            // Create close button
            const closeBtn = document.createElement('button');
            closeBtn.className = 'toast-close';
            closeBtn.innerHTML = '×';
            closeBtn.onclick = () => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
            };
            
            // Assemble toast
            toast.appendChild(icon);
            toast.appendChild(content);
            toast.appendChild(closeBtn);
            container.appendChild(toast);
            
            // Show toast with a slight delay
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.remove();
                        }
                    }, 300);
                }
            }, 5000);
        };
        
        // Helper function to clear existing toasts
        function clearExistingToasts(message, type) {
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => {
                const toastMessage = toast.getAttribute('data-message');
                const toastType = toast.getAttribute('data-type');
                if (toastMessage === message && toastType === type) {
                    toast.remove();
                }
            });
        }
    }
    
    // Define clearAllToasts if not already defined
    if (typeof window.clearAllToasts !== 'function') {
        window.clearAllToasts = function() {
            const container = document.querySelector('.toast-container');
            if (container) {
                while (container.firstChild) {
                    container.removeChild(container.firstChild);
                }
            }
        };
    }
});

// Function to generate a unique token for request tracking
function generateUniqueToken() {
    return 'req_' + Math.random().toString(36).substring(2, 15) + 
           Math.random().toString(36).substring(2, 15) + 
           '_' + new Date().getTime();
} 