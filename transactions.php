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

// Initialize filter variables with default values
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : date('Y-m-01'); // First day of current month
$filter_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : date('Y-m-t'); // Last day of current month
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$filter_min_amount = isset($_GET['min_amount']) ? $_GET['min_amount'] : '';
$filter_max_amount = isset($_GET['max_amount']) ? $_GET['max_amount'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Items per page for pagination
$items_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Function to get transactions with filters and pagination
function getTransactions($conn, $user_id, $filter_type, $filter_date_start, $filter_date_end, 
                        $filter_category, $filter_min_amount, $filter_max_amount, $search, 
                        $sort_by, $sort_order, $offset, $items_per_page, &$total_records) {
    // Base SQL for income
    $income_sql = "SELECT i.income_id AS id, 'income' AS type, i.amount, i.description, 
                  i.income_date AS transaction_date, ic.name AS category
                  FROM income i
                  JOIN income_categories ic ON i.category_id = ic.category_id
                  WHERE i.user_id = ?";
    
    // Base SQL for expenses
    $expense_sql = "SELECT e.expense_id AS id, 'expense' AS type, e.amount, e.description, 
                   e.expense_date AS transaction_date, ec.name AS category
                   FROM expenses e
                   JOIN expense_categories ec ON e.category_id = ec.category_id
                   WHERE e.user_id = ?";
    
    // Apply date filters
    if ($filter_date_start) {
        $income_sql .= " AND i.income_date >= ?";
        $expense_sql .= " AND e.expense_date >= ?";
    }
    
    if ($filter_date_end) {
        $income_sql .= " AND i.income_date <= ?";
        $expense_sql .= " AND e.expense_date <= ?";
    }
    
    // Apply category filter
    if ($filter_category) {
        $income_sql .= " AND ic.name = ?";
        $expense_sql .= " AND ec.name = ?";
    }
    
    // Apply amount filters
    if ($filter_min_amount !== '') {
        $income_sql .= " AND i.amount >= ?";
        $expense_sql .= " AND e.amount >= ?";
    }
    
    if ($filter_max_amount !== '') {
        $income_sql .= " AND i.amount <= ?";
        $expense_sql .= " AND e.amount <= ?";
    }
    
    // Apply search filter to description
    if ($search) {
        $income_sql .= " AND i.description LIKE ?";
        $expense_sql .= " AND e.description LIKE ?";
    }
    
    // Prepare parameters
    $income_params = array($user_id);
    $expense_params = array($user_id);
    $param_types_income = "i";
    $param_types_expense = "i";
    
    if ($filter_date_start) {
        $income_params[] = $filter_date_start;
        $expense_params[] = $filter_date_start;
        $param_types_income .= "s";
        $param_types_expense .= "s";
    }
    
    if ($filter_date_end) {
        $income_params[] = $filter_date_end;
        $expense_params[] = $filter_date_end;
        $param_types_income .= "s";
        $param_types_expense .= "s";
    }
    
    if ($filter_category) {
        $income_params[] = $filter_category;
        $expense_params[] = $filter_category;
        $param_types_income .= "s";
        $param_types_expense .= "s";
    }
    
    if ($filter_min_amount !== '') {
        $income_params[] = $filter_min_amount;
        $expense_params[] = $filter_min_amount;
        $param_types_income .= "d";
        $param_types_expense .= "d";
    }
    
    if ($filter_max_amount !== '') {
        $income_params[] = $filter_max_amount;
        $expense_params[] = $filter_max_amount;
        $param_types_income .= "d";
        $param_types_expense .= "d";
    }
    
    if ($search) {
        $search_term = "%" . $search . "%";
        $income_params[] = $search_term;
        $expense_params[] = $search_term;
        $param_types_income .= "s";
        $param_types_expense .= "s";
    }
    
    // Combine queries based on type filter
    if ($filter_type == 'all') {
        $combined_sql = "($income_sql) UNION ALL ($expense_sql)";
        $combined_params = array_merge($income_params, $expense_params);
        $param_types = $param_types_income . $param_types_expense;
    } elseif ($filter_type == 'income') {
        $combined_sql = $income_sql;
        $combined_params = $income_params;
        $param_types = $param_types_income;
    } else { // expense
        $combined_sql = $expense_sql;
        $combined_params = $expense_params;
        $param_types = $param_types_expense;
    }
    
    // Count total records for pagination
    $count_sql = "SELECT COUNT(*) AS total FROM ($combined_sql) AS combined_transactions";
    $stmt = $conn->prepare($count_sql);
    
    // Bind parameters for count query
    if ($param_types !== "i") {
        $stmt->bind_param($param_types, ...$combined_params);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $count_result = $stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    
    // Apply sorting
    $sort_column = ($sort_by == 'date') ? 'transaction_date' : 
                  (($sort_by == 'amount') ? 'amount' : 
                  (($sort_by == 'category') ? 'category' : 'transaction_date'));
    
    $combined_sql .= " ORDER BY $sort_column $sort_order";
    
    // Apply pagination
    $combined_sql .= " LIMIT $offset, $items_per_page";
    
    // Execute final query
    $stmt = $conn->prepare($combined_sql);
    
    // Bind parameters for main query
    if ($param_types !== "i") {
        $stmt->bind_param($param_types, ...$combined_params);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = array();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    return $transactions;
}

// Function to get all categories (both income and expense)
function getAllCategories($conn, $user_id) {
    $categories = array();
    
    // Get income categories
    $sql = "SELECT name FROM income_categories WHERE user_id = ? OR user_id IS NULL ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['name'];
    }
    
    // Get expense categories
    $sql = "SELECT name FROM expense_categories WHERE user_id = ? OR user_id IS NULL ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['name'], $categories)) {
            $categories[] = $row['name'];
        }
    }
    
    sort($categories);
    return $categories;
}

