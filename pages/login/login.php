<?php
session_start();

// Force logout - Clear all session data when visiting login page
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // User is logged in, log them out
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    // Start fresh session
    session_start();
    $_SESSION['logout_message'] = 'You have been logged out. Please log in again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Login - LITODA</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/4c7e22a859.js" crossorigin="anonymous"></script>
    
    <!-- Main Login CSS -->
    <link rel="stylesheet" href="../../assets/css/login/login.css">
    
    <style>
        /* Inline responsive styles for immediate effect */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Poppins", sans-serif;
            background: #1a1a1a;
            background-image: url('../../assets/images/login_bg.png') !important;
            background-size: cover !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
            background-attachment: fixed !important;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-x: hidden;
        }
        
        .login-container {
            background: rgba(250, 250, 250, 0.95);
            backdrop-filter: blur(10px);
            border: 3px solid #10b981;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .logo {
            width: 120px !important;
            height: 120px !important;
            background-image: url('../../assets/images/logo1.png') !important;
            background-size: contain !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            margin: 0 auto 15px !important;
        }

        .welcome-text h2 {
            color: #065f46;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: 2px;
            text-align: center;
        }

        .welcome-text p {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            line-height: 1.4;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #10b981;
            border-radius: 12px;
            font-size: 15px;
            font-family: "Poppins", sans-serif;
        }

        .login-button {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            font-family: "Poppins", sans-serif;
            min-height: 48px;
        }

        /* Tablet (Portrait) - 768px and below */
        @media screen and (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .login-container {
                max-width: 400px;
                padding: 30px 25px;
                border-width: 2px;
            }
            
            .logo {
                width: 100px !important;
                height: 100px !important;
            }
            
            .welcome-text h2 {
                font-size: 28px;
            }
            
            .welcome-text p {
                font-size: 13px;
            }
        }

        /* Mobile (Large) - 600px and below */
        @media screen and (max-width: 600px) {
            body {
                padding: 12px;
            }
            
            .login-container {
                padding: 25px 20px;
                border-radius: 16px;
            }
            
            .logo {
                width: 85px !important;
                height: 85px !important;
            }
            
            .welcome-text h2 {
                font-size: 24px;
            }
            
            .welcome-text p {
                font-size: 11px;
            }
            
            .form-input {
                padding: 11px 13px;
                font-size: 14px;
            }
            
            .login-button {
                padding: 13px;
                font-size: 14px;
            }
        }

        /* Mobile (Medium) - 480px and below */
        @media screen and (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .login-container {
                padding: 22px 18px;
                border-radius: 12px;
            }
            
            .logo {
                width: 80px !important;
                height: 80px !important;
            }
            
            .welcome-text h2 {
                font-size: 22px;
            }
            
            .welcome-text p {
                font-size: 10px;
            }
            
            .form-input {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .login-button {
                padding: 12px;
                font-size: 13px;
            }
        }

        /* Mobile (Small) - 375px and below */
        @media screen and (max-width: 375px) {
            body {
                padding: 8px;
            }
            
            .login-container {
                padding: 20px 15px;
                border-radius: 10px;
            }
            
            .logo {
                width: 70px !important;
                height: 70px !important;
            }
            
            .welcome-text h2 {
                font-size: 20px;
            }
            
            .welcome-text p {
                font-size: 9px;
            }
            
            .form-input {
                padding: 9px 11px;
                font-size: 12px;
                /* Prevent iOS zoom */
                font-size: 16px;
            }
            
            .login-button {
                padding: 11px;
                font-size: 12px;
                min-height: 44px;
            }
        }

        /* Landscape Mode */
        @media screen and (max-height: 500px) and (orientation: landscape) {
            body {
                padding: 10px 20px;
            }
            
            .login-container {
                padding: 20px 25px;
                max-height: 90vh;
                overflow-y: auto;
            }
            
            .logo {
                width: 70px !important;
                height: 70px !important;
            }
            
            .welcome-text h2 {
                font-size: 20px;
            }
            
            .welcome-text p {
                font-size: 10px;
            }
        }

        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .form-input {
                font-size: 16px !important; /* Prevents iOS zoom */
            }
            
            .login-button {
                min-height: 44px;
                min-width: 44px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo"></div>
            <div class="welcome-text">
                <h2>LITODA</h2>
                <p>Libas Tricycle Operators and Drivers Association</p>
            </div>
        </div>
        
        <?php if (isset($_SESSION['login_error'])): ?>
            <div class="error-message">
                <?php 
                echo htmlspecialchars($_SESSION['login_error']); 
                unset($_SESSION['login_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['logout_message'])): ?>
            <div class="error-message" style="background: #dbeafe; color: #1e40af; border-color: #3b82f6;">
                <?php 
                echo htmlspecialchars($_SESSION['logout_message']); 
                unset($_SESSION['logout_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="../../api/auth/login.php" id="loginForm">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input 
                    type="text" 
                    id="username"
                    name="username" 
                    class="form-input" 
                    placeholder="Enter your username"
                    value="<?php echo isset($_SESSION['old_username']) ? htmlspecialchars($_SESSION['old_username']) : ''; ?>"
                    required
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-container">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                        <i class="fa-regular fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="login-button" id="loginBtn">
                <span class="button-text">Log In</span>
            </button>
        </form>
    </div>
    
    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form submission handling
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        
        loginForm.addEventListener('submit', function(e) {
            loginBtn.disabled = true;
            loginBtn.classList.add('loading');
            loginBtn.querySelector('.button-text').textContent = 'Logging in';
        });

        // Prevent double submission
        let isSubmitting = false;
        loginForm.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
        });

        // Auto-hide error messages after 5 seconds
        const errorMessage = document.querySelector('.error-message');
        if (errorMessage) {
            setTimeout(() => {
                errorMessage.style.opacity = '0';
                errorMessage.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 500);
            }, 5000);
        }

        // Add keyboard accessibility
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.activeElement.classList.contains('password-toggle')) {
                e.preventDefault();
                togglePassword();
            }
        });
    </script>
    
    <script src="../../assets/js/login/login.js"></script>
</body>
</html>
<?php
// Clear any old form data
if (isset($_SESSION['old_username'])) {
    unset($_SESSION['old_username']);
}
?>