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

// Get current date info for filtering
$current_month = date('m');
$current_year = date('Y');
$days_in_month = date('t');

// Calculate date ranges
$month_start = "$current_year-$current_month-01";
$month_end = "$current_year-$current_month-$days_in_month";

$user_id = $_SESSION["user_id"];

// Function to get monthly summary data
function getFinancialSummary($conn, $user_id, $month_start, $month_end) {
    // Get total income for current month
    $income_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM income 
                   WHERE user_id = ? AND income_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($income_sql);
    $stmt->bind_param("iss", $user_id, $month_start, $month_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $income_data = $result->fetch_assoc();
    $total_income = $income_data['total'];
    
    // Get total expenses for current month
    $expense_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
                    WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($expense_sql);
    $stmt->bind_param("iss", $user_id, $month_start, $month_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $expense_data = $result->fetch_assoc();
    $total_expenses = $expense_data['total'];
    
    // Calculate savings
    $savings = $total_income - $total_expenses;
    $savings_percentage = ($total_income > 0) ? ($savings / $total_income) * 100 : 0;
    
    return [
        'income' => $total_income,
        'expenses' => $total_expenses,
        'savings' => $savings,
        'savings_percentage' => $savings_percentage
    ];
}

// Function to get expense breakdown by category
function getExpensesByCategory($conn, $user_id, $month_start, $month_end) {
    $sql = "SELECT ec.name as category, COALESCE(SUM(e.amount), 0) as total
            FROM expense_categories ec
            LEFT JOIN expenses e ON ec.category_id = e.category_id 
                AND e.expense_date BETWEEN ? AND ?
                AND e.user_id = ?
            WHERE ec.user_id = ? OR ec.user_id IS NULL
            GROUP BY ec.category_id
            ORDER BY total DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $month_start, $month_end, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    $amounts = [];
    
    while ($row = $result->fetch_assoc()) {
        if ($row['total'] > 0) {
            $categories[] = $row['category'];
            $amounts[] = $row['total'];
        }
    }
    
    return [
        'categories' => $categories,
        'amounts' => $amounts
    ];
}

// Function to get income breakdown by category
function getIncomeByCategory($conn, $user_id, $month_start, $month_end) {
    $sql = "SELECT ic.name as category, COALESCE(SUM(i.amount), 0) as total
            FROM income_categories ic
            LEFT JOIN income i ON ic.category_id = i.category_id 
                AND i.income_date BETWEEN ? AND ?
                AND i.user_id = ?
            WHERE ic.user_id = ? OR ic.user_id IS NULL
            GROUP BY ic.category_id
            ORDER BY total DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $month_start, $month_end, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    $amounts = [];
    
    while ($row = $result->fetch_assoc()) {
        if ($row['total'] > 0) {
            $categories[] = $row['category'];
            $amounts[] = $row['total'];
        }
    }
    
    return [
        'categories' => $categories,
        'amounts' => $amounts
    ];
}

// Function to get budget status
// Function to get budget status
function getBudgetStatus($conn, $user_id, $month_start, $month_end) {
    $sql = "SELECT ec.name as category, b.amount as budget_amount, 
            COALESCE(SUM(e.amount), 0) as spent_amount
            FROM budgets b
            JOIN expense_categories ec ON b.category_id = ec.category_id
            LEFT JOIN expenses e ON ec.category_id = e.category_id 
                AND e.expense_date BETWEEN ? AND ?
                AND e.user_id = ?
            WHERE b.user_id = ? AND b.period_start <= ? AND b.period_end >= ?
            GROUP BY b.category_id, ec.name, b.amount  /* Added b.amount to GROUP BY */
            ORDER BY ec.name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiiss", $month_start, $month_end, $user_id, $user_id, $month_end, $month_start);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $budget_data = [];
    
    while ($row = $result->fetch_assoc()) {
        $percentage = ($row['budget_amount'] > 0) ? ($row['spent_amount'] / $row['budget_amount']) * 100 : 0;
        $budget_data[] = [
            'category' => $row['category'],
            'budget' => $row['budget_amount'],
            'spent' => $row['spent_amount'],
            'percentage' => min($percentage, 100)
        ];
    }
    
    return $budget_data;
}

// Function to get recent transactions
function getRecentTransactions($conn, $user_id, $limit = 5) {
    // Get recent expenses
    $expense_sql = "SELECT 'expense' as type, e.amount, e.description, e.expense_date as transaction_date, 
                   ec.name as category
                   FROM expenses e
                   JOIN expense_categories ec ON e.category_id = ec.category_id
                   WHERE e.user_id = ?
                   ORDER BY e.expense_date DESC, e.expense_id DESC
                   LIMIT ?";
    
    $stmt = $conn->prepare($expense_sql);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $expense_result = $stmt->get_result();
    
    $expenses = [];
    while ($row = $expense_result->fetch_assoc()) {
        $expenses[] = $row;
    }
    
    // Get recent income
    $income_sql = "SELECT 'income' as type, i.amount, i.description, i.income_date as transaction_date, 
                  ic.name as category
                  FROM income i
                  JOIN income_categories ic ON i.category_id = ic.category_id
                  WHERE i.user_id = ?
                  ORDER BY i.income_date DESC, i.income_id DESC
                  LIMIT ?";
    
    $stmt = $conn->prepare($income_sql);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $income_result = $stmt->get_result();
    
    $incomes = [];
    while ($row = $income_result->fetch_assoc()) {
        $incomes[] = $row;
    }
    
    // Merge and sort transactions
    $transactions = array_merge($expenses, $incomes);
    usort($transactions, function($a, $b) {
        return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
    });
    
    return array_slice($transactions, 0, $limit);
}

// Get financial data
$summary = getFinancialSummary($conn, $user_id, $month_start, $month_end);
$expense_breakdown = getExpensesByCategory($conn, $user_id, $month_start, $month_end);
$income_breakdown = getIncomeByCategory($conn, $user_id, $month_start, $month_end);
$budget_status = getBudgetStatus($conn, $user_id, $month_start, $month_end);
$recent_transactions = getRecentTransactions($conn, $user_id);

// Get 6-month income and expense trend data
$trend_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('m', strtotime("-$i months"));
    $year = date('Y', strtotime("-$i months"));
    $month_start_date = "$year-$month-01";
    $month_end_date = date('Y-m-t', strtotime($month_start_date));
    $month_name = date('M', strtotime($month_start_date));
    
    // Get income for month
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM income 
            WHERE user_id = ? AND income_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $month_start_date, $month_end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $monthly_income = $row['total'];
    
    // Get expenses for month
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
            WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $month_start_date, $month_end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $monthly_expense = $row['total'];
    
    $trend_data[] = [
        'month' => $month_name,
        'income' => $monthly_income,
        'expenses' => $monthly_expense
    ];
}

// Close connection
$conn->close();

include 'partials/header.php';
include 'partials/sidebar.php';
?>


  
        

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Financial Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="current-month-btn">
                                <?php echo date('F Y'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="card stats-card income-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-8">
                                        <h5 class="card-title text-muted mb-0">Total Income(ksh)</h5>
                                        <h4 class="fw-bold mt-2"><?php echo number_format($summary['income'], 2); ?></h4>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon-circle bg-success text-white rounded-circle p-3 d-inline-flex">
                                            <i class="fas fa-dollar-sign fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="text-success">
                                        <i class="fas fa-arrow-up me-1"></i>This Month
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="card stats-card expense-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-8">
                                        <h5 class="card-title text-muted mb-0">Total Expenses(ksh)</h5>
                                        <h4 class="fw-bold mt-2"><?php echo number_format($summary['expenses'], 2); ?></h4>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon-circle bg-danger text-white rounded-circle p-3 d-inline-flex">
                                            <i class="fas fa-credit-card fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="text-danger">
                                        <i class="fas fa-arrow-down me-1"></i>This Month
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="card stats-card savings-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-8">
                                        <h5 class="card-title text-muted mb-0">Savings(ksh)</h5>
                                        <h4 class="fw-bold mt-2"><?php echo number_format($summary['savings'], 2); ?></h4>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon-circle bg-primary text-white rounded-circle p-3 d-inline-flex">
                                            <i class="fas fa-piggy-bank fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="<?php echo $summary['savings'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo number_format($summary['savings_percentage'], 1); ?>% of Income
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="card stats-card budget-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-8">
                                        <h5 class="card-title text-muted mb-0">Budget Status</h5>
                                        <h4 class="fw-bold mt-2"><?php echo count($budget_status); ?> Active</h4>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon-circle bg-warning text-white rounded-circle p-3 d-inline-flex">
                                            <i class="fas fa-tasks fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <a href="budgets.php" class="text-decoration-none">
                                        <span class="text-warning">View Details <i class="fas fa-arrow-right ms-1"></i></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Income vs Expenses Chart -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Income vs Expenses Trend</h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" id="trendDropdown" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="reports.php">View Full Report</a></li>
                                        <li><a class="dropdown-item" href="#" id="exportTrendBtn">Export Data</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="incomeExpenseChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Expense Breakdown Chart -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Expense Breakdown</h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" id="expenseDropdown" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="expenses.php">Manage Expenses</a></li>
                                        <li><a class="dropdown-item" href="categories.php">Manage Categories</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="expenseChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Budget Progress and Recent Transactions -->
                <div class="row">
                    <!-- Budget Progress -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Budget Progress</h5>
                                <a href="budgets.php" class="btn btn-sm btn-outline-light">
                                    <i class="fas fa-plus me-1"></i>Add Budget
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (count($budget_status) == 0): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-wallet fa-3x text-muted mb-3"></i>
                                    <p>No budgets created yet.</p>
                                    <a href="budgets.php" class="btn btn-primary">Create Your First Budget</a>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Budget</th>
                                                <th>Spent</th>
                                                <th>Progress</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($budget_status as $budget): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($budget['category']); ?></td>
                                                <td>Ksh <?php echo number_format($budget['budget'], 2); ?></td>
                                                <td>Ksh <?php echo number_format($budget['spent'], 2); ?></td>
                                                <td class="w-25">
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2">
                                                            <div class="progress-bar <?php echo ($budget['percentage'] > 90) ? 'bg-danger' : (($budget['percentage'] > 70) ? 'bg-warning' : 'bg-success'); ?>" 
                                                                 role="progressbar" 
                                                                 style="width: <?php echo $budget['percentage']; ?>%">
                                                            </div>
                                                        </div>
                                                        <small><?php echo number_format($budget['percentage'], 0); ?>%</small>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Transactions</h5>
                                <div>
                                    <a href="income.php" class="btn btn-sm btn-success me-1">
                                        <i class="fas fa-plus me-1"></i>Income
                                    </a>
                                    <a href="expenses.php" class="btn btn-sm btn-danger">
                                        <i class="fas fa-minus me-1"></i>Expense
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_transactions) == 0): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                    <p>No transactions recorded yet.</p>
                                    <div class="mt-3">
                                        <a href="income.php" class="btn btn-success me-2">Add Income</a>
                                        <a href="expenses.php" class="btn btn-danger">Add Expense</a>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Category</th>
                                                <th>Description</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo date('M d', strtotime($transaction['transaction_date'])); ?></td>
                                                <td>
                                                    <span class="badge <?php echo ($transaction['type'] == 'expense') ? 'badge-expense' : 'badge-income'; ?> text-white">
                                                    <?php echo htmlspecialchars($transaction['category']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                <td class="<?php echo ($transaction['type'] == 'expense') ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo ($transaction['type'] == 'expense') ? '-' : '+'; ?>Ksh <?php echo number_format($transaction['amount'], 2); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="text-center mt-3">
                                        <a href="transactions.php" class="btn btn-sm btn-outline-primary">View All Transactions</a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Income vs Expense Chart
        const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
        const incomeExpenseChart = new Chart(incomeExpenseCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($trend_data, 'month')); ?>,
                datasets: [
                    {
                        label: 'Income',
                        data: <?php echo json_encode(array_column($trend_data, 'income')); ?>,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Expenses',
                        data: <?php echo json_encode(array_column($trend_data, 'expenses')); ?>,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('en-US', { 
                                        style: 'currency', 
                                        currency: 'Ksh' 
                                    }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Ksh' + value;
                            }
                        }
                    }
                }
            }
        });

        // Expense Breakdown Chart
        const expenseCtx = document.getElementById('expenseChart').getContext('2d');
        const expenseChart = new Chart(expenseCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($expense_breakdown['categories']); ?>,
                datasets: [{
                    data: <?php echo json_encode($expense_breakdown['amounts']); ?>,
                    backgroundColor: [
                        '#dc3545', '#fd7e14', '#ffc107', '#198754', '#0d6efd', 
                        '#6610f2', '#d63384', '#20c997', '#0dcaf0', '#6c757d'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `$${value.toFixed(2)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

       
    </script>
</body>
</html>