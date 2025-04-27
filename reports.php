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

// Include TCPDF library
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

// Custom TCPDF extension - Define this class BEFORE using it
class MYPDF extends TCPDF {
    // Page header
    public function Header() {
        // Logo
        $image_file = 'assets/img/logo.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, 10, 10, 50, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        } else {
            // If logo not found, use text instead
            $this->SetFont('helvetica', 'B', 20);
            $this->Cell(0, 15, 'Wall Street Financial Solutions', 0, false, 'L', 0, '', 0, false, 'M', 'M');
        }
        
        $this->SetFont('helvetica', 'I', 10);
        $this->SetY(20);
        $this->Cell(0, 10, 'Biashara Street, Nairobi, Kenya', 0, false, 'L', 0, '', 0, false, 'T', 'M');
        
        // Line
        $this->SetY(30);
        $this->Line(10, 30, $this->getPageWidth() - 10, 30);
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        // Date
        $this->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

// Get current user info
$user_id = $_SESSION["user_id"];

// Get user details
$sql = "SELECT first_name, last_name, email FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Default date range (current month)
$current_month = date('m');
$current_year = date('Y');
$default_start_date = "$current_year-$current_month-01";
$default_end_date = date('Y-m-t', strtotime($default_start_date));

// Process form submission
$report_type = 'summary';
$start_date = $default_start_date;
$end_date = $default_end_date;
$status_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get report parameters
    $report_type = $_POST['report_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // Validate date range
    if (strtotime($start_date) > strtotime($end_date)) {
        $status_message = '<div class="alert alert-danger">Start date cannot be after end date.</div>';
    } else if (isset($_POST['generate_pdf'])) {
        // Generate PDF report
        generatePDFReport($conn, $user_id, $user_data, $report_type, $start_date, $end_date);
    }
}

// Function to get financial summary data
function getFinancialSummary($conn, $user_id, $start_date, $end_date) {
    // Get total income
    $income_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM income 
                   WHERE user_id = ? AND income_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($income_sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $income_data = $result->fetch_assoc();
    $total_income = $income_data['total'];
    
    // Get total expenses
    $expense_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
                    WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($expense_sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
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
function getExpensesByCategory($conn, $user_id, $start_date, $end_date) {
    $sql = "SELECT ec.name as category, COALESCE(SUM(e.amount), 0) as total
            FROM expense_categories ec
            LEFT JOIN expenses e ON ec.category_id = e.category_id 
                AND e.expense_date BETWEEN ? AND ?
                AND e.user_id = ?
            WHERE ec.user_id = ? OR ec.user_id IS NULL
            GROUP BY ec.category_id
            ORDER BY total DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $start_date, $end_date, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $expenses_by_category = [];
    
    while ($row = $result->fetch_assoc()) {
        if ($row['total'] > 0) {
            $expenses_by_category[] = $row;
        }
    }
    
    return $expenses_by_category;
}

// Function to get income breakdown by category
function getIncomeByCategory($conn, $user_id, $start_date, $end_date) {
    $sql = "SELECT ic.name as category, COALESCE(SUM(i.amount), 0) as total
            FROM income_categories ic
            LEFT JOIN income i ON ic.category_id = i.category_id 
                AND i.income_date BETWEEN ? AND ?
                AND i.user_id = ?
            WHERE ic.user_id = ? OR ic.user_id IS NULL
            GROUP BY ic.category_id
            ORDER BY total DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $start_date, $end_date, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $income_by_category = [];
    
    while ($row = $result->fetch_assoc()) {
        if ($row['total'] > 0) {
            $income_by_category[] = $row;
        }
    }
    
    return $income_by_category;
}

// Function to get budget status
function getBudgetStatus($conn, $user_id, $start_date, $end_date) {
    $sql = "SELECT ec.name as category, b.amount as budget_amount, 
            COALESCE(SUM(e.amount), 0) as spent_amount
            FROM budgets b
            JOIN expense_categories ec ON b.category_id = ec.category_id
            LEFT JOIN expenses e ON ec.category_id = e.category_id 
                AND e.expense_date BETWEEN ? AND ?
                AND e.user_id = ?
            WHERE b.user_id = ? AND b.period_start <= ? AND b.period_end >= ?
            GROUP BY b.category_id, ec.name, b.amount
            ORDER BY ec.name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiiss", $start_date, $end_date, $user_id, $user_id, $end_date, $start_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $budget_data = [];
    
    while ($row = $result->fetch_assoc()) {
        $percentage = ($row['budget_amount'] > 0) ? ($row['spent_amount'] / $row['budget_amount']) * 100 : 0;
        $budget_data[] = [
            'category' => $row['category'],
            'budget' => $row['budget_amount'],
            'spent' => $row['spent_amount'],
            'remaining' => $row['budget_amount'] - $row['spent_amount'],
            'percentage' => min($percentage, 100)
        ];
    }
    
    return $budget_data;
}

// Function to get transactions
function getTransactions($conn, $user_id, $start_date, $end_date) {
    // Get expenses
    $expense_sql = "SELECT 'expense' as type, e.amount, e.description, e.expense_date as transaction_date, 
                   ec.name as category
                   FROM expenses e
                   JOIN expense_categories ec ON e.category_id = ec.category_id
                   WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
                   ORDER BY e.expense_date DESC, e.expense_id DESC";
    
    $stmt = $conn->prepare($expense_sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $expense_result = $stmt->get_result();
    
    $expenses = [];
    while ($row = $expense_result->fetch_assoc()) {
        $expenses[] = $row;
    }
    
    // Get income
    $income_sql = "SELECT 'income' as type, i.amount, i.description, i.income_date as transaction_date, 
                  ic.name as category
                  FROM income i
                  JOIN income_categories ic ON i.category_id = ic.category_id
                  WHERE i.user_id = ? AND i.income_date BETWEEN ? AND ?
                  ORDER BY i.income_date DESC, i.income_id DESC";
    
    $stmt = $conn->prepare($income_sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
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
    
    return $transactions;
}

// Function to get monthly trend data
function getMonthlyTrendData($conn, $user_id, $start_date, $end_date) {
    // Calculate number of months between dates
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $months = ($interval->y * 12) + $interval->m + 1;
    
    // Cap at 12 months to avoid excessive data
    $months = min($months, 12);
    
    $trend_data = [];
    for ($i = 0; $i < $months; $i++) {
        $month_date = date('Y-m-01', strtotime($start_date . " +$i months"));
        $month_start = $month_date;
        $month_end = date('Y-m-t', strtotime($month_date));
        
        // Stop if we're past the end date
        if (strtotime($month_start) > strtotime($end_date)) {
            break;
        }
        
        // Adjust if month extends beyond end date
        if (strtotime($month_end) > strtotime($end_date)) {
            $month_end = $end_date;
        }
        
        $month_name = date('M Y', strtotime($month_date));
        
        // Get income for month
        $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM income 
                WHERE user_id = ? AND income_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $month_start, $month_end);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_income = $row['total'];
        
        // Get expenses for month
        $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
                WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $month_start, $month_end);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_expense = $row['total'];
        
        $trend_data[] = [
            'month' => $month_name,
            'income' => $monthly_income,
            'expenses' => $monthly_expense,
            'savings' => $monthly_income - $monthly_expense
        ];
    }
    
    return $trend_data;
}

// Function to generate PDF report
function generatePDFReport($conn, $user_id, $user_data, $report_type, $start_date, $end_date) {
    // Create new PDF document
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Wall Street Financial Solutions');
    $pdf->SetAuthor('Wall Street Financial Solutions');
    $pdf->SetTitle('Financial Report');
    $pdf->SetSubject('Financial Report: ' . date('Y-m-d', strtotime($start_date)) . ' to ' . date('Y-m-d', strtotime($end_date)));
    
    // Set default header/footer data
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, 40, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Get report data
    $summary = getFinancialSummary($conn, $user_id, $start_date, $end_date);
    
    // Define colors
    $headerBackground = array(41, 128, 185);
    $headerText = array(255, 255, 255);
    $lightGray = array(245, 245, 245);
    $darkText = array(44, 62, 80);
    $greenText = array(39, 174, 96);
    $redText = array(231, 76, 60);
    
    // Report title and date range
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Financial Report', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 6, 'Period: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)), 0, 1, 'C');
    
    // User info
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'Client Information:', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(30, 6, 'Name:', 0, 0, 'L');
    $pdf->Cell(0, 6, $user_data['first_name'] . ' ' . $user_data['last_name'], 0, 1, 'L');
    
    $pdf->Cell(30, 6, 'Email:', 0, 0, 'L');
    $pdf->Cell(0, 6, $user_data['email'], 0, 1, 'L');
    
    $pdf->Ln(5);
    
    // Financial Summary Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetFillColor(41, 128, 185);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, 'Financial Summary', 0, 1, 'L', 1);
    
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Ln(5);
    
    // Summary table
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(60, 10, 'Metric', 1, 0, 'L', 1);
    $pdf->Cell(60, 10, 'Amount (KSh)', 1, 0, 'R', 1);
    $pdf->Cell(60, 10, 'Notes', 1, 1, 'L', 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 8, 'Total Income', 1, 0, 'L');
    $pdf->SetTextColor(39, 174, 96);
    $pdf->Cell(60, 8, number_format($summary['income'], 2), 1, 0, 'R');
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(60, 8, 'All income sources', 1, 1, 'L');
    
    $pdf->Cell(60, 8, 'Total Expenses', 1, 0, 'L');
    $pdf->SetTextColor(231, 76, 60);
    $pdf->Cell(60, 8, number_format($summary['expenses'], 2), 1, 0, 'R');
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(60, 8, 'All expenses', 1, 1, 'L');
    
    $pdf->Cell(60, 8, 'Net Savings', 1, 0, 'L');
    $textColor = ($summary['savings'] >= 0) ? $greenText : $redText;
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Cell(60, 8, number_format($summary['savings'], 2), 1, 0, 'R');
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(60, 8, number_format($summary['savings_percentage'], 1) . '% of income', 1, 1, 'L');
    
    $pdf->Ln(5);
    
    // Based on report type, add more details
    if ($report_type == 'detailed' || $report_type == 'summary') {
        // Income by Category
        $income_by_category = getIncomeByCategory($conn, $user_id, $start_date, $end_date);
        
        if (count($income_by_category) > 0) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetFillColor(39, 174, 96);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 10, 'Income by Category', 0, 1, 'L', 1);
            
            $pdf->SetTextColor(44, 62, 80);
            $pdf->Ln(5);
            
            // Table header
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(90, 10, 'Category', 1, 0, 'L', 1);
            $pdf->Cell(90, 10, 'Amount (KSh)', 1, 1, 'R', 1);
            
            // Table data
            $pdf->SetFont('helvetica', '', 10);
            $fill = false;
            
            foreach ($income_by_category as $category) {
                $pdf->Cell(90, 8, $category['category'], 1, 0, 'L', $fill);
                $pdf->Cell(90, 8, number_format($category['total'], 2), 1, 1, 'R', $fill);
                $fill = !$fill;
            }
            
            $pdf->Ln(5);
        }
        
