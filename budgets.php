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

// Process form submission for creating or updating budget
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["action"])) {
        // Get form data
        $category_id = trim($_POST["category_id"]);
        $amount = trim($_POST["amount"]);
        $period_start = trim($_POST["period_start"]);
        $period_end = trim($_POST["period_end"]);
        
        // Validate input
        if (empty($category_id) || empty($amount) || empty($period_start) || empty($period_end)) {
            $error_message = "Please fill all required fields.";
        } elseif (!is_numeric($amount) || $amount <= 0) {
            $error_message = "Amount must be a positive number.";
        } elseif (strtotime($period_end) < strtotime($period_start)) {
            $error_message = "End date cannot be before start date.";
        } else {
            // Create or update budget based on action
            if ($_POST["action"] == "create") {
                // Check if budget already exists for this category and period
                $check_sql = "SELECT budget_id FROM budgets 
                             WHERE user_id = ? AND category_id = ? 
                             AND ((period_start BETWEEN ? AND ?) 
                             OR (period_end BETWEEN ? AND ?))";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("iissss", $user_id, $category_id, $period_start, $period_end, $period_start, $period_end);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error_message = "A budget for this category already exists in the selected period.";
                } else {
                    // Insert new budget
                    $insert_sql = "INSERT INTO budgets (user_id, category_id, amount, period_start, period_end) 
                                  VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param("iidss", $user_id, $category_id, $amount, $period_start, $period_end);
                    
                    if ($stmt->execute()) {
                        $success_message = "Budget created successfully!";
                    } else {
                        $error_message = "Error: " . $stmt->error;
                    }
                }
            } elseif ($_POST["action"] == "update") {
                $budget_id = $_POST["budget_id"];
                
                // Check if budget exists and belongs to user
                $check_sql = "SELECT budget_id FROM budgets WHERE budget_id = ? AND user_id = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("ii", $budget_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    $error_message = "Budget not found or you don't have permission to edit it.";
                } else {
                    // Update budget
                    $update_sql = "UPDATE budgets SET category_id = ?, amount = ?, period_start = ?, period_end = ? 
                                  WHERE budget_id = ? AND user_id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("idssii", $category_id, $amount, $period_start, $period_end, $budget_id, $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Budget updated successfully!";
                    } else {
                        $error_message = "Error: " . $stmt->error;
                    }
                }
            }
        }
    } elseif (isset($_POST["delete"])) {
        // Delete budget
        $budget_id = $_POST["budget_id"];
        
        // Check if budget exists and belongs to user
        $check_sql = "SELECT budget_id FROM budgets WHERE budget_id = ? AND user_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $budget_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error_message = "Budget not found or you don't have permission to delete it.";
        } else {
            // Delete budget
            $delete_sql = "DELETE FROM budgets WHERE budget_id = ? AND user_id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("ii", $budget_id, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Budget deleted successfully!";
            } else {
                $error_message = "Error: " . $stmt->error;
            }
        }
    }
}

// Get all expense categories
$category_sql = "SELECT category_id, name FROM expense_categories 
                WHERE user_id = ? OR user_id IS NULL
                ORDER BY name";