// Get transactions with applied filters
$total_records = 0;
$transactions = getTransactions(
    $conn, $user_id, $filter_type, $filter_date_start, $filter_date_end,
    $filter_category, $filter_min_amount, $filter_max_amount, $search,
    $sort_by, $sort_order, $offset, $items_per_page, $total_records
);

// Calculate total pages for pagination
$total_pages = ceil($total_records / $items_per_page);

// Get all categories for filter dropdown
$all_categories = getAllCategories($conn, $user_id);

// Handle transaction deletion if requested
if (isset($_POST['delete_transaction']) && isset($_POST['transaction_id']) && isset($_POST['transaction_type'])) {
    $transaction_id = $_POST['transaction_id'];
    $transaction_type = $_POST['transaction_type'];
    
    // Prepare the appropriate SQL statement based on transaction type
    if ($transaction_type === 'income') {
        $sql = "DELETE FROM income WHERE income_id = ? AND user_id = ?";
    } else {
        $sql = "DELETE FROM expenses WHERE expense_id = ? AND user_id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $transaction_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Transaction deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Error deleting transaction.";
    }
    
    // Redirect to refresh page and prevent form resubmission
    header("Location: transactions.php");
    exit;
}

// Close connection
$conn->close();

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<!-- Main Content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Transaction History</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="income.php" class="btn btn-sm btn-success">
                    <i class="fas fa-plus me-1"></i>Add Income
                </a>
                <a href="expenses.php" class="btn btn-sm btn-danger">
                    <i class="fas fa-minus me-1"></i>Add Expense
                </a>
            </div>
            
        </div>
    </div>

    <?php if(isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <a class="text-decoration-none text-dark fw-bold" data-bs-toggle="collapse" href="#filtersCollapse" role="button">
                <i class="fas fa-filter me-1"></i> Filter Transactions
                <i class="fas fa-chevron-down float-end"></i>
            </a>
        </div>
        <div class="collapse show" id="filtersCollapse">
            <div class="card-body">
                <form method="GET" action="transactions.php" id="filter-form" class="row g-3">
                    <!-- Transaction Type Filter -->
                    <div class="col-md-3">
                        <label for="type" class="form-label">Transaction Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Transactions</option>
                            <option value="income" <?php echo $filter_type == 'income' ? 'selected' : ''; ?>>Income Only</option>
                            <option value="expense" <?php echo $filter_type == 'expense' ? 'selected' : ''; ?>>Expenses Only</option>
                        </select>
                    </div>

                    <!-- Date Range Filter -->
                    <div class="col-md-5">
                        <label class="form-label">Date Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="date_start" name="date_start" value="<?php echo $filter_date_start; ?>">
                            <span class="input-group-text">to</span>
                            <input type="date" class="form-control" id="date_end" name="date_end" value="<?php echo $filter_date_end; ?>">
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="col-md-4">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($all_categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filter_category == $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Amount Range Filter -->
                    <div class="col-md-4">
                        <label class="form-label">Amount Range (Ksh)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="min_amount" name="min_amount" 
                                placeholder="Min" value="<?php echo $filter_min_amount; ?>">
                            <span class="input-group-text">to</span>
                            <input type="number" step="0.01" class="form-control" id="max_amount" name="max_amount" 
                                placeholder="Max" value="<?php echo $filter_max_amount; ?>">
                        </div>
                    </div>

                    <!-- Search Filter -->
                    <div class="col-md-5">
                        <label for="search" class="form-label">Search Description</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search" name="search" 
                                placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Sort Options -->
                    <div class="col-md-3">
                        <label for="sort_by" class="form-label">Sort By</label>
                        <div class="input-group">
                            <select class="form-select" id="sort_by" name="sort_by">
                                <option value="date" <?php echo $sort_by == 'date' ? 'selected' : ''; ?>>Date</option>
                                <option value="amount" <?php echo $sort_by == 'amount' ? 'selected' : ''; ?>>Amount</option>
                                <option value="category" <?php echo $sort_by == 'category' ? 'selected' : ''; ?>>Category</option>
                            </select>
                            <select class="form-select" id="sort_order" name="sort_order">
                                <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                        </div>
                    </div>

                    <!-- Submit/Reset Buttons -->
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="transactions.php" class="btn btn-outline-secondary">Reset Filters</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card mb-4">
        <div class="card-body">
            <?php if(count($transactions) == 0): ?>
            <div class="text-center py-5">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <h5>No transactions found</h5>
                <p class="text-muted">Try adjusting your filters or add some transactions.</p>
                <div class="mt-3">
                    <a href="income.php" class="btn btn-success me-2">Add Income</a>
                    <a href="expenses.php" class="btn btn-danger">Add Expense</a>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th class="text-end">Amount (Ksh)</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?></td>
                            <td>
                                <span class="badge <?php echo ($transaction['type'] == 'expense') ? 'badge-expense bg-danger' : 'badge-income bg-success'; ?> text-white">
                                    <?php echo htmlspecialchars($transaction['category']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                            <td class="text-end <?php echo ($transaction['type'] == 'expense') ? 'text-danger' : 'text-success'; ?>">
                                <?php echo ($transaction['type'] == 'expense') ? '-' : '+'; ?>
                                <?php echo number_format($transaction['amount'], 2); ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                <?php
                                $edit_page = ($transaction['type'] == 'expense') ? 'expenses.php' : 'income.php';
                                ?>
                                <a href="<?php echo $edit_page; ?>?action=edit&id=<?php echo $transaction['id']; ?>" 
                                class="btn btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                    <button type="button" class="btn btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal"
                                            data-id="<?php echo $transaction['id']; ?>"
                                            data-type="<?php echo $transaction['type']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-4">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page='.($page-1).'&'.http_build_query(array_filter([
                            'type' => $filter_type,
                            'date_start' => $filter_date_start,
                            'date_end' => $filter_date_end,
                            'category' => $filter_category,
                            'min_amount' => $filter_min_amount,
                            'max_amount' => $filter_max_amount,
                            'search' => $search,
                            'sort_by' => $sort_by,
                            'sort_order' => $sort_order
                        ])); ?>">Previous</a>
                    </li>
                    
                    <?php for($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter([
                            'type' => $filter_type,
                            'date_start' => $filter_date_start,
                            'date_end' => $filter_date_end,
                            'category' => $filter_category,
                            'min_amount' => $filter_min_amount,
                            'max_amount' => $filter_max_amount,
                            'search' => $search,
                            'sort_by' => $sort_by,
                            'sort_order' => $sort_order
                        ])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page+1).'&'.http_build_query(array_filter([
                            'type' => $filter_type,
                            'date_start' => $filter_date_start,
                            'date_end' => $filter_date_end,
                            'category' => $filter_category,
                            'min_amount' => $filter_min_amount,
                            'max_amount' => $filter_max_amount,
                            'search' => $search,
                            'sort_by' => $sort_by,
                            'sort_order' => $sort_order
                        ])); ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            
            <!-- Summary Stats -->
            <div class="d-flex justify-content-between mt-3">
                <p class="text-muted">Showing <?php echo count($transactions); ?> of <?php echo $total_records; ?> transactions</p>
                <?php
                    // Calculate totals
                    $total_income = 0;
                    $total_expense = 0;
                    foreach ($transactions as $t) {
                        if ($t['type'] == 'income') {
                            $total_income += $t['amount'];
                        } else {
                            $total_expense += $t['amount'];
                        }
                    }
                ?>
                <div class="text-end">
                    <span class="badge bg-success p-2 me-2">Income: Ksh <?php echo number_format($total_income, 2); ?></span>
                    <span class="badge bg-danger p-2 me-2">Expenses: Ksh <?php echo number_format($total_expense, 2); ?></span>
                    <span class="badge bg-primary p-2">Balance: Ksh <?php echo number_format($total_income - $total_expense, 2); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Delete Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this transaction? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <form method="POST" action="transactions.php">
                    <input type="hidden" name="transaction_id" id="delete_transaction_id">
                    <input type="hidden" name="transaction_type" id="delete_transaction_type">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_transaction" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for page functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set up delete modal
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const type = button.getAttribute('data-type');
            
            const transactionIdInput = document.getElementById('delete_transaction_id');
            const transactionTypeInput = document.getElementById('delete_transaction_type');
            
            transactionIdInput.value = id;
            transactionTypeInput.value = type;
        });
    }
    
    // Export button functionality
    document.getElementById('export-transactions').addEventListener('click', function() {
        // Gather current filter parameters
        const params = new URLSearchParams(window.location.search);
        
        // Create export URL with current filters
        const exportUrl = 'export-transactions.php?' + params.toString();
        
      
    });
    
});
</script>

