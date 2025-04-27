<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Include config file
require_once "config.php";

$user_id = $_SESSION["user_id"];

// Define variables and initialize with empty values
$username = $email = $first_name = $last_name = "";
$username_err = $email_err = $general_err = $success_msg = "";

// Get user data
$sql = "SELECT username, email, first_name, last_name FROM users WHERE user_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $stmt->store_result();
        
        if ($stmt->num_rows == 1) {
            $stmt->bind_result($username, $email, $first_name, $last_name);
            $stmt->fetch();
        } else {
            // Redirect to login page if user not found
            header("location: login.php");
            exit;
        }
    } else {
        $general_err = "Oops! Something went wrong. Please try again later.";
    }
    
    $stmt->close();
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check which form was submitted
    if (isset($_POST["update_profile"])) {
        // Validate first name (optional)
        $input_first_name = trim($_POST["first_name"]);
        if (strlen($input_first_name) > 50) {
            $general_err = "First name must be less than 50 characters.";
        } else {
            $first_name = $input_first_name;
        }
        
        // Validate last name (optional)
        $input_last_name = trim($_POST["last_name"]);
        if (strlen($input_last_name) > 50) {
            $general_err = "Last name must be less than 50 characters.";
        } else {
            $last_name = $input_last_name;
        }
        
        // Validate email
        $input_email = trim($_POST["email"]);
        if (empty($input_email)) {
            $email_err = "Please enter your email.";
        } elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        } else {
            // Check if email exists
            $sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("si", $input_email, $user_id);
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows > 0) {
                        $email_err = "This email is already taken.";
                    } else {
                        $email = $input_email;
                    }
                } else {
                    $general_err = "Oops! Something went wrong. Please try again later.";
                }
                
                $stmt->close();
            }
        }
        
        // Check input errors before updating in database
        if (empty($email_err) && empty($general_err)) {
            // Prepare an update statement
            $sql = "UPDATE users SET email = ?, first_name = ?, last_name = ? WHERE user_id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssi", $email, $first_name, $last_name, $user_id);
                
                if ($stmt->execute()) {
                    $success_msg = "Profile updated successfully!";
                } else {
                    $general_err = "Oops! Something went wrong. Please try again later.";
                }
                
                $stmt->close();
            }
        }
    } elseif (isset($_POST["change_password"])) {
        // Process password change
        $current_password = trim($_POST["current_password"]);
        $new_password = trim($_POST["new_password"]);
        $confirm_password = trim($_POST["confirm_password"]);
        
        $password_err = "";
        
        // Validate current password
        if (empty($current_password)) {
            $password_err = "Please enter your current password.";
        } else {
            // Check if the current password is correct
            $sql = "SELECT password FROM users WHERE user_id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows == 1) {
                        $stmt->bind_result($hashed_password);
                        $stmt->fetch();
                        
                        if (!password_verify($current_password, $hashed_password)) {
                            $password_err = "The current password you entered is not correct.";
                        }
                    } else {
                        $password_err = "No account found.";
                    }
                } else {
                    $general_err = "Oops! Something went wrong. Please try again later.";
                }
                
                $stmt->close();
            }
        }
        
        // Validate new password
        if (empty($new_password)) {
            $password_err = "Please enter a new password.";
        } elseif (strlen($new_password) < 6) {
            $password_err = "Password must have at least 6 characters.";
        }
        
        // Validate confirm password
        if (empty($confirm_password)) {
            $password_err = "Please confirm the password.";
        } else {
            if ($new_password != $confirm_password) {
                $password_err = "Passwords do not match.";
            }
        }
        
        // Check input errors before updating the password
        if (empty($password_err) && empty($general_err)) {
            // Prepare an update statement
            $sql = "UPDATE users SET password = ? WHERE user_id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                // Hash the password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $success_msg = "Password changed successfully!";
                } else {
                    $general_err = "Oops! Something went wrong. Please try again later.";
                }
                
                $stmt->close();
            }
        } else {
            $general_err = $password_err;
        }
    }
}



