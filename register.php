<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wall Street Budget - Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0a2e52;
            --secondary-color: #1a5dad;
            --accent-color: #4d96ff;
            --light-accent: #a5c8ff;
            --text-color: #f8f9fa;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .register-container {
            max-width: 550px;
            margin: 80px auto;
        }
        
        .register-card {
            border-radius: 15px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background-color: white;
        }
        
        .register-header {
            background-color: var(--primary-color);
            color: var(--text-color);
            padding: 30px;
            text-align: center;
            font-weight: 600;
        }
        
        .register-body {
            padding: 30px;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(77, 150, 255, 0.25);
        }
        
        .register-footer {
            text-align: center;
            padding: 15px;
            border-top: 1px solid #eee;
        }
        
        .brand-name {
            font-weight: 700;
            color: var(--accent-color);
        }
        
        .input-group-text {
            background-color: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="container register-container">
        <div class="register-card">
            <div class="register-header">
                <h2><i class="fas fa-chart-line me-2"></i>Wall Street Budget</h2>
                <p class="mb-0">Create Your Account</p>
            </div>
            <div class="register-body">
                <?php
                session_start();
                
                // Check if there's an error message to display
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
                    unset($_SESSION['error']);
                }
                ?>
                <form action="register_process.php" method="post" id="registerForm">
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="first_name" class="form-label">First Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="form-text">Password must be at least 8 characters with at least one letter and one number</div>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">I agree to the <a href="#">Terms and Conditions</a></label>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                    </div>
                </form>
            </div>
            <div class="register-footer">
                <p>Already have an account? <a href="login.php">Sign In</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('registerForm').addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match!');
            }
            
            // Basic password validation
            const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*#?&]{8,}$/;
            if (!passwordRegex.test(password)) {
                event.preventDefault();
                alert('Password must be at least 8 characters with at least one letter and one number!');
            }
        });
    </script>
</body>
</html>