        // Expenses by Category
        $expenses_by_category = getExpensesByCategory($conn, $user_id, $start_date, $end_date);
        
        if (count($expenses_by_category) > 0) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetFillColor(231, 76, 60);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 10, 'Expenses by Category', 0, 1, 'L', 1);
            
            $pdf->SetTextColor(44, 62, 80);
            $pdf->Ln(5);
            
            // Table header
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(90, 10, 'Category', 1, 0, 'L', 1);
            $pdf->Cell(90, 10, 'Amount (KSh)', 1, 1, 'R', 1);
            
            // Table data
            $pdf->SetFont('helvetica', '', 10);
            $fill = false;
            
            foreach ($expenses_by_category as $category) {
                $pdf->Cell(90, 8, $category['category'], 1, 0, 'L', $fill);
                $pdf->Cell(90, 8, number_format($category['total'], 2), 1, 1, 'R', $fill);
                $fill = !$fill;
            }
            
            $pdf->Ln(5);
        }
        
        // Budget Status
        $budget_status = getBudgetStatus($conn, $user_id, $start_date, $end_date);
        
        if (count($budget_status) > 0) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetFillColor(243, 156, 18);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 10, 'Budget Status', 0, 1, 'L', 1);
            
            $pdf->SetTextColor(44, 62, 80);
            $pdf->Ln(5);
            
            // Table header
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(45, 10, 'Category', 1, 0, 'L', 1);
            $pdf->Cell(40, 10, 'Budget (KSh)', 1, 0, 'R', 1);
            $pdf->Cell(40, 10, 'Spent (KSh)', 1, 0, 'R', 1);
            $pdf->Cell(40, 10, 'Remaining (KSh)', 1, 0, 'R', 1);
            $pdf->Cell(25, 10, 'Usage (%)', 1, 1, 'R', 1);
            
            // Table data
            $pdf->SetFont('helvetica', '', 10);
            $fill = false;
            
            foreach ($budget_status as $budget) {
                $pdf->Cell(45, 8, $budget['category'], 1, 0, 'L', $fill);
                $pdf->Cell(40, 8, number_format($budget['budget'], 2), 1, 0, 'R', $fill);
                $pdf->Cell(40, 8, number_format($budget['spent'], 2), 1, 0, 'R', $fill);
                $pdf->Cell(40, 8, number_format($budget['remaining'], 2), 1, 0, 'R', $fill);
                
                // Set color based on percentage
                if ($budget['percentage'] > 90) {
                    $pdf->SetTextColor(231, 76, 60);
                } else if ($budget['percentage'] > 70) {
                    $pdf->SetTextColor(243, 156, 18);
                } else {
                    $pdf->SetTextColor(39, 174, 96);
                }
                
                $pdf->Cell(25, 8, number_format($budget['percentage'], 1) . '%', 1, 1, 'R', $fill);
                $pdf->SetTextColor(44, 62, 80);
                
                $fill = !$fill;
            }
            
            $pdf->Ln(5);
        }
    }
    
    // If detailed report, add transaction listing
    if ($report_type == 'detailed') {
        // Add a new page for transactions
        $pdf->AddPage();
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetFillColor(41, 128, 185);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 10, 'Transaction Details', 0, 1, 'L', 1);
        
        $pdf->SetTextColor(44, 62, 80);
        $pdf->Ln(5);
        
        $transactions = getTransactions($conn, $user_id, $start_date, $end_date);
        
        if (count($transactions) > 0) {
            // Table header
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(30, 10, 'Date', 1, 0, 'L', 1);
            $pdf->Cell(30, 10, 'Type', 1, 0, 'L', 1);
            $pdf->Cell(40, 10, 'Category', 1, 0, 'L', 1);
            $pdf->Cell(60, 10, 'Description', 1, 0, 'L', 1);
            $pdf->Cell(30, 10, 'Amount (KSh)', 1, 1, 'R', 1);
            
            // Table data
            $pdf->SetFont('helvetica', '', 10);
            $fill = false;
            
            foreach ($transactions as $transaction) {
                $pdf->Cell(30, 8, date('Y-m-d', strtotime($transaction['transaction_date'])), 1, 0, 'L', $fill);
                
                if ($transaction['type'] == 'income') {
                    $pdf->SetTextColor(39, 174, 96);
                    $pdf->Cell(30, 8, 'Income', 1, 0, 'L', $fill);
                } else {
                    $pdf->SetTextColor(231, 76, 60);
                    $pdf->Cell(30, 8, 'Expense', 1, 0, 'L', $fill);
                }
                
                $pdf->SetTextColor(44, 62, 80);
                $pdf->Cell(40, 8, $transaction['category'], 1, 0, 'L', $fill);
                
                // Handle long descriptions
                $description = $transaction['description'];
                if (strlen($description) > 30) {
                    $description = substr($description, 0, 27) . '...';
                }
                $pdf->Cell(60, 8, $description, 1, 0, 'L', $fill);
                
                if ($transaction['type'] == 'income') {
                    $pdf->SetTextColor(39, 174, 96);
                    $pdf->Cell(30, 8, number_format($transaction['amount'], 2), 1, 1, 'R', $fill);
                } else {
                    $pdf->SetTextColor(231, 76, 60);
                    $pdf->Cell(30, 8, number_format($transaction['amount'], 2), 1, 1, 'R', $fill);
                }
                
                $pdf->SetTextColor(44, 62, 80);
                $fill = !$fill;
            }
        } else {
            $pdf->Cell(0, 10, 'No transactions found for the selected period.', 0, 1, 'L');
        }
    }
    
    // If trend report, add monthly trend data
    if ($report_type == 'trend') {
        $trend_data = getMonthlyTrendData($conn, $user_id, $start_date, $end_date);
        
        if (count($trend_data) > 0) {
            $pdf->AddPage();
            
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetFillColor(41, 128, 185);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 10, 'Monthly Financial Trends', 0, 1, 'L', 1);
            
            $pdf->SetTextColor(44, 62, 80);
            $pdf->Ln(5);
            
           // Table header
           $pdf->SetFont('helvetica', 'B', 11);
           $pdf->SetFillColor(240, 240, 240);
           $pdf->Cell(40, 10, 'Month', 1, 0, 'L', 1);
           $pdf->Cell(50, 10, 'Income (KSh)', 1, 0, 'R', 1);
           $pdf->Cell(50, 10, 'Expenses (KSh)', 1, 0, 'R', 1);
           $pdf->Cell(50, 10, 'Savings (KSh)', 1, 1, 'R', 1);
           
           // Table data
           $pdf->SetFont('helvetica', '', 10);
           $fill = false;
           
           foreach ($trend_data as $month_data) {
               $pdf->Cell(40, 8, $month_data['month'], 1, 0, 'L', $fill);
               
               $pdf->SetTextColor(39, 174, 96);
               $pdf->Cell(50, 8, number_format($month_data['income'], 2), 1, 0, 'R', $fill);
               
               $pdf->SetTextColor(231, 76, 60);
               $pdf->Cell(50, 8, number_format($month_data['expenses'], 2), 1, 0, 'R', $fill);
               
               if ($month_data['savings'] >= 0) {
                   $pdf->SetTextColor(39, 174, 96);
               } else {
                   $pdf->SetTextColor(231, 76, 60);
               }
               $pdf->Cell(50, 8, number_format($month_data['savings'], 2), 1, 1, 'R', $fill);
               
               $pdf->SetTextColor(44, 62, 80);
               $fill = !$fill;
           }
       }
   }
   
   // Output the PDF
   $filename = 'financial_report_' . date('Ymd') . '.pdf';
   $pdf->Output($filename, 'D'); // 'D' means download
   exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Financial Reports - Wall Street Financial Solutions</title>
   <!-- Bootstrap CSS -->
   <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
   
   <!-- Date picker CSS -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
