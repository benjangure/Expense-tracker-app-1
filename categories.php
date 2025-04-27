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
$success_message = $error_message = "";

// Process category operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new category
    if (isset($_POST["add_category"])) {
        $type = $_POST["category_type"];
        $name = trim($_POST["category_name"]);
        $description = trim($_POST["category_description"]);
        
        if (empty($name)) {
            $error_message = "Category name is required.";
        } else {
            $table = ($type == "income") ? "income_categories" : "expense_categories";
            
            // Check if category already exists for this user
            $check_sql = "SELECT * FROM $table WHERE name = ? AND (user_id = ? OR user_id IS NULL)";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("si", $name, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "A category with this name already exists.";
            } else {
                // Insert new category
                $sql = "INSERT INTO $table (user_id, name, description) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $user_id, $name, $description);
                
                if ($stmt->execute()) {
                    $success_message = "Category created successfully!";
                } else {
                    $error_message = "Something went wrong. Please try again later.";
                }
            }
        }
    }
    
    // Edit category
    if (isset($_POST["edit_category"])) {
        $category_id = $_POST["category_id"];
        $type = $_POST["category_type"];
        $name = trim($_POST["category_name"]);
        $description = trim($_POST["category_description"]);
        
        if (empty($name)) {
            $error_message = "Category name is required.";
        } else {
            $table = ($type == "income") ? "income_categories" : "expense_categories";
            
            // Check if this category belongs to the user
            $check_sql = "SELECT * FROM $table WHERE category_id = ? AND user_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ii", $category_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                $error_message = "You can only edit your own categories.";
            } else {
                // Update category
                $sql = "UPDATE $table SET name = ?, description = ? WHERE category_id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssii", $name, $description, $category_id, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Category updated successfully!";
                } else {
                    $error_message = "Something went wrong. Please try again later.";
                }
            }
        }
    }
    
    // Delete category
    if (isset($_POST["delete_category"])) {
        $category_id = $_POST["category_id"];
        $type = $_POST["category_type"];
        
        $table = ($type == "income") ? "income_categories" : "expense_categories";
        $trans_table = ($type == "income") ? "income" : "expenses";
        
        // Check if this category belongs to the user
        $check_sql = "SELECT * FROM $table WHERE category_id = ? AND user_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $category_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error_message = "You can only delete your own categories.";
        } else {
            // Check if category is in use
            $check_use_sql = "SELECT COUNT(*) as count FROM $trans_table WHERE category_id = ?";
            $stmt = $conn->prepare($check_use_sql);
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $use_result = $stmt->get_result();
            $row = $use_result->fetch_assoc();
            
            if ($row["count"] > 0) {
                $error_message = "Cannot delete a category that is in use. Please reassign transactions first.";
            } else {
                // Delete category
                $sql = "DELETE FROM $table WHERE category_id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $category_id, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Category deleted successfully!";
                } else {
                    $error_message = "Something went wrong. Please try again later.";
                }
            }
        }
    }
}

// Function to get categories
function getCategories($conn, $user_id, $type) {
    $table = ($type == "income") ? "income_categories" : "expense_categories";
    $trans_table = ($type == "income") ? "income" : "expenses";
    
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM $trans_table t WHERE t.category_id = c.category_id AND t.user_id = ?) as usage_count,
            CASE WHEN c.user_id IS NULL THEN 'System' ELSE 'User' END as category_type
            FROM $table c
            WHERE c.user_id = ? OR c.user_id IS NULL
            ORDER BY c.name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    return $categories;
}

// Get all categories
$income_categories = getCategories($conn, $user_id, "income");
$expense_categories = getCategories($conn, $user_id, "expense");

