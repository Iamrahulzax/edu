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

// Get user's game statistics
$game_stats = [];
try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(CASE WHEN game_type = 'quiz_battle' THEN 1 END) as quiz_battles,
        COUNT(CASE WHEN game_type = 'recycle_sorter' THEN 1 END) as recycle_games,
        COUNT(CASE WHEN game_type = 'carbon_tracker' THEN 1 END) as carbon_entries,
        COUNT(CASE WHEN game_type = 'habit_streak' THEN 1 END) as habit_days,
        COUNT(CASE WHEN game_type = 'tree_grow' THEN 1 END) as tree_actions,
        SUM(points_earned) as total_game_points,
        MAX(score) as best_score
        FROM game_results WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $game_stats = $stmt->fetch();
    if ($game_stats) {
        $defaults = [
            'quiz_battles' => 0,
            'recycle_games' => 0,
            'carbon_entries' => 0,
            'habit_days' => 0,
            'tree_actions' => 0,
            'total_game_points' => 0,
            'best_score' => 0,
        ];
        foreach ($defaults as $key => $fallback) {
            $game_stats[$key] = isset($game_stats[$key]) ? (int)$game_stats[$key] : $fallback;
        }
    } else {
        $game_stats = ['quiz_battles' => 0, 'recycle_games' => 0, 'carbon_entries' => 0, 'habit_days' => 0, 'tree_actions' => 0, 'total_game_points' => 0, 'best_score' => 0];
    }
} catch (Exception $e) {
    $game_stats = ['quiz_battles' => 0, 'recycle_games' => 0, 'carbon_entries' => 0, 'habit_days' => 0, 'tree_actions' => 0, 'total_game_points' => 0, 'best_score' => 0];
}

// Get user's current streak
$current_streak = 0;
try {
    $stmt = $pdo->prepare("SELECT streak_count FROM user_streaks WHERE user_id = ? AND streak_type = 'daily_habit'");
    $stmt->execute([$user['id']]);
    $streak_data = $stmt->fetch();
    $current_streak = $streak_data ? $streak_data['streak_count'] : 0;
} catch (Exception $e) {
    $current_streak = 0;
}

