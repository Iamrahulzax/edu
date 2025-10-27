<?php
require_once 'admin_header.php';
// $user is already available from admin_header.php

// Handle actions
$message = '';
$message_type = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_quiz':
                $title = sanitizeInput($_POST['title']);
                $description = sanitizeInput($_POST['description']);
                $category_id = (int)$_POST['category_id'];
                $difficulty_level = $_POST['difficulty_level'];
                $time_limit = (int)$_POST['time_limit'];
                $points_per_question = (int)$_POST['points_per_question'];
                $pass_percentage = (float)$_POST['pass_percentage'];
                
                $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, category_id, difficulty_level, time_limit, points_per_question, pass_percentage, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $description, $category_id, $difficulty_level, $time_limit, $points_per_question, $pass_percentage, $_SESSION['user_id']])) {
                    $quiz_id = $pdo->lastInsertId();
                    $message = 'Quiz created successfully! Now add questions.';
                    $message_type = 'success';
                    header("Location: quiz_questions.php?quiz_id=$quiz_id");
                    exit;
                } else {
                    $message = 'Failed to create quiz';
                    $message_type = 'error';
                }
                break;
                
            case 'toggle_status':
                $quiz_id = (int)$_POST['quiz_id'];
                $stmt = $pdo->prepare("UPDATE quizzes SET is_active = NOT is_active WHERE id = ?");
                if ($stmt->execute([$quiz_id])) {
                    $message = 'Quiz status updated successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update quiz status';
                    $message_type = 'error';
                }
                break;
                
            case 'delete_quiz':
                $quiz_id = (int)$_POST['quiz_id'];
                $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
                if ($stmt->execute([$quiz_id])) {
                    $message = 'Quiz deleted successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to delete quiz';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where_conditions = [];
$params = [];

if ($category_filter) {
    $where_conditions[] = "q.category_id = ?";
    $params[] = $category_filter;
}

if ($difficulty_filter) {
    $where_conditions[] = "q.difficulty_level = ?";
    $params[] = $difficulty_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "q.is_active = ?";
    $params[] = $status_filter === '1' ? 1 : 0;
}

if ($search) {
    $where_conditions[] = "(q.title LIKE ? OR q.description LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get quizzes
$query = "SELECT q.*, c.name as category_name, u.first_name, u.last_name, 
          (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
          (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count
          FROM quizzes q 
          LEFT JOIN categories c ON q.category_id = c.id 
          LEFT JOIN users u ON q.created_by = u.id 
          $where_clause 
          ORDER BY q.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$quizzes = $stmt->fetchAll();

// Get categories
$categories = getCategories();

// Get quiz statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_quizzes,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_quizzes,
    SUM(CASE WHEN difficulty_level = 'easy' THEN 1 ELSE 0 END) as easy_quizzes,
    SUM(CASE WHEN difficulty_level = 'medium' THEN 1 ELSE 0 END) as medium_quizzes,
    SUM(CASE WHEN difficulty_level = 'hard' THEN 1 ELSE 0 END) as hard_quizzes
    FROM quizzes");
$stmt->execute();
$quiz_stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Management - EcoEdu Admin</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .admin-sidebar {
            background: linear-gradient(135deg, #343a40 0%, #495057 100%);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        .admin-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        .sidebar-toggle {
            display: none;
        }
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-sidebar.show {
                transform: translateX(0);
            }
            .admin-content {
                margin-left: 0;
            }
            .sidebar-toggle {
                display: block;
            }
        }
        .quiz-card {
            transition: all 0.3s ease;
        }
        .quiz-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body data-theme="light">
    <!-- Admin Sidebar -->
    <div class="admin-sidebar" id="adminSidebar">
        <div class="p-4">
            <div class="text-center mb-4">
                <i class="fas fa-leaf text-success fa-3x mb-2"></i>
                <h5 class="text-white">EcoEdu Admin</h5>
                <small class="text-light">Welcome, <?php echo htmlspecialchars($user['first_name']); ?></small>
            </div>
            
            <nav class="nav flex-column">
                <a class="nav-link text-white" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a class="nav-link text-white" href="users.php">
                    <i class="fas fa-users-cog me-2"></i>Users Management
                </a>
                <a class="nav-link text-white" href="content.php">
                    <i class="fas fa-book me-2"></i>Learning Content
                </a>
                <a class="nav-link text-white active" href="quizzes.php">
                    <i class="fas fa-question-circle me-2"></i>Quizzes
                </a>
                <a class="nav-link text-white" href="challenges.php">
                    <i class="fas fa-trophy me-2"></i>Challenges
                </a>
                <a class="nav-link text-white" href="verifications.php">
                    <i class="fas fa-check-circle me-2"></i>Verifications
                </a>
                <a class="nav-link text-white" href="analytics.php">
                    <i class="fas fa-chart-bar me-2"></i>Analytics
                </a>
                <a class="nav-link text-white" href="settings.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
                <hr class="my-3" style="border-color: #6c757d;">
                <a class="nav-link text-white" href="../dashboard.php">
                    <i class="fas fa-home me-2"></i>Student View
                </a>
                <a class="nav-link text-white" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="admin-content">
        <!-- Top Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <button class="btn btn-outline-secondary sidebar-toggle me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h2 class="mb-0">Quiz Management</h2>
            </div>
            <div>
                <button class="btn btn-success me-2" data-mdb-toggle="modal" data-mdb-target="#createQuizModal">
                    <i class="fas fa-plus me-1"></i>Create Quiz
                </button>
                <button class="btn btn-outline-secondary" onclick="toggleTheme()">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $quiz_stats['total_quizzes']; ?></h4>
                                <p class="mb-0">Total Quizzes</p>
                            </div>
                            <i class="fas fa-question-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $quiz_stats['active_quizzes']; ?></h4>
                                <p class="mb-0">Active Quizzes</p>
                            </div>
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h5><?php echo $quiz_stats['easy_quizzes']; ?></h5>
                        <p class="mb-0 small">Easy</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h5><?php echo $quiz_stats['medium_quizzes']; ?></h5>
                        <p class="mb-0 small">Medium</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h5><?php echo $quiz_stats['hard_quizzes']; ?></h5>
                        <p class="mb-0 small">Hard</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Quiz title or description...">
                    </div>
                    <div class="col-md-2">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="difficulty" class="form-label">Difficulty</label>
                        <select class="form-select" id="difficulty" name="difficulty">
                            <option value="">All Levels</option>
                            <option value="easy" <?php echo $difficulty_filter === 'easy' ? 'selected' : ''; ?>>Easy</option>
                            <option value="medium" <?php echo $difficulty_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="hard" <?php echo $difficulty_filter === 'hard' ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <a href="quizzes.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quizzes Grid -->
        <div class="row">
            <?php if (empty($quizzes)): ?>
            <div class="col-12">
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-question-circle fa-4x text-muted mb-4"></i>
                        <h5 class="text-muted">No quizzes found</h5>
                        <p class="text-muted">Create your first quiz to get started</p>
                        <button class="btn btn-success" data-mdb-toggle="modal" data-mdb-target="#createQuizModal">
                            <i class="fas fa-plus me-2"></i>Create Quiz
                        </button>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($quizzes as $quiz): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card quiz-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" data-mdb-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="quiz_questions.php?quiz_id=<?php echo $quiz['id']; ?>">
                                        <i class="fas fa-edit me-2"></i>Edit Questions
                                    </a></li>
                                    <li><a class="dropdown-item" href="../quiz.php?id=<?php echo $quiz['id']; ?>" target="_blank">
                                        <i class="fas fa-eye me-2"></i>Preview
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" onclick="toggleQuizStatus(<?php echo $quiz['id']; ?>)">
                                        <i class="fas fa-<?php echo $quiz['is_active'] ? 'ban' : 'check'; ?> me-2"></i>
                                        <?php echo $quiz['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </a></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteQuiz(<?php echo $quiz['id']; ?>)">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </a></li>
                                </ul>
                            </div>
                        </div>

                        <p class="card-text text-muted"><?php echo htmlspecialchars($quiz['description']); ?></p>

                        <div class="mb-3">
                            <?php if ($quiz['category_name']): ?>
                            <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($quiz['category_name']); ?></span>
                            <?php endif; ?>
                            <span class="badge difficulty-<?php echo $quiz['difficulty_level']; ?>">
                                <?php echo ucfirst($quiz['difficulty_level']); ?>
                            </span>
                            <span class="badge <?php echo $quiz['is_active'] ? 'bg-success' : 'bg-secondary'; ?> ms-2">
                                <?php echo $quiz['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>

                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <i class="fas fa-question text-primary"></i>
                                <small class="d-block text-muted"><?php echo $quiz['question_count']; ?> Questions</small>
                            </div>
                            <div class="col-4">
                                <i class="fas fa-clock text-warning"></i>
                                <small class="d-block text-muted"><?php echo floor($quiz['time_limit'] / 60); ?>m</small>
                            </div>
                            <div class="col-4">
                                <i class="fas fa-users text-info"></i>
                                <small class="d-block text-muted"><?php echo $quiz['attempt_count']; ?> Attempts</small>
                            </div>
                        </div>

                        <div class="border-top pt-3">
                            <small class="text-muted">
                                Created by <?php echo htmlspecialchars($quiz['first_name'] . ' ' . $quiz['last_name']); ?>
                                <br>on <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-flex gap-2">
                            <a href="quiz_questions.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-primary btn-sm flex-fill">
                                <i class="fas fa-edit me-1"></i>Manage
                            </a>
                            <a href="../quiz.php?id=<?php echo $quiz['id']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Quiz Modal -->
    <div class="modal fade" id="createQuizModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Quiz</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_quiz">
                        
                        <div class="form-outline mb-3">
                            <input type="text" class="form-control" name="title" required>
                            <label class="form-label">Quiz Title</label>
                        </div>
                        
                        <div class="form-outline mb-3">
                            <textarea class="form-control" name="description" rows="3"></textarea>
                            <label class="form-label">Description</label>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-outline mb-3">
                                    <select class="form-select" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="form-label select-label">Category</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-outline mb-3">
                                    <select class="form-select" name="difficulty_level" required>
                                        <option value="easy">Easy</option>
                                        <option value="medium">Medium</option>
                                        <option value="hard">Hard</option>
                                    </select>
                                    <label class="form-label select-label">Difficulty Level</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-outline mb-3">
                                    <input type="number" class="form-control" name="time_limit" value="300" min="60" max="3600">
                                    <label class="form-label">Time Limit (seconds)</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-outline mb-3">
                                    <input type="number" class="form-control" name="points_per_question" value="10" min="1" max="100">
                                    <label class="form-label">Points per Question</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-outline mb-3">
                                    <input type="number" class="form-control" name="pass_percentage" value="70" min="0" max="100" step="0.1">
                                    <label class="form-label">Pass Percentage</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Create Quiz
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden Forms for Actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="quiz_id" id="actionQuizId">
    </form>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            sidebar.classList.toggle('show');
        }
        
        function toggleQuizStatus(quizId) {
            if (confirm('Are you sure you want to change the status of this quiz?')) {
                document.getElementById('actionType').value = 'toggle_status';
                document.getElementById('actionQuizId').value = quizId;
                document.getElementById('actionForm').submit();
            }
        }
        
        function deleteQuiz(quizId) {
            if (confirm('Are you sure you want to delete this quiz? This will also delete all questions and attempts. This action cannot be undone.')) {
                document.getElementById('actionType').value = 'delete_quiz';
                document.getElementById('actionQuizId').value = quizId;
                document.getElementById('actionForm').submit();
            }
        }
    </script>
</body>
</html>
