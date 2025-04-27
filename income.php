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
$error = "";
$success = "";

// Define variables and initialize with empty values
$category_id = $amount = $description = $income_date = "";
$category_id_err = $amount_err = $income_date_err = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if we're processing delete, edit, or add
    if (isset($_POST["delete_income"])) {
        // Process delete request
        $income_id = trim($_POST["income_id"]);
        
        // Prepare a delete statement
        $sql = "DELETE FROM income WHERE income_id = ? AND user_id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ii", $income_id, $user_id);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $success = "Income record deleted successfully!";
            } else {
                $error = "Oops! Something went wrong. Please try again later.";
            }
            
            // Close statement
            $stmt->close();
        }
    } elseif (isset($_POST["update_income"])) {
        // Process update request
        $income_id = trim($_POST["income_id"]);
        
        // Validate category
        if (empty(trim($_POST["category_id"]))) {
            $category_id_err = "Please select a category.";
        } else {
            $category_id = trim($_POST["category_id"]);
        }
        
        // Validate amount
        if (empty(trim($_POST["amount"]))) {
            $amount_err = "Please enter the amount.";
        } elseif (!is_numeric(trim($_POST["amount"])) || trim($_POST["amount"]) <= 0) {
            $amount_err = "Please enter a valid positive amount.";
        } else {
            $amount = trim($_POST["amount"]);
        }
        
        // Validate date
        if (empty(trim($_POST["income_date"]))) {
            $income_date_err = "Please select a date.";
        } else {
            $income_date = trim($_POST["income_date"]);
        }
        
        // Description is optional
        $description = trim($_POST["description"]);
        
        // Check input errors before updating in database
        if (empty($category_id_err) && empty($amount_err) && empty($income_date_err)) {
            // Prepare an update statement
            $sql = "UPDATE income SET category_id = ?, amount = ?, description = ?, income_date = ? WHERE income_id = ? AND user_id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bind_param("idssii", $category_id, $amount, $description, $income_date, $income_id, $user_id);
                
                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    $success = "Income record updated successfully!";
                } else {
                    $error = "Oops! Something went wrong. Please try again later.";
                }
                
                // Close statement
                $stmt->close();
            }
        }
    } else {
        // Process add new income request
        
        // Validate category
        if (empty(trim($_POST["category_id"]))) {
            $category_id_err = "Please select a category.";
        } else {
            $category_id = trim($_POST["category_id"]);
        }
        
        // Validate amount
        if (empty(trim($_POST["amount"]))) {
            $amount_err = "Please enter the amount.";
        } elseif (!is_numeric(trim($_POST["amount"])) || trim($_POST["amount"]) <= 0) {
            $amount_err = "Please enter a valid positive amount.";
        } else {
            $amount = trim($_POST["amount"]);
        }
        
        // Validate date
        if (empty(trim($_POST["income_date"]))) {
            $income_date_err = "Please select a date.";
        } else {
            $income_date = trim($_POST["income_date"]);
        }
        
        // Description is optional
        $description = trim($_POST["description"]);
        
        // Check input errors before inserting in database
        if (empty($category_id_err) && empty($amount_err) && empty($income_date_err)) {
            // Prepare an insert statement
            $sql = "INSERT INTO income (user_id, category_id, amount, description, income_date) VALUES (?, ?, ?, ?, ?)";
            
            if ($stmt = $conn->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bind_param("iidss", $user_id, $category_id, $amount, $description, $income_date);
                
                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    $success = "Income added successfully!";
                    // Clear form fields
                    $category_id = $amount = $description = $income_date = "";
                } else {
                    $error = "Oops! Something went wrong. Please try again later.";
                }
                
                // Close statement
                $stmt->close();
            }
        }
    }
}

// Get income categories
$categories = [];
$sql = "SELECT category_id, name FROM income_categories WHERE user_id = ? OR user_id IS NULL ORDER BY name";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    $stmt->close();
}

// Handle filter parameters
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'income_date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sorting parameters
$allowed_sort_fields = ['income_date', 'amount', 'category_id'];
$allowed_sort_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'income_date';
}

if (!in_array($sort_order, $allowed_sort_orders)) {
    $sort_order = 'DESC';
}

// Build the WHERE clause for filtering
$where_clause = "WHERE i.user_id = ?";
$params = [$user_id];
$param_types = "i";

if ($filter_month != 'all') {
    $where_clause .= " AND MONTH(i.income_date) = ?";
    $params[] = $filter_month;
    $param_types .= "s";
}

if ($filter_year != 'all') {
    $where_clause .= " AND YEAR(i.income_date) = ?";
    $params[] = $filter_year;
    $param_types .= "s";
}

