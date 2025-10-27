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

// Get quiz ID
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$quiz_id) {
    header('Location: quizzes.php');
    exit;
}

// Get quiz details
$stmt = $pdo->prepare("SELECT q.*, c.name as category_name FROM quizzes q LEFT JOIN categories c ON q.category_id = c.id WHERE q.id = ? AND q.is_active = 1");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: quizzes.php?message=' . urlencode('Quiz not found') . '&type=error');
    exit;
}

// Get quiz questions
$questions = getQuizQuestions($quiz_id);
if (empty($questions)) {
    header('Location: quizzes.php?message=' . urlencode('This quiz has no questions yet') . '&type=error');
    exit;
}

// Handle quiz submission
$result = null;
if ($_POST && isset($_POST['submit_quiz'])) {
    $answers = [];
    foreach ($questions as $question) {
        if (isset($_POST['question_' . $question['id']])) {
            $answers[$question['id']] = $_POST['question_' . $question['id']];
        }
    }
    
    $result = submitQuizAttempt($user['id'], $quiz_id, $answers);
}

// Get user's previous attempts
$stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? ORDER BY completed_at DESC LIMIT 5");
$stmt->execute([$user['id'], $quiz_id]);
$previous_attempts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?> - EcoEdu</title>
    
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
            
            <div class="ms-auto d-flex align-items-center">
                <?php if (!$result): ?>
                <div class="quiz-timer text-white me-3" data-time-limit="<?php echo $quiz['time_limit']; ?>">
                    <i class="fas fa-clock me-1"></i>
                    <span id="timer-display"><?php echo floor($quiz['time_limit'] / 60); ?>:<?php echo str_pad($quiz['time_limit'] % 60, 2, '0', STR_PAD_LEFT); ?></span>
                </div>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-light" onclick="toggleTheme()">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-5 mt-4">
        <div class="container">
            <?php if ($result): ?>
            <!-- Quiz Results -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-lg">
                        <div class="card-body text-center p-5">
                            <?php if ($result['success']): ?>
                                <div class="mb-4">
                                    <?php if ($result['is_passed']): ?>
                                        <i class="fas fa-trophy text-warning fa-4x mb-3 pulse-animation"></i>
                                        <h2 class="text-success mb-3">Congratulations! 🎉</h2>
                                        <p class="lead">You passed the quiz!</p>
                                    <?php else: ?>
                                        <i class="fas fa-redo text-info fa-4x mb-3"></i>
                                        <h2 class="text-info mb-3">Good Effort! 💪</h2>
                                        <p class="lead">Keep learning and try again!</p>
                                    <?php endif; ?>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-md-3 col-6 mb-3">
                                        <div class="stat-card">
                                            <div class="stat-value text-primary"><?php echo round($result['score']); ?>%</div>
                                            <div class="stat-label">Score</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <div class="stat-card">
                                            <div class="stat-value text-success"><?php echo $result['correct_answers']; ?>/<?php echo $result['total_questions']; ?></div>
                                            <div class="stat-label">Correct</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <div class="stat-card">
                                            <div class="stat-value text-warning"><?php echo $result['points_earned']; ?></div>
                                            <div class="stat-label">Points Earned</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <div class="stat-card">
                                            <div class="stat-value text-info"><?php echo $quiz['pass_percentage']; ?>%</div>
                                            <div class="stat-label">Pass Mark</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-3 justify-content-center flex-wrap">
                                    <a href="quiz.php?id=<?php echo $quiz_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-redo me-2"></i>Retake Quiz
                                    </a>
                                    <a href="quizzes.php" class="btn btn-success">
                                        <i class="fas fa-list me-2"></i>More Quizzes
                                    </a>
                                    <a href="dashboard.php" class="btn btn-outline-success">
                                        <i class="fas fa-home me-2"></i>Dashboard
                                    </a>
                                </div>
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle text-danger fa-4x mb-3"></i>
                                <h2 class="text-danger mb-3">Oops! Something went wrong</h2>
                                <p class="lead"><?php echo htmlspecialchars($result['message']); ?></p>
                                <a href="quiz.php?id=<?php echo $quiz_id; ?>" class="btn btn-primary">Try Again</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Quiz Taking Interface -->
            <div class="row">
                <div class="col-lg-8">
                    <!-- Quiz Header -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h1 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                                    <p class="text-muted"><?php echo htmlspecialchars($quiz['description']); ?></p>
                                    <?php if ($quiz['category_name']): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($quiz['category_name']); ?></span>
                                    <?php endif; ?>
                                    <span class="badge difficulty-<?php echo $quiz['difficulty_level']; ?> ms-2">
                                        <?php echo ucfirst($quiz['difficulty_level']); ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <div class="eco-points mb-2">
                                        <i class="fas fa-coins me-1"></i>
                                        <?php echo $quiz['total_questions'] * $quiz['points_per_question']; ?> Points
                                    </div>
                                    <small class="text-muted">Pass: <?php echo $quiz['pass_percentage']; ?>%</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quiz Form -->
                    <form method="POST" class="quiz-form needs-validation" novalidate>
                        <?php foreach ($questions as $index => $question): ?>
                        <div class="card quiz-question mb-4 fade-in-up" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                            <div class="card-body">
                                <h5 class="mb-3">
                                    <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                    <?php echo htmlspecialchars($question['question']); ?>
                                </h5>
                                
                                <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                    <div class="quiz-options">
                                        <?php 
                                        $options = [
                                            'A' => $question['option_a'],
                                            'B' => $question['option_b'],
                                            'C' => $question['option_c'],
                                            'D' => $question['option_d']
                                        ];
                                        foreach ($options as $key => $option): 
                                            if (!empty($option)):
                                        ?>
                                        <div class="quiz-option" data-value="<?php echo htmlspecialchars($option); ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="option-letter me-3">
                                                    <span class="badge bg-light text-dark"><?php echo $key; ?></span>
                                                </div>
                                                <div class="option-text flex-grow-1">
                                                    <?php echo htmlspecialchars($option); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                    <input type="hidden" name="question_<?php echo $question['id']; ?>" required>
                                
                                <?php elseif ($question['question_type'] === 'true_false'): ?>
                                    <div class="quiz-options">
                                        <div class="quiz-option" data-value="True">
                                            <div class="d-flex align-items-center">
                                                <div class="option-letter me-3">
                                                    <span class="badge bg-success">T</span>
                                                </div>
                                                <div class="option-text flex-grow-1">True</div>
                                            </div>
                                        </div>
                                        <div class="quiz-option" data-value="False">
                                            <div class="d-flex align-items-center">
                                                <div class="option-letter me-3">
                                                    <span class="badge bg-danger">F</span>
                                                </div>
                                                <div class="option-text flex-grow-1">False</div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="question_<?php echo $question['id']; ?>" required>
                                
                                <?php elseif ($question['question_type'] === 'fill_blank'): ?>
                                    <div class="form-outline">
                                        <input type="text" class="form-control form-control-lg" name="question_<?php echo $question['id']; ?>" required>
                                        <label class="form-label">Your Answer</label>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="invalid-feedback">
                                    Please select an answer for this question.
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Submit Button -->
                        <div class="card">
                            <div class="card-body text-center">
                                <button type="submit" name="submit_quiz" class="btn btn-success btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Quiz
                                </button>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        Make sure you've answered all questions before submitting.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Quiz Progress -->
                    <div class="card mb-4 sticky-top" style="top: 100px;">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Quiz Progress</h5>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3" style="height: 20px;">
                                <div class="progress-bar" id="quiz-progress" role="progressbar" style="width: 0%">
                                    <span id="progress-text">0%</span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Questions Answered:</small>
                                <small class="fw-bold"><span id="answered-count">0</span>/<?php echo count($questions); ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- Quiz Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quiz Information</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-question text-primary me-2"></i>
                                    <strong>Questions:</strong> <?php echo $quiz['total_questions']; ?>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-clock text-warning me-2"></i>
                                    <strong>Time Limit:</strong> <?php echo floor($quiz['time_limit'] / 60); ?> minutes
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-coins text-success me-2"></i>
                                    <strong>Points:</strong> <?php echo $quiz['points_per_question']; ?> per question
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-percentage text-info me-2"></i>
                                    <strong>Pass Mark:</strong> <?php echo $quiz['pass_percentage']; ?>%
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Previous Attempts -->
                    <?php if (!empty($previous_attempts)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Previous Attempts</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach (array_slice($previous_attempts, 0, 3) as $attempt): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <div>
                                    <small class="text-muted"><?php echo formatTimeAgo($attempt['completed_at']); ?></small>
                                </div>
                                <div>
                                    <span class="badge <?php echo $attempt['is_passed'] ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo round($attempt['score']); ?>%
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        <?php if ($result && $result['success'] && $result['is_passed']): ?>
        setTimeout(() => {
            showNotification('🎉 Congratulations! You earned <?php echo $result['points_earned']; ?> eco-points!', 'success');
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>
