<?php
session_start();
require_once "config.php";

// Define variables and initialize with empty values
$username = $password = $confirm_password = $email = $first_name = $last_name = "";
$username_err = $password_err = $confirm_password_err = $email_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $_SESSION['error'] = "Please enter a username.";
        header("location: register.php");
        exit;
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $_SESSION['error'] = "Username can only contain letters, numbers, and underscores.";
        header("location: register.php");
        exit;
    } else {
        // Prepare a select statement
        $sql = "SELECT user_id FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $_SESSION['error'] = "This username is already taken.";
                    header("location: register.php");
                    exit;
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                $_SESSION['error'] = "Oops! Something went wrong. Please try again later.";
                header("location: register.php");
                exit;
            }

            // Close statement
            $stmt->close();
        }
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $_SESSION['error'] = "Please enter an email.";
        header("location: register.php");
        exit;
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header("location: register.php");
        exit;
    } else {
        // Prepare a select statement
        $sql = "SELECT user_id FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_email);
            
            // Set parameters
            $param_email = trim($_POST["email"]);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $_SESSION['error'] = "This email is already taken.";
                    header("location: register.php");
                    exit;
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                $_SESSION['error'] = "Oops! Something went wrong. Please try again later.";
                header("location: register.php");
                exit;
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $_SESSION['error'] = "Please enter a password.";
        header("location: register.php");
        exit;
    } elseif (strlen(trim($_POST["password"])) < 8) {
        $_SESSION['error'] = "Password must have at least 8 characters.";
        header("location: register.php");
        exit;
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*#?&]{8,}$/', trim($_POST["password"]))) {
        $_SESSION['error'] = "Password must contain at least one letter and one number.";
        header("location: register.php");
        exit;
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $_SESSION['error'] = "Please confirm password.";
        header("location: register.php");
        exit;
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if ($password != $confirm_password) {
            $_SESSION['error'] = "Password did not match.";
            header("location: register.php");
            exit;
        }
    }
    
    // Get first and last name
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    
    // Check if terms are accepted
    if (!isset($_POST["terms"])) {
        $_SESSION['error'] = "You must agree to the terms and conditions.";
        header("location: register.php");
        exit;
    }
    
    // Check input errors before inserting in database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($email_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, email, password, first_name, last_name) VALUES (?, ?, ?, ?, ?)";
         
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("sssss", $param_username, $param_email, $param_password, $param_first_name, $param_last_name);
            
            // Set parameters
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_first_name = $first_name;
            $param_last_name = $last_name;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Create default income and expense categories for the new user
                $user_id = $stmt->insert_id;
                createDefaultCategories($conn, $user_id);
                
                // Redirect to login page with success message
                $_SESSION['success'] = "Your account has been created successfully. You can now login.";
                header("location: login.php");
            } else {
                $_SESSION['error'] = "Oops! Something went wrong. Please try again later.";
                header("location: register.php");
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}

// Function to create default categories for a new user
function createDefaultCategories($conn, $user_id) {
    // Default income categories
    $income_categories = [
        ['Salary', 'Regular employment income'],
        ['Freelance', 'Income from freelance work'],
        ['Investments', 'Income from investments'],
        ['Other', 'Other income sources']
    ];
    
    // Default expense categories
    $expense_categories = [
        ['Housing', 'Rent, mortgage, repairs'],
        ['Transportation', 'Car payments, gas, public transit'],
        ['Food', 'Groceries, dining out'],
        ['Utilities', 'Electricity, water, internet'],
        ['Entertainment', 'Movies, subscriptions, hobbies'],
        ['Healthcare', 'Insurance, medications, doctor visits'],
        ['Education', 'Tuition, books, courses'],
        ['Shopping', 'Clothing, electronics, household items'],
        ['Personal Care', 'Haircuts, cosmetics, gym'],
        ['Debt Payments', 'Credit card, loans'],
        ['Savings', 'Emergency fund, retirement'],
        ['Other', 'Miscellaneous expenses']
    ];
    
    // Insert income categories
    $income_sql = "INSERT INTO income_categories (user_id, name, description) VALUES (?, ?, ?)";
    if ($stmt = $conn->prepare($income_sql)) {
        foreach ($income_categories as $category) {
            $stmt->bind_param("iss", $user_id, $category[0], $category[1]);
            $stmt->execute();
        }
        $stmt->close();
    }
    
    // Insert expense categories
    $expense_sql = "INSERT INTO expense_categories (user_id, name, description) VALUES (?, ?, ?)";
    if ($stmt = $conn->prepare($expense_sql)) {
        foreach ($expense_categories as $category) {
            $stmt->bind_param("iss", $user_id, $category[0], $category[1]);
            $stmt->execute();
        }
        $stmt->close();
    }
}
?>