<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Handle habit tracking
$message = '';
$message_type = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_habit':
                $habit_type = sanitizeInput($_POST['habit_type']);
                $date = date('Y-m-d');
                
                try {
                    // Check if already marked today
                    $stmt = $pdo->prepare("
                        SELECT id FROM user_streaks 
                        WHERE user_id = ? AND streak_type = ? AND last_activity_date = ?
                    ");
                    $stmt->execute([$user['id'], $habit_type, $date]);
                    
                    if (!$stmt->fetch()) {
                        // Update or create streak
                        $stmt = $pdo->prepare("
                            INSERT INTO user_streaks (user_id, streak_type, streak_count, last_activity_date) 
                            VALUES (?, ?, 1, ?) 
                            ON DUPLICATE KEY UPDATE 
                            streak_count = CASE 
                                WHEN last_activity_date = DATE_SUB(?, INTERVAL 1 DAY) THEN streak_count + 1
                                WHEN last_activity_date = ? THEN streak_count
                                ELSE 1 
                            END,
                            last_activity_date = ?
                        ");
                        $stmt->execute([$user['id'], $habit_type, $date, $date, $date, $date]);
                        
                        // Award points for habit completion
                        $points = 10;
                        addEcoPoints($user['id'], $points, "Completed daily habit: $habit_type");
                        
                        $message = 'Habit marked successfully! +' . $points . ' eco-points earned.';
                        $message_type = 'success';
                    } else {
                        $message = 'You have already marked this habit today!';
                        $message_type = 'warning';
                    }
                } catch (Exception $e) {
                    $message = 'Error marking habit. Please try again.';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get user's current streaks
try {
    $stmt = $pdo->prepare("
        SELECT * FROM user_streaks 
        WHERE user_id = ? 
        ORDER BY streak_count DESC
    ");
    $stmt->execute([$user['id']]);
    $streaks = $stmt->fetchAll();
} catch (Exception $e) {
    $streaks = [];
}

// Define available habits
$habits = [
    'daily_habit' => [
        'name' => 'Daily Eco Action',
        'icon' => 'fas fa-leaf',
        'color' => 'success',
        'description' => 'Complete any eco-friendly action today'
    ],
    'water_conservation' => [
        'name' => 'Water Conservation',
        'icon' => 'fas fa-tint',
        'color' => 'primary',
        'description' => 'Save water by taking shorter showers or fixing leaks'
    ],
    'energy_saving' => [
        'name' => 'Energy Saving',
        'icon' => 'fas fa-bolt',
        'color' => 'warning',
        'description' => 'Turn off lights, unplug devices, or use natural light'
    ],
    'waste_reduction' => [
        'name' => 'Waste Reduction',
        'icon' => 'fas fa-recycle',
        'color' => 'info',
        'description' => 'Reduce, reuse, or recycle items today'
    ],
    'green_transport' => [
        'name' => 'Green Transport',
        'icon' => 'fas fa-bicycle',
        'color' => 'secondary',
        'description' => 'Walk, bike, or use public transport instead of driving'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habit Streaks - EcoEdu Games</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .habit-card {
            transition: all 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .habit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .streak-counter {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .flame-icon {
            color: #ff6b35;
            animation: flicker 2s infinite alternate;
        }
        
        @keyframes flicker {
            0% { opacity: 1; }
            50% { opacity: 0.8; }
            100% { opacity: 1; }
        }
        
        .habit-completed {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .progress-ring {
            width: 120px;
            height: 120px;
        }
        
        .progress-ring-circle {
            stroke: #e9ecef;
            stroke-width: 8;
            fill: transparent;
            r: 52;
            cx: 60;
            cy: 60;
        }
        
        .progress-ring-fill {
            stroke: #28a745;
            stroke-width: 8;
            fill: transparent;
            r: 52;
            cx: 60;
            cy: 60;
            stroke-dasharray: 327;
            stroke-dashoffset: 327;
            transition: stroke-dashoffset 0.5s ease;
        }
    </style>
</head>
<body data-theme="light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-success fixed-top">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="../index.php">
                <i class="fas fa-leaf me-2"></i>EcoEdu
            </a>
            
            <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="../dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="../games.php">
                            <i class="fas fa-gamepad me-1"></i>Games
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-mdb-toggle="dropdown">
                            <?php 
                            $profile_image_path = '../uploads/profiles/' . ($user['profile_image'] ?? 'default-avatar.png');
                            if ($user['profile_image'] && $user['profile_image'] !== 'default-avatar.png' && file_exists($profile_image_path)): 
                            ?>
                                <img src="<?php echo $profile_image_path; ?>" alt="Profile" class="navbar-profile-img me-2">
                            <?php else: ?>
                                <i class="fas fa-user me-2"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($user['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5 pt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="text-center">
                    <h1 class="display-4 text-success mb-3">
                        <i class="fas fa-fire flame-icon me-3"></i>
                        Habit Streaks
                    </h1>
                    <p class="lead text-muted">Build eco-friendly habits and maintain your streaks!</p>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'danger'); ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Current Streaks Overview -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Your Current Streaks
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($streaks)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-fire fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No streaks yet!</h5>
                            <p class="text-muted">Start building your eco-habits today.</p>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($streaks as $streak): ?>
                            <?php $habit = $habits[$streak['streak_type']] ?? ['name' => $streak['streak_type'], 'icon' => 'fas fa-leaf', 'color' => 'success']; ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-<?php echo $habit['color']; ?>">
                                    <div class="card-body text-center">
                                        <i class="<?php echo $habit['icon']; ?> fa-2x text-<?php echo $habit['color']; ?> mb-2"></i>
                                        <h6><?php echo $habit['name']; ?></h6>
                                        <div class="streak-counter text-<?php echo $habit['color']; ?>">
                                            <?php echo $streak['streak_count']; ?>
                                            <i class="fas fa-fire flame-icon ms-1"></i>
                                        </div>
                                        <small class="text-muted">
                                            Last: <?php echo date('M j', strtotime($streak['last_activity_date'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Habits -->
        <div class="row">
            <div class="col-12">
                <h3 class="mb-4">
                    <i class="fas fa-calendar-check me-2"></i>Today's Eco Habits
                </h3>
            </div>
        </div>

        <div class="row">
            <?php foreach ($habits as $habit_key => $habit): ?>
            <?php 
            // Check if completed today
            $completed_today = false;
            foreach ($streaks as $streak) {
                if ($streak['streak_type'] === $habit_key && $streak['last_activity_date'] === date('Y-m-d')) {
                    $completed_today = true;
                    break;
                }
            }
            ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card habit-card <?php echo $completed_today ? 'habit-completed' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <i class="<?php echo $habit['icon']; ?> fa-2x <?php echo $completed_today ? 'text-white' : 'text-' . $habit['color']; ?>"></i>
                            </div>
                            <?php if ($completed_today): ?>
                            <span class="badge bg-light text-success">
                                <i class="fas fa-check me-1"></i>Completed
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="<?php echo $completed_today ? 'text-white' : ''; ?>">
                            <?php echo $habit['name']; ?>
                        </h5>
                        <p class="<?php echo $completed_today ? 'text-white-50' : 'text-muted'; ?> mb-3">
                            <?php echo $habit['description']; ?>
                        </p>
                        
                        <?php if (!$completed_today): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="mark_habit">
                            <input type="hidden" name="habit_type" value="<?php echo $habit_key; ?>">
                            <button type="submit" class="btn btn-<?php echo $habit['color']; ?> w-100">
                                <i class="fas fa-check me-2"></i>Mark as Done
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="text-center">
                            <i class="fas fa-trophy fa-2x text-warning"></i>
                            <div class="mt-2 text-white">+10 Eco-Points Earned!</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Streak Tips -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5><i class="fas fa-lightbulb text-warning me-2"></i>Streak Building Tips</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Start small and be consistent</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Set daily reminders</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Track your progress</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Celebrate milestones</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Don't break the chain</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Make it part of your routine</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    
    <style>
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
</body>
</html>
