<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wall Street Budget - Login</title>
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
        
        .login-container {
            max-width: 450px;
            margin: 100px auto;
        }
        
        .login-card {
            border-radius: 15px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background-color: white;
        }
        
        .login-header {
            background-color: var(--primary-color);
            color: var(--text-color);
            padding: 30px;
            text-align: center;
            font-weight: 600;
        }
        
        .login-body {
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
        
        .login-footer {
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
    <div class="container login-container">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="fas fa-chart-line me-2"></i>Wall Street Budget</h2>
                <p class="mb-0">Financial Planning Made Simple</p>
            </div>
            <div class="login-body">
                <?php
                session_start();
                
                // Check if there's an error message to display
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
                    unset($_SESSION['error']);
                }
                
                // Check if there's a success message to display
                if (isset($_SESSION['success'])) {
                    echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
                    unset($_SESSION['success']);
                }
                ?>
                <form action="login_process.php" method="post">
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" name="username" placeholder="Username or Email" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" placeholder="Password" required>
                        </div>
                    </div>
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                </form>
            </div>
            <div class="login-footer">
                <p>Don't have an account? <a href="register.php">Sign Up</a></p>
                <!-- <p><a href="forgot_password.php">Forgot password?</a></p> -->
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>