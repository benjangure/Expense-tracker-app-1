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
$success_message = "";
$error_message = "";

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new expense
    if (isset($_POST["add_expense"])) {
        $category_id = $_POST["category_id"];
        $amount = $_POST["amount"];
        $description = $_POST["description"];
        $expense_date = $_POST["expense_date"];
        
        // Validate input
        if (empty($category_id) || empty($amount) || empty($expense_date)) {
            $error_message = "Please fill all required fields.";
        } else {
            // Prepare an insert statement
            $sql = "INSERT INTO expenses (user_id, category_id, amount, description, expense_date) VALUES (?, ?, ?, ?, ?)";
            
            if ($stmt = $conn->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bind_param("iidss", $user_id, $category_id, $amount, $description, $expense_date);
                
                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    $success_message = "Expense added successfully!";
                } else {
                    $error_message = "Something went wrong. Please try again later.";
                }
                
                // Close statement
                $stmt->close();
            }
        }
    }
    
    // Update existing expense
    if (isset($_POST["update_expense"])) {
        $expense_id = $_POST["expense_id"];
        $category_id = $_POST["category_id"];
        $amount = $_POST["amount"];
        $description = $_POST["description"];
        $expense_date = $_POST["expense_date"];
        
        // Validate input
        if (empty($category_id) || empty($amount) || empty($expense_date)) {
            $error_message = "Please fill all required fields.";
        } else {
            // Prepare an update statement
            $sql = "UPDATE expenses SET category_id = ?, amount = ?, description = ?, expense_date = ? WHERE expense_id = ? AND user_id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bind_param("idssii", $category_id, $amount, $description, $expense_date, $expense_id, $user_id);
                
                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    $success_message = "Expense updated successfully!";
                } else {
                    $error_message = "Something went wrong. Please try again later.";
                }
                
                // Close statement
                $stmt->close();
            }
        }
    }
    
    // Delete expense
    if (isset($_POST["delete_expense"])) {
        $expense_id = $_POST["expense_id"];
        
        // Prepare a delete statement
        $sql = "DELETE FROM expenses WHERE expense_id = ? AND user_id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ii", $expense_id, $user_id);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $success_message = "Expense deleted successfully!";
            } else {
                $error_message = "Something went wrong. Please try again later.";
            }
            
            // Close statement
            $stmt->close();
        }
    }
}