</head>
<body>
   <!-- Include navbar -->
   <?php include 'partials/header.php'; ?>
   <?php include 'partials/sidebar.php'; ?>
   
   <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
       <div class="row">
           <div class="col-md-12">
               <div class="card shadow-sm">
                   <div class="card-header bg-primary text-white">
                       <h4 class="mb-0">Financial Reports</h4>
                   </div>
                   <div class="card-body">
                       <?php if (!empty($status_message)) echo $status_message; ?>
                       
                       <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                           <div class="row">
                               <div class="col-md-4">
                                   <div class="form-group">
                                       <label for="report_type">Report Type</label>
                                       <select class="form-control" id="report_type" name="report_type" required>
                                           <option value="summary" <?php if ($report_type == 'summary') echo 'selected'; ?>>Summary Report</option>
                                           <option value="detailed" <?php if ($report_type == 'detailed') echo 'selected'; ?>>Detailed Report</option>
                                           <option value="trend" <?php if ($report_type == 'trend') echo 'selected'; ?>>Trend Analysis</option>
                                       </select>
                                   </div>
                               </div>
                               <div class="col-md-4">
                                   <div class="form-group">
                                       <label for="start_date">Start Date</label>
                                       <input type="text" class="form-control datepicker" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                                   </div>
                               </div>
                               <div class="col-md-4">
                                   <div class="form-group">
                                       <label for="end_date">End Date</label>
                                       <input type="text" class="form-control datepicker" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                                   </div>
                               </div>
                           </div>
                           
                           <div class="form-group mt-3">
                               <button type="submit" name="generate_pdf" class="btn btn-primary">
                                   <i class="fas fa-file-pdf"></i> Generate PDF Report
                               </button>
                           </div>
                       </form>
                   </div>
               </div>
               
               <!-- Preview Section -->
               <div class="card shadow-sm mt-4">
                   <div class="card-header bg-secondary text-white">
                       <h5 class="mb-0">Report Preview</h5>
                   </div>
                   <div class="card-body">
                       <h4 class="text-center">Financial Report</h4>
                       <p class="text-center">Period: <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></p>
                       
                       <?php 
                       // Get financial summary for preview
                       $summary = getFinancialSummary($conn, $user_id, $start_date, $end_date);
                       ?>
                       
                       <div class="table-responsive mt-4">
                           <h5>Financial Summary</h5>
                           <table class="table table-bordered">
                               <thead class="thead-light">
                                   <tr>
                                       <th>Metric</th>
                                       <th class="text-right">Amount (KSh)</th>
                                       <th>Notes</th>
                                   </tr>
                               </thead>
                               <tbody>
                                   <tr>
                                       <td>Total Income</td>
                                       <td class="text-right text-success"><?php echo number_format($summary['income'], 2); ?></td>
                                       <td>All income sources</td>
                                   </tr>
                                   <tr>
                                       <td>Total Expenses</td>
                                       <td class="text-right text-danger"><?php echo number_format($summary['expenses'], 2); ?></td>
                                       <td>All expenses</td>
                                   </tr>
                                   <tr>
                                       <td>Net Savings</td>
                                       <td class="text-right <?php echo ($summary['savings'] >= 0) ? 'text-success' : 'text-danger'; ?>">
                                           <?php echo number_format($summary['savings'], 2); ?>
                                       </td>
                                       <td><?php echo number_format($summary['savings_percentage'], 1); ?>% of income</td>
                                   </tr>
                               </tbody>
                           </table>
                       </div>
                       
                       <?php 
                       if ($report_type == 'trend') {
                           // Get trend data for preview
                           $trend_data = getMonthlyTrendData($conn, $user_id, $start_date, $end_date);
                           
                           if (count($trend_data) > 0) {
                               ?>
                               <div class="table-responsive mt-4">
                                   <h5>Monthly Financial Trends</h5>
                                   <table class="table table-bordered table-sm">
                                       <thead class="thead-light">
                                           <tr>
                                               <th>Month</th>
                                               <th class="text-right">Income (KSh)</th>
                                               <th class="text-right">Expenses (KSh)</th>
                                               <th class="text-right">Savings (KSh)</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php foreach ($trend_data as $month_data): ?>
                                           <tr>
                                               <td><?php echo $month_data['month']; ?></td>
                                               <td class="text-right text-success"><?php echo number_format($month_data['income'], 2); ?></td>
                                               <td class="text-right text-danger"><?php echo number_format($month_data['expenses'], 2); ?></td>
                                               <td class="text-right <?php echo ($month_data['savings'] >= 0) ? 'text-success' : 'text-danger'; ?>">
                                                   <?php echo number_format($month_data['savings'], 2); ?>
                                               </td>
                                           </tr>
                                           <?php endforeach; ?>
                                       </tbody>
                                   </table>
                               </div>
                               <?php
                           }
                       }
                       ?>
                       
                       <div class="alert alert-info mt-4">
                           <i class="fas fa-info-circle"></i> The PDF report will contain more detailed information and styling. Click "Generate PDF Report" to download the complete report.
                       </div>
                   </div>
               </div>
           </div>
       </div>
    </main>
  </div>
</div>
   
      
   <!-- Bootstrap and jQuery JS -->
   <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
   <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
   
   <!-- Datepicker JS -->
   <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
   
   <script>
       $(document).ready(function() {
           // Initialize datepicker
           $('.datepicker').datepicker({
               format: 'yyyy-mm-dd',
               autoclose: true,
               todayHighlight: true
           });
       });
   </script>
</body>
</html>