if (!empty($filter_category)) {
    $where_clause .= " AND i.category_id = ?";
    $params[] = $filter_category;
    $param_types .= "i";
}

// Get income records with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM income i " . $where_clause;
$total_records = 0;

if ($stmt = $conn->prepare($count_sql)) {
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $total_records = $row['total'];
    }
    $stmt->close();
}

$total_pages = ceil($total_records / $records_per_page);

// Get income records with filtering and sorting
$sql = "SELECT i.income_id, i.amount, i.description, i.income_date, c.name as category_name, c.category_id
        FROM income i
        JOIN income_categories c ON i.category_id = c.category_id
        $where_clause
        ORDER BY $sort_by $sort_order
        LIMIT ?, ?";

$income_records = [];

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($param_types . "ii", ...[...$params, $offset, $records_per_page]);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $income_records[] = $row;
    }
    
    $stmt->close();
}

// Calculate summary statistics
$summary_sql = "SELECT 
                SUM(amount) as total_income,
                AVG(amount) as average_income,
                MAX(amount) as max_income,
                COUNT(*) as transaction_count
                FROM income i " . $where_clause;

$summary = [
    'total_income' => 0,
    'average_income' => 0,
    'max_income' => 0,
    'transaction_count' => 0
];

if ($stmt = $conn->prepare($summary_sql)) {
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $summary = $row;
    }
    
    $stmt->close();
}

// Get months for filter dropdown
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

// Get years for filter dropdown (last 5 years)
$current_year = date('Y');
$years = ['all' => 'All Years'];
for ($i = 0; $i < 5; $i++) {
    $year = $current_year - $i;
    $years[$year] = $year;
}

