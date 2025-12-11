function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.querySelector('.password-toggle');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleBtn.innerHTML = '<i class="fa-regular fa-eye-slash"></i>'; // Changed to innerHTML and eye-slash for "hide"
    } else {
        passwordInput.type = 'password';
        toggleBtn.innerHTML = '<i class="fa-regular fa-eye"></i>'; // Changed to innerHTML
    }
}

// Form submission with loading animation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('loginBtn');
    submitBtn.classList.add('loading');
    submitBtn.style.pointerEvents = 'none';
    
    // Remove loading state after form processes
    setTimeout(() => {
        submitBtn.classList.remove('loading');
        submitBtn.style.pointerEvents = 'auto';
    }, 2000);
});

// Auto-hide messages after 5 seconds
setTimeout(() => {
    const errorMsg = document.querySelector('.error-message');
    const successMsg = document.querySelector('.success-message');
    
    if (errorMsg && errorMsg.style.display === 'block') {
        errorMsg.style.opacity = '0';
        errorMsg.style.transition = 'opacity 0.5s ease';
        setTimeout(() => errorMsg.style.display = 'none', 500);
    }
    
    if (successMsg && successMsg.style.display === 'block') {
        successMsg.style.opacity = '0';
        successMsg.style.transition = 'opacity 0.5s ease';
        setTimeout(() => successMsg.style.display = 'none', 500);
    }
}, 5000);

// Smooth focus animations
document.querySelectorAll('.form-input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
    });
});