$stmt = $conn->prepare($category_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories_result = $stmt->get_result();

// Get current date info for default date range
$current_month = date('m');
$current_year = date('Y');
$days_in_month = date('t');
$default_start_date = "$current_year-$current_month-01";
$default_end_date = "$current_year-$current_month-$days_in_month";

// Get all budgets for the user
$budgets_sql = "SELECT b.budget_id, b.category_id, ec.name as category_name, 
                b.amount, b.period_start, b.period_end, 
                COALESCE(SUM(e.amount), 0) as spent_amount
                FROM budgets b
                JOIN expense_categories ec ON b.category_id = ec.category_id
                LEFT JOIN expenses e ON ec.category_id = e.category_id 
                    AND e.expense_date BETWEEN b.period_start AND b.period_end
                    AND e.user_id = b.user_id
                WHERE b.user_id = ?
                GROUP BY b.budget_id, b.category_id, ec.name, b.amount, b.period_start, b.period_end
                ORDER BY b.period_start DESC, ec.name";
$stmt = $conn->prepare($budgets_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$budgets_result = $stmt->get_result();

// Helper function to format date for display
function formatDateDisplay($date) {
    return date('M d, Y', strtotime($date));
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<!-- Main Content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Budget Management</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBudgetModal">
            <i class="fas fa-plus-circle me-1"></i> Create New Budget
        </button>
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
    
    <!-- Budget List -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">My Budgets</h5>
        </div>
        <div class="card-body">
            <?php if ($budgets_result->num_rows == 0): ?>
            <div class="text-center py-5">
                <i class="fas fa-wallet fa-4x text-muted mb-4"></i>
                <h5>No budgets found</h5>
                <p class="text-muted">Create your first budget to start tracking your expenses against your financial goals.</p>
                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addBudgetModal">
                    <i class="fas fa-plus-circle me-1"></i> Create New Budget
                </button>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Budget Amount</th>
                            <th>Period</th>
                            <th>Spent</th>
                            <th>Remaining</th>
                            <th>Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($budget = $budgets_result->fetch_assoc()): ?>
                        <?php
                            $remaining = $budget['amount'] - $budget['spent_amount'];
                            $percentage = ($budget['amount'] > 0) ? ($budget['spent_amount'] / $budget['amount']) * 100 : 0;
                            $progress_class = ($percentage > 90) ? 'bg-danger' : (($percentage > 70) ? 'bg-warning' : 'bg-success');
                            $is_active = (strtotime($budget['period_end']) >= strtotime(date('Y-m-d')));
                        ?>
                        <tr class="<?php echo $is_active ? '' : 'text-muted'; ?>">
                            <td><?php echo htmlspecialchars($budget['category_name']); ?></td>
                            <td>Ksh <?php echo number_format($budget['amount'], 2); ?></td>
                            <td><?php echo formatDateDisplay($budget['period_start']); ?> - <?php echo formatDateDisplay($budget['period_end']); ?></td>
                            <td>Ksh <?php echo number_format($budget['spent_amount'], 2); ?></td>
                            <td class="<?php echo ($remaining < 0) ? 'text-danger' : ''; ?>">
                                Ksh <?php echo number_format($remaining, 2); ?>
                            </td>
                            <td class="w-25">
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 me-2">
                                        <div class="progress-bar <?php echo $progress_class; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo min($percentage, 100); ?>%">
                                        </div>
                                    </div>
                                    <small><?php echo number_format($percentage, 0); ?>%</small>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary edit-budget" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editBudgetModal"
                                            data-budget-id="<?php echo $budget['budget_id']; ?>"
                                            data-category-id="<?php echo $budget['category_id']; ?>"
                                            data-amount="<?php echo $budget['amount']; ?>"
                                            data-start-date="<?php echo $budget['period_start']; ?>"
                                            data-end-date="<?php echo $budget['period_end']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-budget" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteBudgetModal"
                                            data-budget-id="<?php echo $budget['budget_id']; ?>"
                                            data-category-name="<?php echo htmlspecialchars($budget['category_name']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Budget Tips -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Budgeting Tips</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-start">
                        <div class="icon-circle bg-primary text-white rounded-circle p-3 me-3">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div>
                            <h6>50/30/20 Rule</h6>
                            <p class="text-muted small">Allocate 50% of your income to needs, 30% to wants, and 20% to savings or debt repayment.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-start">
                        <div class="icon-circle bg-success text-white rounded-circle p-3 me-3">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <h6>Review Regularly</h6>
                            <p class="text-muted small">Update your budgets monthly to adjust for changing expenses and financial goals.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-start">
                        <div class="icon-circle bg-warning text-white rounded-circle p-3 me-3">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <h6>Emergency Fund</h6>
                            <p class="text-muted small">Aim to build an emergency fund covering 3-6 months of essential expenses.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add Budget Modal -->
<div class="modal fade" id="addBudgetModal" tabindex="-1" aria-labelledby="addBudgetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBudgetModalLabel">Create New Budget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Expense Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php
                            $categories_result->data_seek(0);
                            while ($category = $categories_result->fetch_assoc()) {
                                echo '<option value="' . $category['category_id'] . '">' . htmlspecialchars($category['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Budget Amount (Ksh)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="0.01" step="0.01" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col">
                            <label for="period_start" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="period_start" name="period_start" value="<?php echo $default_start_date; ?>" required>
                        </div>
                        <div class="col">
                            <label for="period_end" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="period_end" name="period_end" value="<?php echo $default_end_date; ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Budget</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Budget Modal -->
<div class="modal fade" id="editBudgetModal" tabindex="-1" aria-labelledby="editBudgetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBudgetModalLabel">Edit Budget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="budget_id" id="edit_budget_id">
                    
                    <div class="mb-3">
                        <label for="edit_category_id" class="form-label">Expense Category</label>
                        <select class="form-select" id="edit_category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php
                            $categories_result->data_seek(0);
                            while ($category = $categories_result->fetch_assoc()) {
                                echo '<option value="' . $category['category_id'] . '">' . htmlspecialchars($category['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_amount" class="form-label">Budget Amount (Ksh)</label>
                        <input type="number" class="form-control" id="edit_amount" name="amount" min="0.01" step="0.01" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col">
                            <label for="edit_period_start" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="edit_period_start" name="period_start" required>
                        </div>
                        <div class="col">
                            <label for="edit_period_end" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="edit_period_end" name="period_end" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Budget</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Budget Modal -->
<div class="modal fade" id="deleteBudgetModal" tabindex="-1" aria-labelledby="deleteBudgetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteBudgetModalLabel">Delete Budget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the budget for <span id="delete_category_name" class="fw-bold"></span>?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="delete" value="true">
                <input type="hidden" name="budget_id" id="delete_budget_id">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Budget</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
    // Handle edit budget modal
    document.querySelectorAll('.edit-budget').forEach(button => {
        button.addEventListener('click', function() {
            const budgetId = this.getAttribute('data-budget-id');
            const categoryId = this.getAttribute('data-category-id');
            const amount = this.getAttribute('data-amount');
            const startDate = this.getAttribute('data-start-date');
            const endDate = this.getAttribute('data-end-date');
            
            document.getElementById('edit_budget_id').value = budgetId;
            document.getElementById('edit_category_id').value = categoryId;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_period_start').value = startDate;
            document.getElementById('edit_period_end').value = endDate;
        });
    });
    
    // Handle delete budget modal
    document.querySelectorAll('.delete-budget').forEach(button => {
        button.addEventListener('click', function() {
            const budgetId = this.getAttribute('data-budget-id');
            const categoryName = this.getAttribute('data-category-name');
            
            document.getElementById('delete_budget_id').value = budgetId;
            document.getElementById('delete_category_name').textContent = categoryName;
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    window.addEventListener('DOMContentLoaded', (event) => {
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    });
</script>
</body>
</html>