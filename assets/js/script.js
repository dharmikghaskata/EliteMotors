// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Add smooth scrolling to all links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Image preview for file uploads
    const imageUpload = document.getElementById('carImage');
    if (imageUpload) {
        imageUpload.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                const preview = document.getElementById('imagePreview');
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            }
        });
    }

    // Initialize carousel if it exists
    const carouselElement = document.querySelector('#carouselExampleIndicators');
    if (carouselElement) {
        const carousel = new bootstrap.Carousel(carouselElement, {
            interval: 5000,
            touch: true
        });
    }

    // Back to top button
    const backToTopButton = document.getElementById('backToTop');
    if (backToTopButton) {
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.style.display = 'block';
            } else {
                backToTopButton.style.display = 'none';
            }
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});

// Function to show loading spinner
function showLoading() {
    const spinner = document.createElement('div');
    spinner.className = 'spinner-border text-primary position-fixed top-50 start-50';
    spinner.role = 'status';
    spinner.id = 'loadingSpinner';
    
    const spinnerDiv = document.createElement('div');
    spinnerDiv.className = 'position-fixed w-100 h-100 bg-dark bg-opacity-50';
    spinnerDiv.style.top = '0';
    spinnerDiv.style.left = '0';
    spinnerDiv.style.zIndex = '9999';
    spinnerDiv.style.display = 'flex';
    spinnerDiv.style.justifyContent = 'center';
    spinnerDiv.style.alignItems = 'center';
    spinnerDiv.id = 'spinnerOverlay';
    
    spinnerDiv.appendChild(spinner);
    document.body.appendChild(spinnerDiv);
}

// Function to hide loading spinner
function hideLoading() {
    const spinner = document.getElementById('loadingSpinner');
    const spinnerOverlay = document.getElementById('spinnerOverlay');
    
    if (spinner && spinnerOverlay) {
        spinnerOverlay.remove();
    }
}

// Function to show toast notifications
function showToast(type, message) {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    const toastId = 'toast-' + Date.now();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.role = 'alert';
    toast.id = toastId;
    
    const toastBody = document.createElement('div');
    toastBody.className = 'd-flex';
    
    const toastMessage = document.createElement('div');
    toastMessage.className = 'toast-body';
    toastMessage.textContent = message;
    
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'btn-close btn-close-white me-2 m-auto';
    closeButton.setAttribute('data-bs-dismiss', 'toast');
    
    toastBody.appendChild(toastMessage);
    toastBody.appendChild(closeButton);
    toast.appendChild(toastBody);
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
    });
}

// Helper function to create toast container if it doesn't exist
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '1100';
    document.body.appendChild(container);
    return container;
}