// Get recent game results
$recent_games = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM game_results WHERE user_id = ? ORDER BY played_at DESC LIMIT 5");
    $stmt->execute([$user['id']]);
    $recent_games = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_games = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eco Games - EcoEdu</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .game-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            height: 100%;
        }
        
        .game-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border-color: #28a745;
        }
        
        .game-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
        }
        
        .quiz-battle { background: linear-gradient(45deg, #007bff, #0056b3); }
        .recycle-sorter { background: linear-gradient(45deg, #28a745, #1e7e34); }
        .carbon-tracker { background: linear-gradient(45deg, #17a2b8, #117a8b); }
        .habit-streak { background: linear-gradient(45deg, #ffc107, #e0a800); }
        .tree-grow { background: linear-gradient(45deg, #20c997, #17a2b8); }
        .eco-hunt { background: linear-gradient(45deg, #dc3545, #c82333); }
        
        .stats-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        
        .streak-counter {
            font-size: 3rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .game-progress {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 10px;
            margin-top: 15px;
        }
        
        .progress-bar-animated {
            animation: progress-animation 2s ease-in-out;
        }
        
        @keyframes progress-animation {
            0% { width: 0%; }
            100% { width: var(--progress-width); }
        }
        
        .floating-points {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #333;
            padding: 15px 25px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(255,215,0,0.3);
            z-index: 1000;
        }
    </style>
</head>
<body data-theme="light">
    <!-- Floating Points Display -->
    <div class="floating-points">
        <i class="fas fa-coins me-2"></i>
        <?php echo number_format($user['eco_points']); ?> EcoPoints
    </div>

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
                        <a class="nav-link text-white" href="quizzes.php">
                            <i class="fas fa-question-circle me-1"></i>Quizzes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="games.php">
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
                    <div class="text-center">
                        <h1 class="text-gradient mb-3">
                            <i class="fas fa-gamepad me-3"></i>Eco Games Arena
                        </h1>
                        <p class="lead text-muted">Play fun games, learn about environment, and earn EcoPoints!</p>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row mb-5">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="streak-counter"><?php echo $current_streak; ?></div>
                        <h6>Day Streak</h6>
                        <small>Keep it going! 🔥</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="streak-counter"><?php echo $game_stats['quiz_battles']; ?></div>
                        <h6>Quiz Battles</h6>
                        <small>Brain power! 🧠</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="streak-counter"><?php echo number_format($game_stats['total_game_points']); ?></div>
                        <h6>Game Points</h6>
                        <small>From games only! 🎮</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="streak-counter"><?php echo $game_stats['best_score']; ?>%</div>
                        <h6>Best Score</h6>
                        <small>Personal record! 🏆</small>
                    </div>
                </div>
            </div>

            <!-- Games Grid -->
            <div class="row">
                <!-- Eco Quiz Battle -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="game-card">
                        <div class="game-icon quiz-battle">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h4 class="text-center mb-3">Eco Quiz Battle</h4>
                        <p class="text-center text-muted mb-4">Test your environmental knowledge in timed quizzes!</p>
                        
                        <div class="game-progress">
                            <div class="d-flex justify-content-between mb-2">
                                <small>Games Played</small>
                                <small><?php echo $game_stats['quiz_battles']; ?>/∞</small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary progress-bar-animated" 
                                     style="--progress-width: <?php echo min(100, ($game_stats['quiz_battles'] / 10) * 100); ?>%; width: <?php echo min(100, ($game_stats['quiz_battles'] / 10) * 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <div class="mb-3">
                                <span class="badge bg-primary me-2">+10 points per correct answer</span>
                                <span class="badge bg-info">Timed Challenge</span>
                            </div>
                            <a href="games/quiz-battle.php" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-play me-2"></i>Start Battle
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recycle Sorter -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="game-card">
                        <div class="game-icon recycle-sorter">
                            <i class="fas fa-recycle"></i>
                        </div>
                        <h4 class="text-center mb-3">Recycle Sorter</h4>
                        <p class="text-center text-muted mb-4">Sort waste items into the correct recycling bins!</p>
                        
                        <div class="game-progress">
                            <div class="d-flex justify-content-between mb-2">
                                <small>Rounds Completed</small>
                                <small><?php echo $game_stats['recycle_games']; ?>/∞</small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success progress-bar-animated" 
                                     style="--progress-width: <?php echo min(100, ($game_stats['recycle_games'] / 10) * 100); ?>%; width: <?php echo min(100, ($game_stats['recycle_games'] / 10) * 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <div class="mb-3">
                                <span class="badge bg-success me-2">+15 points per round</span>
                                <span class="badge bg-warning">Drag & Drop</span>
                            </div>
                            <a href="games/recycle-sorter.php" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-play me-2"></i>Start Sorting
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Carbon Footprint Tracker -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="game-card">
                        <div class="game-icon carbon-tracker">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <h4 class="text-center mb-3">Carbon Tracker</h4>
                        <p class="text-center text-muted mb-4">Track your daily eco-friendly behaviors!</p>
                        
                        <div class="game-progress">
                            <div class="d-flex justify-content-between mb-2">
                                <small>Days Tracked</small>
                                <small><?php echo $game_stats['carbon_entries']; ?>/30</small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-info progress-bar-animated" 
                                     style="--progress-width: <?php echo min(100, ($game_stats['carbon_entries'] / 30) * 100); ?>%; width: <?php echo min(100, ($game_stats['carbon_entries'] / 30) * 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <div class="mb-3">
                                <span class="badge bg-info me-2">5-25 points daily</span>
                                <span class="badge bg-secondary">Daily Challenge</span>
                            </div>
                            <a href="games/carbon-tracker.php" class="btn btn-info btn-lg w-100">
                                <i class="fas fa-play me-2"></i>Track Today
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Green Habit Streaks -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="game-card">
                        <div class="game-icon habit-streak">
                            <i class="fas fa-fire"></i>
                        </div>
                        <h4 class="text-center mb-3">Green Habit Streaks</h4>
                        <p class="text-center text-muted mb-4">Build daily eco-habits and maintain streaks!</p>
                        
                        <div class="game-progress">
                            <div class="d-flex justify-content-between mb-2">
                                <small>Current Streak</small>
                                <small><?php echo $current_streak; ?> days</small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning progress-bar-animated" 
                                     style="--progress-width: <?php echo min(100, ($current_streak / 30) * 100); ?>%; width: <?php echo min(100, ($current_streak / 30) * 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <div class="mb-3">
                                <span class="badge bg-warning me-2">+10 points daily</span>
                                <span class="badge bg-danger">Streak Bonus</span>
                            </div>
                            <a href="games/habit-streaks.php" class="btn btn-warning btn-lg w-100">
                                <i class="fas fa-play me-2"></i>Check In
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Virtual Tree Growing -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="game-card">
                        <div class="game-icon tree-grow">
                            <i class="fas fa-tree"></i>
                        </div>
                        <h4 class="text-center mb-3">Grow Virtual Tree</h4>
                        <p class="text-center text-muted mb-4">Watch your tree grow as you earn more points!</p>
                        
                        <div class="game-progress">
                            <div class="d-flex justify-content-between mb-2">
                                <small>Tree Progress</small>
                                <small><?php echo min(100, ($user['eco_points'] / 1000) * 100); ?>%</small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success progress-bar-animated" 
                                     style="--progress-width: <?php echo min(100, ($user['eco_points'] / 1000) * 100); ?>%; width: <?php echo min(100, ($user['eco_points'] / 1000) * 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <div class="mb-3">
                                <span class="badge bg-success me-2">+5 per eco action</span>
                                <span class="badge bg-info">Progress Game</span>
                            </div>
                            <a href="games/tree-grow.php" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-play me-2"></i>View Tree
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Eco Hunt (Real World Challenge) -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="game-card">
                        <div class="game-icon eco-hunt">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h4 class="text-center mb-3">Eco Hunt</h4>
                        <p class="text-center text-muted mb-4">Complete real-world eco tasks and upload proof!</p>
                        
                        <div class="game-progress">
                            <div class="d-flex justify-content-between mb-2">
                                <small>Tasks Completed</small>
                                <small>Coming Soon</small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-danger progress-bar-animated" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <div class="mb-3">
                                <span class="badge bg-danger me-2">+50 verified points</span>
                                <span class="badge bg-dark">Real World</span>
                            </div>
                            <button class="btn btn-outline-danger btn-lg w-100" disabled>
                                <i class="fas fa-lock me-2"></i>Coming Soon
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Games -->
            <?php if (!empty($recent_games)): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Game Results</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Game</th>
                                            <th>Score</th>
                                            <th>Points Earned</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_games as $game): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-<?php echo $game['game_type'] === 'quiz_battle' ? 'brain' : ($game['game_type'] === 'recycle_sorter' ? 'recycle' : 'leaf'); ?> me-2"></i>
                                                <?php echo ucwords(str_replace('_', ' ', $game['game_type'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $game['score'] >= 80 ? 'success' : ($game['score'] >= 60 ? 'warning' : 'danger'); ?>">
                                                    <?php echo $game['score']; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-success fw-bold">+<?php echo $game['points_earned']; ?></span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($game['played_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
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
        // Add floating point animation when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const floatingPoints = document.querySelector('.floating-points');
            floatingPoints.style.animation = 'bounce 2s ease-in-out infinite';
        });
        
        // Add CSS for bounce animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes bounce {
                0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                40% { transform: translateY(-10px); }
                60% { transform: translateY(-5px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