// Fetch expense categories
$categories = [];
$category_sql = "SELECT category_id, name FROM expense_categories WHERE user_id = ? OR user_id IS NULL ORDER BY name";
if ($stmt = $conn->prepare($category_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    $stmt->close();
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Set up filtering
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

// Build WHERE clause for filtering
$where_clause = "WHERE e.user_id = ?";
$params = [$user_id];
$param_types = "i";

if (!empty($filter_category)) {
    $where_clause .= " AND category_id = ?";
    $params[] = $filter_category;
    $param_types .= "i";
}

if ($filter_month != 'all') {
    $where_clause .= " AND MONTH(expense_date) = ?";
    $params[] = $filter_month;
    $param_types .= "s";
}

if ($filter_year != 'all') {
    $where_clause .= " AND YEAR(expense_date) = ?";
    $params[] = $filter_year;
    $param_types .= "s";
}

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM expenses e $where_clause";
$total_records = 0;

if ($stmt = $conn->prepare($count_sql)) {
    // FIX: Dynamically bind parameters using bind_param
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_records = $row['total'];
    $stmt->close();
}

$total_pages = ceil($total_records / $records_per_page);

// Fetch expenses with pagination and filtering
$expenses = [];
$expense_sql = "SELECT e.expense_id, e.category_id, e.amount, e.description, e.expense_date, 
                ec.name as category_name 
                FROM expenses e
                JOIN expense_categories ec ON e.category_id = ec.category_id
                $where_clause
                ORDER BY e.expense_date DESC, e.expense_id DESC
                LIMIT ? OFFSET ?";

if ($stmt = $conn->prepare($expense_sql)) {
    // Add pagination parameters
    $params_with_limit = $params;
    $params_with_limit[] = $records_per_page;
    $params_with_limit[] = $offset;
    $param_types_with_limit = $param_types . "ii";
    
    // FIX: Bind all parameters correctly
    $stmt->bind_param($param_types_with_limit, ...$params_with_limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    
    $stmt->close();
}

// Calculate total expenses for the current filter
$total_amount = 0;
$total_sql = "SELECT SUM(e.amount) as total FROM expenses e $where_clause";
if ($stmt = $conn->prepare($total_sql)) {
    // FIX: Dynamically bind parameters
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_amount = $row['total'] ?: 0;
    $stmt->close();
}

// Generate months for filter dropdown
$months = [
    '01' => 'January',
    '02' => 'February',
    '03' => 'March',
    '04' => 'April',
    '05' => 'May',
    '06' => 'June',
    '07' => 'July',
    '08' => 'August',
    '09' => 'September',
    '10' => 'October',
    '11' => 'November',
    '12' => 'December',
    'all' => 'All Months'
];

// Generate years for filter dropdown (current year and 4 years back)
$years = [];
$current_year = date('Y');
for ($i = 0; $i <= 4; $i++) {
    $year = $current_year - $i;
    $years[$year] = $year;
}
$years['all'] = 'All Years';

// Close connection
$conn->close();

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<!-- Main Content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Manage Expenses</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fas fa-plus me-1"></i>Add New Expense
            </button>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter Expenses</h5>
        </div>
        <div class="card-body">
            <form method="get" action="expenses.php" class="row g-3">
                <div class="col-md-3">
                    <label for="month" class="form-label">Month</label>
                    <select class="form-select" name="month" id="month">
                        <?php foreach ($months as $key => $value): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($filter_month == $key) ? 'selected' : ''; ?>>
                            <?php echo $value; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" name="year" id="year">
                        <?php foreach ($years as $key => $value): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($filter_year == $key) ? 'selected' : ''; ?>>
                            <?php echo $value; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" name="category" id="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>" <?php echo ($filter_category == $category['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filter</button>
                    <a href="expenses.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Expense Summary -->
    <div class="alert alert-info mb-4">
        <div class="d-flex justify-content-between">
            <div>
                <strong>Total Expenses:</strong> Ksh <?php echo number_format($total_amount, 2); ?>
            </div>
            <div>
                <strong>Total Records:</strong> <?php echo $total_records; ?>
            </div>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Your Expenses</h5>
        </div>
        <div class="card-body">
            <?php if (count($expenses) == 0): ?>
            <div class="text-center py-4">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <p>No expenses found for the selected filters.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="fas fa-plus me-1"></i>Add Your First Expense
                </button>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount (Ksh)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                            <td>
                                <span class="badge badge-expense text-white">
                                    <?php echo htmlspecialchars($expense['category_name']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                            <td><?php echo number_format($expense['amount'], 2); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary edit-expense" 
                                        data-id="<?php echo $expense['expense_id']; ?>"
                                        data-category="<?php echo $expense['category_id']; ?>"
                                        data-amount="<?php echo $expense['amount']; ?>"
                                        data-description="<?php echo htmlspecialchars($expense['description']); ?>"
                                        data-date="<?php echo $expense['expense_date']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-expense" 
                                        data-id="<?php echo $expense['expense_id']; ?>"
                                        data-description="<?php echo htmlspecialchars($expense['description']); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&category=<?php echo $filter_category; ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&category=<?php echo $filter_category; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&category=<?php echo $filter_category; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="expenses.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" name="category_id" id="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount (Ksh)</label>
                        <input type="number" class="form-control" name="amount" id="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="expense_date" class="form-label">Date</label>
                        <input type="date" class="form-control" name="expense_date" id="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_expense" class="btn btn-primary">Add Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editExpenseModalLabel">Edit Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="expenses.php">
                <input type="hidden" name="expense_id" id="edit_expense_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_category_id" class="form-label">Category</label>
                        <select class="form-select" name="category_id" id="edit_category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_amount" class="form-label">Amount (Ksh)</label>
                        <input type="number" class="form-control" name="amount" id="edit_amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_expense_date" class="form-label">Date</label>
                        <input type="date" class="form-control" name="expense_date" id="edit_expense_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_expense" class="btn btn-primary">Update Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Expense Modal -->
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-labelledby="deleteExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteExpenseModalLabel">Delete Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this expense: <span id="delete_expense_description"></span>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <form method="post" action="expenses.php">
                <input type="hidden" name="expense_id" id="delete_expense_id">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_expense" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
    // Handle Edit Expense Modal
    document.querySelectorAll('.edit-expense').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const category = this.getAttribute('data-category');
            const amount = this.getAttribute('data-amount');
            const description = this.getAttribute('data-description');
            const date = this.getAttribute('data-date');
            
            document.getElementById('edit_expense_id').value = id;
            document.getElementById('edit_category_id').value = category;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_expense_date').value = date;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('editExpenseModal'));
            modal.show();
        });
    });
    
    // Handle Delete Expense Modal
    document.querySelectorAll('.delete-expense').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const description = this.getAttribute('data-description');
            
            document.getElementById('delete_expense_id').value = id;
            document.getElementById('delete_expense_description').textContent = description;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('deleteExpenseModal'));
            modal.show();
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
</script>
</body>
</html>