// Close connection
$conn->close();

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<!-- Main Content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Income Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                <i class="fas fa-plus me-1"></i> Add Income
            </button>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-6 col-xl-3 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <h5 class="card-title text-muted mb-0">Total Income</h5>
                            <h4 class="fw-bold mt-2">Ksh <?php echo number_format($summary['total_income']?? 0, 2); ?></h4>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon-circle bg-success text-white rounded-circle p-3 d-inline-flex">
                                <i class="fas fa-dollar-sign fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <h5 class="card-title text-muted mb-0">Average Income</h5>
                            <h4 class="fw-bold mt-2">Ksh <?php echo number_format($summary['average_income']?? 0, 2); ?></h4>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon-circle bg-info text-white rounded-circle p-3 d-inline-flex">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <h5 class="card-title text-muted mb-0">Highest Income</h5>
                            <h4 class="fw-bold mt-2">Ksh <?php echo number_format($summary['max_income']?? 0, 2); ?></h4>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon-circle bg-primary text-white rounded-circle p-3 d-inline-flex">
                                <i class="fas fa-trophy fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <h5 class="card-title text-muted mb-0">Transactions</h5>
                            <h4 class="fw-bold mt-2"><?php echo number_format($summary['transaction_count']); ?></h4>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon-circle bg-warning text-white rounded-circle p-3 d-inline-flex">
                                <i class="fas fa-receipt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Sort Options -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Filter & Sort</h5>
        </div>
        <div class="card-body">
            <form action="income.php" method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="month" class="form-label">Month</label>
                    <select class="form-select" id="month" name="month">
                        <?php foreach ($months as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($filter_month == $key) ? 'selected' : ''; ?>>
                                <?php echo $value; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <?php foreach ($years as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($filter_year == $key) ? 'selected' : ''; ?>>
                                <?php echo $value; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo ($filter_category == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort" class="form-label">Sort By</label>
                    <div class="input-group">
                        <select class="form-select" id="sort" name="sort">
                            <option value="income_date" <?php echo ($sort_by == 'income_date') ? 'selected' : ''; ?>>Date</option>
                            <option value="amount" <?php echo ($sort_by == 'amount') ? 'selected' : ''; ?>>Amount</option>
                            <option value="category_id" <?php echo ($sort_by == 'category_id') ? 'selected' : ''; ?>>Category</option>
                        </select>
                        <select class="form-select" id="order" name="order">
                            <option value="DESC" <?php echo ($sort_order == 'DESC') ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo ($sort_order == 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="income.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Income Records Table -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Income Records</h5>
            <a href="categories.php?type=income" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-tags me-1"></i> Manage Categories
            </a>
        </div>
        <div class="card-body">
            <?php if (count($income_records) == 0): ?>
                <div class="text-center py-4">
                    <i class="fas fa-dollar-sign fa-3x text-muted mb-3"></i>
                    <p>No income records found for the selected filters.</p>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                        <i class="fas fa-plus me-1"></i> Add Income
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
                                <th class="text-end">Amount (Ksh)</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($income_records as $income): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($income['income_date'])); ?></td>
                                <td><?php echo htmlspecialchars($income['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($income['description']); ?></td>
                                <td class="text-end text-success fw-bold"><?php echo number_format($income['amount'], 2); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1 edit-btn" 
                                            data-id="<?php echo $income['income_id']; ?>"
                                            data-category="<?php echo $income['category_id']; ?>"
                                            data-amount="<?php echo $income['amount']; ?>"
                                            data-description="<?php echo htmlspecialchars($income['description']); ?>"
                                            data-date="<?php echo $income['income_date']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" 
                                            data-id="<?php echo $income['income_id']; ?>"
                                            data-description="<?php echo htmlspecialchars($income['description']); ?>"
                                            data-amount="<?php echo number_format($income['amount'], 2); ?>">
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
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo "?page=".($page-1)."&month=$filter_month&year=$filter_year&category=$filter_category&sort=$sort_by&order=$sort_order"; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo "?page=$i&month=$filter_month&year=$filter_year&category=$filter_category&sort=$sort_by&order=$sort_order"; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo "?page=".($page+1)."&month=$filter_month&year=$filter_year&category=$filter_category&sort=$sort_by&order=$sort_order"; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Add Income Modal -->
<div class="modal fade" id="addIncomeModal" tabindex="-1" aria-labelledby="addIncomeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addIncomeModalLabel">Add New Income</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="addIncomeForm">
                    <div class="mb-3">
                        <label for="income_date" class="form-label">Date</label>
                        <input type="date" class="form-control <?php echo (!empty($income_date_err)) ? 'is-invalid' : ''; ?>" id="income_date" name="income_date" value="<?php echo date('Y-m-d'); ?>" required>
                        <div class="invalid-feedback"><?php echo $income_date_err; ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select <?php echo (!empty($category_id_err)) ? 'is-invalid' : ''; ?>" id="category_id" name="category_id" required>
                            <option value="" selected disabled>Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $category_id_err; ?></div>
                        <div class="mt-1">
                            <a href="categories.php?type=income" class="small text-decoration-none">
                                <i class="fas fa-plus-circle me-1"></i>Add New Category
                            </a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount (Ksh)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" id="amount" name="amount" placeholder="0.00" required>
                        <div class="invalid-feedback"><?php echo $amount_err; ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional: Add details about this income"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addIncomeForm" class="btn btn-success">Save Income</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Income Modal -->
<div class="modal fade" id="editIncomeModal" tabindex="-1" aria-labelledby="editIncomeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editIncomeModalLabel">Edit Income</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="editIncomeForm">
                    <input type="hidden" name="income_id" id="edit_income_id">
                    <input type="hidden" name="update_income" value="1">
                    <div class="mb-3">
                        <label for="edit_income_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="edit_income_date" name="income_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_id" class="form-label">Category</label>
                        <select class="form-select" id="edit_category_id" name="category_id" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_amount" class="form-label">Amount (Ksh)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="edit_amount" name="amount" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editIncomeForm" class="btn btn-primary">Update Income</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Income Modal -->
<div class="modal fade" id="deleteIncomeModal" tabindex="-1" aria-labelledby="deleteIncomeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteIncomeModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this income record?</p>
                <p><strong>Description:</strong> <span id="delete_description"></span></p>
                <p><strong>Amount:</strong> Ksh <span id="delete_amount"></span></p>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="deleteIncomeForm">
                    <input type="hidden" name="income_id" id="delete_income_id">
                    <input type="hidden" name="delete_income" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteIncomeForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle edit button clicks
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const category = this.getAttribute('data-category');
            const amount = this.getAttribute('data-amount');
            const description = this.getAttribute('data-description');
            const date = this.getAttribute('data-date');
            
            document.getElementById('edit_income_id').value = id;
            document.getElementById('edit_category_id').value = category;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_income_date').value = date;
            
            const editModal = new bootstrap.Modal(document.getElementById('editIncomeModal'));
            editModal.show();
        });
    });
    
    // Handle delete button clicks
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const description = this.getAttribute('data-description');
            const amount = this.getAttribute('data-amount');
            
            document.getElementById('delete_income_id').value = id;
            document.getElementById('delete_description').textContent = description || 'No description';
            document.getElementById('delete_amount').textContent = amount;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteIncomeModal'));
            deleteModal.show();
        });
    });
    
    // Initialize any tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });
</script>

