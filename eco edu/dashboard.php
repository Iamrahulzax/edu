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

// Get user stats
$user_badges = getUserBadges($user['id']);
$recent_quizzes = getQuizzes(null, 5);
$recent_challenges = getChallenges(null, 5);
$categories = getCategories();

// Get leaderboard position
$global_leaderboard = getGlobalLeaderboard(100);
$user_rank = 0;
foreach ($global_leaderboard as $index => $leader) {
    if ($leader['id'] == $user['id']) {
        $user_rank = $index + 1;
        break;
    }
}

// Calculate progress to next level
$stmt = $pdo->prepare("SELECT * FROM levels WHERE min_points <= ? ORDER BY min_points DESC LIMIT 1");
$stmt->execute([$user['eco_points']]);
$current_level = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM levels WHERE min_points > ? ORDER BY min_points ASC LIMIT 1");
$stmt->execute([$user['eco_points']]);
$next_level = $stmt->fetch();

$progress_percentage = 100;
$points_to_next = 0;

if ($next_level) {
    $current_level_min = $current_level ? (int)$current_level['min_points'] : 0;
    $next_min = (int)$next_level['min_points'];
    $range = max(1, $next_min - $current_level_min);
    $progress_percentage = (($user['eco_points'] - $current_level_min) / $range) * 100;
    $progress_percentage = min(100, max(0, $progress_percentage));
    $points_to_next = max(0, $next_min - $user['eco_points']);
} else {
    // At top level; cap progress
    $progress_percentage = 100;
    $points_to_next = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EcoEdu</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/dashboard-enhanced.css" rel="stylesheet">
    <style>
        /* Navbar Profile Image */
        .navbar-profile-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .navbar-profile-img:hover {
            border-color: rgba(255, 255, 255, 0.8);
            transform: scale(1.1);
        }
    </style>
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
                        <a class="nav-link text-white active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="learn.php">
                            <i class="fas fa-book me-1"></i>Learn
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="quizzes.php">
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
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-mdb-toggle="dropdown">
                            <?php 
                            $profile_image_path = 'uploads/profiles/' . ($user['profile_image'] ?? 'default-avatar.png');
                            if ($user['profile_image'] && $user['profile_image'] !== 'default-avatar.png' && file_exists($profile_image_path)): 
                            ?>
                                <img src="<?php echo $profile_image_path; ?>" alt="Profile" class="navbar-profile-img me-2">
                            <?php else: ?>
                                <i class="fas fa-user me-2"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($user['first_name']); ?>
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
            <!-- Enhanced Welcome Section -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="hero-card">
                        <div class="hero-background"></div>
                        <div class="hero-content">
                            <div class="row align-items-center">
                                <div class="col-lg-8">
                                    <div class="welcome-text">
                                        <h1 class="hero-title">Welcome back, <span class="text-gradient"><?php echo htmlspecialchars($user['first_name']); ?></span>! 🌱</h1>
                                        <p class="hero-subtitle">Ready to continue your eco-journey? You're making a real difference!</p>
                                        
                                        <!-- User Stats Badges -->
                                        <div class="user-badges">
                                            <div class="badge-item level-badge">
                                                <div class="badge-icon">
                                                    <i class="fas fa-star"></i>
                                                </div>
                                                <div class="badge-info">
                                                    <div class="badge-label">Level</div>
                                                    <div class="badge-value"><?php echo htmlspecialchars($user['level_name'] ?? 'Beginner'); ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="badge-item points-badge">
                                                <div class="badge-icon">
                                                    <i class="fas fa-coins"></i>
                                                </div>
                                                <div class="badge-info">
                                                    <div class="badge-label">EcoPoints</div>
                                                    <div class="badge-value" id="points-counter"><?php echo number_format($user['eco_points']); ?></div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($user_rank > 0): ?>
                                            <div class="badge-item rank-badge">
                                                <div class="badge-icon">
                                                    <i class="fas fa-medal"></i>
                                                </div>
                                                <div class="badge-info">
                                                    <div class="badge-label">Global Rank</div>
                                                    <div class="badge-value">#<?php echo $user_rank; ?></div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="badge-item badges-badge">
                                                <div class="badge-icon">
                                                    <i class="fas fa-trophy"></i>
                                                </div>
                                                <div class="badge-info">
                                                    <div class="badge-label">Badges</div>
                                                    <div class="badge-value"><?php echo $user['total_badges']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Progress to Next Level -->
                                        <?php if ($next_level): ?>
                                        <div class="level-progress">
                                            <div class="progress-header">
                                                <span>Progress to Next Level</span>
                                                <span class="progress-text"><?php echo $next_level['min_points'] - $user['eco_points']; ?> points to go</span>
                                            </div>
                                            <div class="progress-container">
                                                <div class="progress-bar-custom">
                                                    <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                                                </div>
                                                <div class="progress-percentage"><?php echo round($progress_percentage); ?>%</div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="hero-visual">
                                        <div class="floating-elements">
                                            <div class="floating-icon leaf-1"><i class="fas fa-leaf"></i></div>
                                            <div class="floating-icon recycle-1"><i class="fas fa-recycle"></i></div>
                                            <div class="floating-icon tree-1"><i class="fas fa-tree"></i></div>
                                            <div class="floating-icon globe-1"><i class="fas fa-globe-americas"></i></div>
                                        </div>
                                        <div class="central-icon">
                                            <i class="fas fa-seedling"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Quick Actions - Horizontal Layout -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card" style="border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                        <div class="card-header text-white" style="background: linear-gradient(135deg, #28a745, #20c997); border-radius: 20px 20px 0 0;">
                            <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="quick-actions-grid">
                                <a href="quizzes.php" class="action-btn success">
                                    <div class="action-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                        <i class="fas fa-brain"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">Take Quiz</div>
                                        <small class="text-muted">Test knowledge</small>
                                    </div>
                                </a>
                                
                                <a href="games.php" class="action-btn danger">
                                    <div class="action-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                        <i class="fas fa-gamepad"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">Play Games</div>
                                        <small class="text-muted">Earn points</small>
                                    </div>
                                </a>
                                
                                <a href="challenges.php" class="action-btn info">
                                    <div class="action-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">Challenges</div>
                                        <small class="text-muted">Join events</small>
                                    </div>
                                </a>
                                
                                <a href="learn.php" class="action-btn primary">
                                    <div class="action-icon" style="background: rgba(0, 123, 255, 0.1); color: #007bff;">
                                        <i class="fas fa-book-open"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">Learn</div>
                                        <small class="text-muted">Read content</small>
                                    </div>
                                </a>
                                
                                <a href="leaderboard.php" class="action-btn warning">
                                    <div class="action-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                        <i class="fas fa-medal"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">Leaderboard</div>
                                        <small class="text-muted">View rankings</small>
                                    </div>
                                </a>
                                
                                <a href="profile.php" class="action-btn" style="border-color: #6f42c1;">
                                    <div class="action-icon" style="background: rgba(111, 66, 193, 0.1); color: #6f42c1;">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">Profile</div>
                                        <small class="text-muted">View stats</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="dashboard-grid mb-5">
                <div class="stat-card card-hover-effect">
                    <div class="stat-icon text-success">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-value text-success" id="points-counter"><?php echo number_format($user['eco_points']); ?></div>
                    <div class="stat-label">Eco Points</div>
                    <?php if ($next_level): ?>
                    <div class="mt-3">
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $progress_percentage; ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo $next_level['min_points'] - $user['eco_points']; ?> points to next level</small>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="stat-card card-hover-effect">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-medal"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo $user['total_badges']; ?></div>
                    <div class="stat-label">Badges Earned</div>
                </div>

                <div class="stat-card card-hover-effect">
                    <div class="stat-icon text-info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value text-info"><?php echo $user_rank > 0 ? '#' . $user_rank : 'Unranked'; ?></div>
                    <div class="stat-label">Global Rank</div>
                </div>

                <div class="stat-card card-hover-effect">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-value text-primary">
                        <?php 
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM quiz_attempts WHERE user_id = ? AND is_passed = 1");
                        $stmt->execute([$user['id']]);
                        echo $stmt->fetch()['count'];
                        ?>
                    </div>
                    <div class="stat-label">Quizzes Passed</div>
                </div>

                <div class="stat-card card-hover-effect">
                    <div class="stat-icon text-danger">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <div class="stat-value text-danger">
                        <?php 
                        try {
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM game_results WHERE user_id = ?");
                            $stmt->execute([$user['id']]);
                            echo $stmt->fetch()['count'];
                        } catch (Exception $e) {
                            echo '0';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Games Played</div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Badges -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="fas fa-medal me-2"></i>Recent Badges</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($user_badges)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-medal fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No badges yet. Complete quizzes and challenges to earn your first badge!</p>
                                    <a href="quizzes.php" class="btn btn-sm btn-warning">Take a Quiz</a>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($user_badges, 0, 3) as $badge): ?>
                                <div class="badge-item mb-3">
                                    <div class="badge-icon">
                                        <i class="<?php echo $badge['icon']; ?>"></i>
                                    </div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($badge['name']); ?></h6>
                                    <small class="text-muted"><?php echo formatTimeAgo($badge['earned_at']); ?></small>
                                </div>
                                <?php endforeach; ?>
                                <?php if (count($user_badges) > 3): ?>
                                <div class="text-center">
                                    <a href="profile.php#badges" class="btn btn-sm btn-outline-warning">View All Badges</a>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Recent Activity and Other Content -->
        <div class="row">
            <!-- Recent Activity -->
            <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT 'quiz' as type, q.title, qa.completed_at as date, qa.points_earned 
                                FROM quiz_attempts qa 
                                JOIN quizzes q ON qa.quiz_id = q.id 
                                WHERE qa.user_id = ? AND qa.is_passed = 1
                                UNION ALL
                                SELECT 'challenge' as type, ch.title, cp.verified_at as date, cp.points_earned 
                                FROM challenge_participation cp 
                                JOIN challenges ch ON cp.challenge_id = ch.id 
                                WHERE cp.user_id = ? AND cp.status = 'verified'
                                ORDER BY date DESC LIMIT 5
                            ");
                            $stmt->execute([$user['id'], $user['id']]);
                            $activities = $stmt->fetchAll();
                            ?>
                            
                            <?php if (empty($activities)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No recent activity. Start learning to see your progress here!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($activities as $activity): ?>
                                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                    <div class="me-3">
                                        <i class="fas <?php echo $activity['type'] === 'quiz' ? 'fa-question-circle text-primary' : 'fa-trophy text-warning'; ?> fa-2x"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                        <small class="text-muted"><?php echo formatTimeAgo($activity['date']); ?></small>
                                        <div class="text-success">
                                            <i class="fas fa-coins me-1"></i>+<?php echo $activity['points_earned']; ?> points
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Featured Content -->
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="mb-4"><i class="fas fa-star text-warning me-2"></i>Featured Content</h3>
                </div>
                
                <!-- Featured Quizzes -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Popular Quizzes</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach (array_slice($recent_quizzes, 0, 3) as $quiz): ?>
                            <div class="quiz-card card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h6>
                                    <p class="card-text text-muted"><?php echo htmlspecialchars($quiz['description']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i><?php echo $quiz['time_limit']; ?>s
                                            <i class="fas fa-coins ms-2 me-1"></i><?php echo $quiz['total_questions'] * $quiz['points_per_question']; ?> pts
                                        </small>
                                        <a href="quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">Start Quiz</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="text-center">
                                <a href="quizzes.php" class="btn btn-outline-primary">View All Quizzes</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Featured Challenges -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Active Challenges</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach (array_slice($recent_challenges, 0, 3) as $challenge): ?>
                            <div class="challenge-card card mb-3">
                                <div class="card-body">
                                    <div class="challenge-difficulty difficulty-<?php echo $challenge['difficulty_level']; ?>">
                                        <?php echo ucfirst($challenge['difficulty_level']); ?>
                                    </div>
                                    <h6 class="card-title"><?php echo htmlspecialchars($challenge['title']); ?></h6>
                                    <p class="card-text text-muted"><?php echo htmlspecialchars(substr($challenge['description'], 0, 100)) . '...'; ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-coins me-1"></i><?php echo $challenge['points_reward']; ?> points
                                        </small>
                                        <a href="challenge.php?id=<?php echo $challenge['id']; ?>" class="btn btn-sm btn-info">View Challenge</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="text-center">
                                <a href="challenges.php" class="btn btn-outline-info">View All Challenges</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        // Enhanced Dashboard Animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate points counter
            const pointsCounters = document.querySelectorAll('#points-counter, .badge-value');
            pointsCounters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/,/g, ''));
                if (target > 0) {
                    animateCounter(counter, target);
                }
            });
            
            // Animate progress bars
            const progressFills = document.querySelectorAll('.progress-fill');
            progressFills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0%';
                setTimeout(() => {
                    fill.style.width = width;
                }, 500);
            });
            
            // Stagger animation for stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100 + 800);
            });
            
            // Animate action buttons
            const actionBtns = document.querySelectorAll('.action-btn');
            actionBtns.forEach((btn, index) => {
                btn.style.opacity = '0';
                btn.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    btn.style.transition = 'all 0.4s ease';
                    btn.style.opacity = '1';
                    btn.style.transform = 'scale(1)';
                }, index * 50 + 1200);
            });
            
            // Add hover sound effect (optional)
            actionBtns.forEach(btn => {
                btn.addEventListener('mouseenter', () => {
                    btn.style.transform = 'translateY(-2px) scale(1.02)';
                });
                btn.addEventListener('mouseleave', () => {
                    btn.style.transform = 'translateY(0) scale(1)';
                });
            });
            
            // Floating icons animation
            const floatingIcons = document.querySelectorAll('.floating-icon');
            floatingIcons.forEach((icon, index) => {
                icon.style.animationDelay = `${index * 0.5}s`;
            });
            
            // Add particle effect on badge hover
            const badgeItems = document.querySelectorAll('.badge-item');
            badgeItems.forEach(badge => {
                badge.addEventListener('mouseenter', createParticles);
            });
        });
        
        // Counter animation function
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current).toLocaleString();
            }, 20);
        }
        
        // Particle effect function
        function createParticles(e) {
            const rect = e.currentTarget.getBoundingClientRect();
            for (let i = 0; i < 5; i++) {
                const particle = document.createElement('div');
                particle.style.position = 'fixed';
                particle.style.left = rect.left + Math.random() * rect.width + 'px';
                particle.style.top = rect.top + Math.random() * rect.height + 'px';
                particle.style.width = '4px';
                particle.style.height = '4px';
                particle.style.background = '#ffd700';
                particle.style.borderRadius = '50%';
                particle.style.pointerEvents = 'none';
                particle.style.zIndex = '9999';
                particle.style.animation = 'particle-float 1s ease-out forwards';
                document.body.appendChild(particle);
                
                setTimeout(() => particle.remove(), 1000);
            }
        }
        
        // Add particle animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes particle-float {
                0% { 
                    opacity: 1; 
                    transform: translateY(0) scale(1); 
                }
                100% { 
                    opacity: 0; 
                    transform: translateY(-50px) scale(0); 
                }
            }
        `;
        document.head.appendChild(style);
        
        // Theme toggle functionality
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            body.setAttribute('data-theme', newTheme);
            
            const icon = document.getElementById('theme-icon');
            icon.className = newTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            
            localStorage.setItem('theme', newTheme);
        }
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', savedTheme);
        const themeIcon = document.getElementById('theme-icon');
        if (themeIcon) {
            themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }
    </script>
</body>
</html>