include 'partials/header.php';
include 'partials/sidebar.php';
?>

<!-- Main Content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">User Profile</h1>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <?php if (!empty($general_err)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $general_err; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="profile-form">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($username); ?>" disabled>
                            <div class="form-text">Username cannot be changed.</div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="password-form">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-danger">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Summary -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Account Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-info text-white rounded-circle p-3 me-3">
                                    <i class="fas fa-calendar-alt fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-muted">Member Since</h6>
                                    <h5 class="mb-0">
                                        <?php 
                                        // Get member since date
                                        $member_since = "SELECT DATE_FORMAT(created_at, '%M %Y') as joined FROM users WHERE user_id = ?";
                                        if ($stmt = $conn->prepare($member_since)) {
                                            $stmt->bind_param("i", $user_id);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($row = $result->fetch_assoc()) {
                                                echo $row['joined'];
                                            } else {
                                                echo "Unknown";
                                            }
                                            $stmt->close();
                                        }
                                        ?>
                                    </h5>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-success text-white rounded-circle p-3 me-3">
                                    <i class="fas fa-receipt fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-muted">Total Transactions</h6>
                                    <h5 class="mb-0">
                                        <?php 
                                        // Calculate total transactions
                                        $total_transactions = "SELECT 
                                            (SELECT COUNT(*) FROM income WHERE user_id = ?) +
                                            (SELECT COUNT(*) FROM expenses WHERE user_id = ?) AS total";
                                        if ($stmt = $conn->prepare($total_transactions)) {
                                            $stmt->bind_param("ii", $user_id, $user_id);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($row = $result->fetch_assoc()) {
                                                echo number_format($row['total']);
                                            } else {
                                                echo "0";
                                            }
                                            $stmt->close();
                                        }
                                        ?>
                                    </h5>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-warning text-white rounded-circle p-3 me-3">
                                    <i class="fas fa-tasks fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-muted">Active Budgets</h6>
                                    <h5 class="mb-0">
                                        <?php 
                                        // Calculate active budgets
                                        $active_budgets = "SELECT COUNT(*) as total FROM budgets 
                                                         WHERE user_id = ? AND period_end >= CURDATE()";
                                        if ($stmt = $conn->prepare($active_budgets)) {
                                            $stmt->bind_param("i", $user_id);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($row = $result->fetch_assoc()) {
                                                echo number_format($row['total']);
                                            } else {
                                                echo "0";
                                            }
                                            $stmt->close();
                                        }
                                        ?>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Categories -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Your Income Categories</h5>
                    <a href="categories.php" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-plus me-1"></i>Manage Categories
                    </a>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php
                        // Get user's custom income categories
                        $income_categories = "SELECT name FROM income_categories WHERE user_id = ? ORDER BY name";
                        if ($stmt = $conn->prepare($income_categories)) {
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                    echo htmlspecialchars($row['name']);
                                    echo '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i></span>';
                                    echo '</li>';
                                }
                            } else {
                                echo '<li class="list-group-item text-center">No custom income categories</li>';
                            }
                            $stmt->close();
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Your Expense Categories</h5>
                    <a href="categories.php" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-plus me-1"></i>Manage Categories
                    </a>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php
                        // Get user's custom expense categories
                        $expense_categories = "SELECT name FROM expense_categories WHERE user_id = ? ORDER BY name";
                        if ($stmt = $conn->prepare($expense_categories)) {
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                    echo htmlspecialchars($row['name']);
                                    echo '<span class="badge bg-danger rounded-pill"><i class="fas fa-check"></i></span>';
                                    echo '</li>';
                                }
                            } else {
                                echo '<li class="list-group-item text-center">No custom expense categories</li>';
                            }
                            $stmt->close();
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password validation
    const passwordForm = document.getElementById('password-form');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    passwordForm.addEventListener('submit', function(event) {
        if (newPassword.value !== confirmPassword.value) {
            event.preventDefault();
            alert('New password and confirm password do not match!');
        }
    });
});
</script>
