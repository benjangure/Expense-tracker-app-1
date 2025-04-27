<?php
session_start();
require_once "config.php";

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = "";

// Check if admin exists, if not create one
checkAndCreateAdmin($conn);

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $_SESSION['error'] = "Please enter username or email.";
        header("location: login.php");
        exit;
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $_SESSION['error'] = "Please enter your password.";
        header("location: login.php");
        exit;
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT user_id, username, email, password, is_admin FROM users WHERE username = ? OR email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ss", $username, $username);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if username exists, if yes then verify password
                if ($stmt->num_rows == 1) {                    
                    // Bind result variables
                    $stmt->bind_result($id, $username, $email, $hashed_password, $is_admin);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, so start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["is_admin"] = $is_admin;
                            
                            // Remember me functionality
                            if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
                                $token = bin2hex(random_bytes(32));
                                setcookie('remember_token', $token, time() + (86400 * 30), "/"); // 30 days
                                
                                // Store token in database
                                $update_sql = "UPDATE users SET remember_token = ? WHERE user_id = ?";
                                if ($update_stmt = $conn->prepare($update_sql)) {
                                    $update_stmt->bind_param("si", $token, $id);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                }
                            }
                            
                            // Redirect user based on admin status
                            if ($is_admin) {
                                header("location: admin/dashboard.php");
                            } else {
                                header("location: dashboard.php");
                            }
                        } else {
                            // Password is not valid
                            $_SESSION['error'] = "Invalid username or password.";
                            header("location: login.php");
                        }
                    }
                } else {
                    // Username doesn't exist
                    $_SESSION['error'] = "Invalid username or password.";
                    header("location: login.php");
                }
            } else {
                $_SESSION['error'] = "Oops! Something went wrong. Please try again later.";
                header("location: login.php");
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}

/**
 * Function to check if admin exists and create one if not
 */
function checkAndCreateAdmin($conn) {
    // First, check if 'is_admin' column exists in users table
    $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
    
    // If column doesn't exist, add it
    if ($columnCheck->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
    }
    
    // Check if any admin exists
    $adminCheck = $conn->query("SELECT user_id FROM users WHERE is_admin = 1 LIMIT 1");
    
    // If no admin exists, create one
    if ($adminCheck->num_rows == 0) {
        // Default admin credentials
        $admin_username = "admin";
        $admin_email = "admin@wallstreet.com";
        $admin_password = "Admin@123"; 
        
        // Hash the password
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        
        // Insert admin user
        $sql = "INSERT INTO users (username, email, password, is_admin, first_name, last_name) 
                VALUES (?, ?, ?, 1, 'Admin', 'User')";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sss", $admin_username, $admin_email, $hashed_password);
            $stmt->execute();
            $stmt->close();
            
            // Log the creation of admin account
            error_log("Admin account created with username: admin and email: admin@example.com");
        }
    }
}
?>