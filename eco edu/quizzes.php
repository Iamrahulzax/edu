<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : null;

// Get quizzes
$quizzes = getQuizzes($category_filter, 20);
$categories = getCategories();

// Filter by difficulty if specified
if ($difficulty_filter && !empty($quizzes)) {
    $quizzes = array_filter($quizzes, function($quiz) use ($difficulty_filter) {
        return $quiz['difficulty_level'] === $difficulty_filter;
    });
}

// Get user's quiz attempts
$stmt = $pdo->prepare("SELECT quiz_id, MAX(score) as best_score, COUNT(*) as attempts FROM quiz_attempts WHERE user_id = ? GROUP BY quiz_id");
$stmt->execute([$user['id']]);
$user_attempts = [];
while ($row = $stmt->fetch()) {
    $user_attempts[$row['quiz_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes - EcoEdu</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-theme="light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-success fixed-top">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="index.php">
                <i class="fas fa-leaf me-2"></i>EcoEdu
            </a>
            
            <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="learn.php">
                            <i class="fas fa-book me-1"></i>Learn
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="quizzes.php">
                            <i class="fas fa-question-circle me-1"></i>Quizzes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="games.php">
                            <i class="fas fa-gamepad me-1"></i>Games
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="challenges.php">
                            <i class="fas fa-trophy me-1"></i>Challenges
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="leaderboard.php">
                            <i class="fas fa-medal me-1"></i>Leaderboard
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-mdb-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-sm btn-outline-light ms-2" onclick="toggleTheme()">
                            <i class="fas fa-moon" id="theme-icon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-5 mt-4">
        <div class="container">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="text-gradient"><i class="fas fa-question-circle me-2"></i>Environmental Quizzes</h1>
                            <p class="text-muted">Test your knowledge and earn eco-points!</p>
                        </div>
                        <div class="text-end">
                            <div class="eco-points">
                                <i class="fas fa-coins me-1"></i>
                                <?php echo number_format($user['eco_points']); ?> Points
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-4">
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
                                <div class="col-md-4">
                                    <label for="difficulty" class="form-label">Difficulty</label>
                                    <select class="form-select" id="difficulty" name="difficulty">
                                        <option value="">All Levels</option>
                                        <option value="easy" <?php echo $difficulty_filter === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                        <option value="medium" <?php echo $difficulty_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="hard" <?php echo $difficulty_filter === 'hard' ? 'selected' : ''; ?>>Hard</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                    <a href="quizzes.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quiz Stats -->
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon text-primary">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="stat-value text-primary"><?php echo count($quizzes); ?></div>
                        <div class="stat-label">Available Quizzes</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value text-success"><?php echo count($user_attempts); ?></div>
                        <div class="stat-label">Quizzes Attempted</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon text-warning">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-value text-warning">
                            <?php 
                            $passed_count = 0;
                            foreach ($user_attempts as $attempt) {
                                if ($attempt['best_score'] >= 70) $passed_count++;
                            }
                            echo $passed_count;
                            ?>
                        </div>
                        <div class="stat-label">Quizzes Passed</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon text-info">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-value text-info">
                            <?php 
                            if (count($user_attempts) > 0) {
                                echo round(($passed_count / count($user_attempts)) * 100);
                            } else {
                                echo '0';
                            }
                            ?>%
                        </div>
                        <div class="stat-label">Success Rate</div>
                    </div>
                </div>
            </div>

            <!-- Quizzes Grid -->
            <div class="row">
                <?php if (empty($quizzes)): ?>
                <div class="col-12">
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-question-circle fa-4x text-muted mb-4"></i>
                            <h3 class="text-muted">No Quizzes Found</h3>
                            <p class="text-muted">Try adjusting your filters or check back later for new quizzes.</p>
                            <a href="quizzes.php" class="btn btn-primary">View All Quizzes</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($quizzes as $quiz): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card quiz-card h-100 card-hover-effect">
                        <div class="card-body">
                            <!-- Quiz Header -->
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <h5 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                                    <?php if ($quiz['category_name']): ?>
                                    <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($quiz['category_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="difficulty-badge">
                                    <span class="badge difficulty-<?php echo $quiz['difficulty_level']; ?>">
                                        <?php echo ucfirst($quiz['difficulty_level']); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Quiz Description -->
                            <p class="card-text text-muted"><?php echo htmlspecialchars($quiz['description']); ?></p>

                            <!-- Quiz Info -->
                            <div class="quiz-info mb-3">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <i class="fas fa-question text-primary"></i>
                                        <small class="d-block text-muted"><?php echo $quiz['total_questions']; ?> Questions</small>
                                    </div>
                                    <div class="col-4">
                                        <i class="fas fa-clock text-warning"></i>
                                        <small class="d-block text-muted"><?php echo floor($quiz['time_limit'] / 60); ?>m</small>
                                    </div>
                                    <div class="col-4">
                                        <i class="fas fa-coins text-success"></i>
                                        <small class="d-block text-muted"><?php echo $quiz['total_questions'] * $quiz['points_per_question']; ?> pts</small>
                                    </div>
                                </div>
                            </div>

                            <!-- User Progress -->
                            <?php if (isset($user_attempts[$quiz['id']])): ?>
                            <div class="user-progress mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">Best Score:</small>
                                    <span class="badge <?php echo $user_attempts[$quiz['id']]['best_score'] >= $quiz['pass_percentage'] ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo round($user_attempts[$quiz['id']]['best_score']); ?>%
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar <?php echo $user_attempts[$quiz['id']]['best_score'] >= $quiz['pass_percentage'] ? 'bg-success' : 'bg-warning'; ?>" 
                                         style="width: <?php echo $user_attempts[$quiz['id']]['best_score']; ?>%"></div>
                                </div>
                                <small class="text-muted">Attempts: <?php echo $user_attempts[$quiz['id']]['attempts']; ?></small>
                            </div>
                            <?php endif; ?>

                            <!-- Action Button -->
                            <div class="d-grid">
                                <?php if (isset($user_attempts[$quiz['id']]) && $user_attempts[$quiz['id']]['best_score'] >= $quiz['pass_percentage']): ?>
                                <a href="quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-redo me-2"></i>Retake Quiz
                                </a>
                                <?php else: ?>
                                <a href="quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-play me-2"></i><?php echo isset($user_attempts[$quiz['id']]) ? 'Continue' : 'Start'; ?> Quiz
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination (if needed) -->
            <?php if (count($quizzes) >= 20): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <nav aria-label="Quiz pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1">Previous</a>
                            </li>
                            <li class="page-item active">
                                <a class="page-link" href="#">1</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="#">2</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="#">3</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