// Close connection
$conn->close();

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<!-- Main Content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Category Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-1"></i> Add New Category
            </button>
        </div>
    </div>

    <!-- Alerts -->
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

    <style>
        .category-tab-text {
            color: #333; 
            font-weight: 500; 
        }
        .nav-link.active .category-tab-text {
            color: #0d6efd; 
        }
    </style>

    <ul class="nav nav-tabs mb-4" id="categoryTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="income-tab" data-bs-toggle="tab" data-bs-target="#income-categories" type="button" role="tab" aria-controls="income-categories" aria-selected="true">
                <i class="fas fa-arrow-down text-success me-1"></i>
                <span class="category-tab-text">Income Categories</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="expense-tab" data-bs-toggle="tab" data-bs-target="#expense-categories" type="button" role="tab" aria-controls="expense-categories" aria-selected="false">
                <i class="fas fa-arrow-up text-danger me-1"></i>
                <span class="category-tab-text">Expense Categories</span>
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="categoryTabContent">
        <!-- Income Categories Tab -->
        <div class="tab-pane fade show active" id="income-categories" role="tabpanel" aria-labelledby="income-tab">
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($income_categories as $category): ?>
                <div class="col">
                    <div class="card h-100 border-success">
                        <div class="card-header bg-<?php echo ($category['category_type'] == 'System') ? 'light' : 'success text-white'; ?> d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo htmlspecialchars($category['name']); ?></h5>
                            <span class="badge bg-<?php echo ($category['category_type'] == 'System') ? 'secondary' : 'light text-success'; ?> ">
                                <?php echo $category['category_type']; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <?php echo !empty($category['description']) ? htmlspecialchars($category['description']) : '<em>No description</em>'; ?>
                            </p>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="fas fa-chart-bar me-1"></i> Used in <?php echo $category['usage_count']; ?> transactions
                                </small>
                            </p>
                        </div>
                        <?php if ($category['category_type'] == 'User'): ?>
                        <div class="card-footer bg-white border-top-0">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary flex-grow-1 edit-category" 
                                        data-id="<?php echo $category['category_id']; ?>"
                                        data-type="income"
                                        data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                        data-description="<?php echo htmlspecialchars($category['description']); ?>">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <button type="button" class="btn btn-outline-danger flex-grow-1 delete-category"
                                        data-id="<?php echo $category['category_id']; ?>"
                                        data-type="income"
                                        data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                        <?php echo $category['usage_count'] > 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($income_categories) == 0): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-tag fa-3x text-muted mb-3"></i>
                            <h5>No Income Categories Added Yet</h5>
                            <p>Create income categories to better organize your earnings.</p>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCategoryModal" data-type="income">
                                <i class="fas fa-plus me-1"></i> Add Income Category
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Expense Categories Tab -->
        <div class="tab-pane fade" id="expense-categories" role="tabpanel" aria-labelledby="expense-tab">
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($expense_categories as $category): ?>
                <div class="col">
                    <div class="card h-100 border-danger">
                        <div class="card-header bg-<?php echo ($category['category_type'] == 'System') ? 'light' : 'danger text-white'; ?> d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo htmlspecialchars($category['name']); ?></h5>
                            <span class="badge bg-<?php echo ($category['category_type'] == 'System') ? 'secondary' : 'light text-danger'; ?> ">
                                <?php echo $category['category_type']; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <?php echo !empty($category['description']) ? htmlspecialchars($category['description']) : '<em>No description</em>'; ?>
                            </p>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="fas fa-chart-bar me-1"></i> Used in <?php echo $category['usage_count']; ?> transactions
                                </small>
                            </p>
                        </div>
                        <?php if ($category['category_type'] == 'User'): ?>
                        <div class="card-footer bg-white border-top-0">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary flex-grow-1 edit-category" 
                                        data-id="<?php echo $category['category_id']; ?>"
                                        data-type="expense"
                                        data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                        data-description="<?php echo htmlspecialchars($category['description']); ?>">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <button type="button" class="btn btn-outline-danger flex-grow-1 delete-category"
                                        data-id="<?php echo $category['category_id']; ?>"
                                        data-type="expense"
                                        data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                        <?php echo $category['usage_count'] > 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($expense_categories) == 0): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-tag fa-3x text-muted mb-3"></i>
                            <h5>No Expense Categories Added Yet</h5>
                            <p>Create expense categories to better track your spending.</p>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addCategoryModal" data-type="expense">
                                <i class="fas fa-plus me-1"></i> Add Expense Category
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories.php" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category_type" class="form-label">Category Type</label>
                        <select class="form-select" id="category_type" name="category_type" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="add_category">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories.php" method="post">
                <input type="hidden" id="edit_category_id" name="category_id">
                <input type="hidden" id="edit_category_type" name="category_type">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="edit_category_description" name="category_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="edit_category">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Delete Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories.php" method="post">
                <input type="hidden" id="delete_category_id" name="category_id">
                <input type="hidden" id="delete_category_type" name="category_type">
                <div class="modal-body">
                    <p>Are you sure you want to delete the category <strong id="delete_category_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i> This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" name="delete_category">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
    // Set preset category type in Add Category modal based on active tab
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (event) {
            const categoryType = event.target.id === 'income-tab' ? 'income' : 'expense';
            document.querySelector('#category_type').value = categoryType;
        });
    });
    
    // Pre-select category type when Add Category button is clicked with data-type
    document.querySelectorAll('[data-bs-target="#addCategoryModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            if (type) {
                document.querySelector('#category_type').value = type;
            }
        });
    });
    
    // Set up Edit Category modal when edit button is clicked
    document.querySelectorAll('.edit-category').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            
            document.querySelector('#edit_category_id').value = id;
            document.querySelector('#edit_category_type').value = type;
            document.querySelector('#edit_category_name').value = name;
            document.querySelector('#edit_category_description').value = description;
            
            const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            modal.show();
        });
    });
    
    // Set up Delete Category modal when delete button is clicked
    document.querySelectorAll('.delete-category').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const name = this.getAttribute('data-name');
            
            document.querySelector('#delete_category_id').value = id;
            document.querySelector('#delete_category_type').value = type;
            document.querySelector('#delete_category_name').textContent = name;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
            modal.show();
        });
    });
</script>
