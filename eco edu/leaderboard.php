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

// Get leaderboard type
$leaderboard_type = isset($_GET['type']) ? $_GET['type'] : 'global';

// Get leaderboards with error handling
try {
    $global_leaderboard = getGlobalLeaderboard(50);
} catch (Exception $e) {
    $global_leaderboard = [];
}

$personal_window = getUserRankWindow($user['id']);
$user_tier = getTierForPoints((int)$user['eco_points']);
$tier_record = updateUserTierTracking($user['id'], $user_tier['slug']);
awardTierBadgeIfEligible($user['id'], $user_tier['slug'], $tier_record['entered_at'] ?? date('Y-m-d H:i:s'));

try {
    $school_leaderboard = getSchoolLeaderboard(20);
} catch (Exception $e) {
    $school_leaderboard = [];
}

// Find user's position in global leaderboard
$user_global_rank = 0;
foreach ($global_leaderboard as $index => $leader) {
    if ($leader['id'] == $user['id']) {
        $user_global_rank = $index + 1;
        break;
    }
}

// Get school-specific leaderboard if user has a school
$school_specific_leaderboard = [];
if ($user['school_name']) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM user_leaderboard 
            WHERE school_name = ? 
            ORDER BY eco_points DESC 
            LIMIT 20
        ");
        $stmt->execute([$user['school_name']]);
        $school_specific_leaderboard = $stmt->fetchAll();
    } catch (Exception $e) {
        $school_specific_leaderboard = [];
    }
}

// Get recent achievements
try {
    $stmt = $pdo->prepare("
        SELECT u.username, u.first_name, u.last_name, u.school_name, ub.earned_at, b.name as badge_name, b.icon
        FROM user_badges ub
        JOIN users u ON ub.user_id = u.id
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.earned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY ub.earned_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_achievements = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_achievements = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - EcoEdu</title>
    
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
                        <a class="nav-link text-white" href="quizzes.php">
                            <i class="fas fa-question-circle me-1"></i>Quizzes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="challenges.php">
                            <i class="fas fa-trophy me-1"></i>Challenges
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="leaderboard.php">
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
                            <h1 class="text-gradient"><i class="fas fa-medal me-2"></i>Leaderboard</h1>
                            <p class="text-muted">See how you rank among eco-warriors worldwide!</p>
                        </div>
                        <div class="text-end">
                            <div class="eco-points mb-2">
                                <i class="fas fa-coins me-1"></i>
                                <?php echo number_format($user['eco_points']); ?> Points
                            </div>
                            <?php if ($user_global_rank > 0): ?>
                            <div class="eco-points" style="background: linear-gradient(45deg, #17a2b8, #20c997);">
                                <i class="fas fa-medal me-1"></i>
                                Global Rank #<?php echo $user_global_rank; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leaderboard Tabs -->
            <div class="row mb-4">
                <div class="col-12">
                    <ul class="nav nav-pills nav-justified" id="leaderboard-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $leaderboard_type === 'global' ? 'active' : ''; ?>" 
                                    id="global-tab" data-mdb-toggle="pill" data-mdb-target="#global" 
                                    type="button" role="tab">
                                <i class="fas fa-globe me-2"></i>Global Leaderboard
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $leaderboard_type === 'personal' ? 'active' : ''; ?>"
                                    id="personal-tab" data-mdb-toggle="pill" data-mdb-target="#personal"
                                    type="button" role="tab">
                                <i class="fas fa-user-graduate me-2"></i>My Ranking
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $leaderboard_type === 'schools' ? 'active' : ''; ?>" 
                                    id="schools-tab" data-mdb-toggle="pill" data-mdb-target="#schools" 
                                    type="button" role="tab">
                                <i class="fas fa-school me-2"></i>School Rankings
                            </button>
                        </li>
                        <?php if (!empty($school_specific_leaderboard)): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $leaderboard_type === 'myschool' ? 'active' : ''; ?>" 
                                    id="myschool-tab" data-mdb-toggle="pill" data-mdb-target="#myschool" 
                                    type="button" role="tab">
                                <i class="fas fa-users me-2"></i>My School
                            </button>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $leaderboard_type === 'achievements' ? 'active' : ''; ?>" 
                                    id="achievements-tab" data-mdb-toggle="pill" data-mdb-target="#achievements" 
                                    type="button" role="tab">
                                <i class="fas fa-star me-2"></i>Recent Achievements
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="leaderboard-content">
                <!-- Global Leaderboard -->
                <div class="tab-pane fade <?php echo $leaderboard_type === 'global' ? 'show active' : ''; ?>" 
                     id="global" role="tabpanel">
                    
                    <!-- Top 3 Podium -->
                    <?php if (count($global_leaderboard) >= 3): ?>
                    <div class="row mb-5">
                        <div class="col-12">
                            <div class="podium-container text-center">
                                <div class="row justify-content-center align-items-end">
                                    <!-- 2nd Place -->
                                    <div class="col-md-3 mb-3">
                                        <div class="podium-card silver">
                                            <div class="podium-rank">2</div>
                                            <div class="podium-avatar mb-2">
                                                <i class="fas fa-user-circle fa-3x text-secondary"></i>
                                            </div>
                                            <h5><?php echo htmlspecialchars($global_leaderboard[1]['first_name'] . ' ' . $global_leaderboard[1]['last_name']); ?></h5>
                                            <p class="text-muted"><?php echo htmlspecialchars($global_leaderboard[1]['school_name'] ?: 'Independent'); ?></p>
                                            <div class="eco-points">
                                                <i class="fas fa-coins me-1"></i>
                                                <?php echo number_format($global_leaderboard[1]['eco_points']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 1st Place -->
                                    <div class="col-md-3 mb-3">
                                        <div class="podium-card gold">
                                            <div class="podium-rank">1</div>
                                            <div class="podium-avatar mb-2">
                                                <i class="fas fa-user-circle fa-4x text-warning"></i>
                                            </div>
                                            <h5><?php echo htmlspecialchars($global_leaderboard[0]['first_name'] . ' ' . $global_leaderboard[0]['last_name']); ?></h5>
                                            <p class="text-muted"><?php echo htmlspecialchars($global_leaderboard[0]['school_name'] ?: 'Independent'); ?></p>
                                            <div class="eco-points">
                                                <i class="fas fa-coins me-1"></i>
                                                <?php echo number_format($global_leaderboard[0]['eco_points']); ?>
                                            </div>
                                            <div class="crown">
                                                <i class="fas fa-crown text-warning"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 3rd Place -->
                                    <div class="col-md-3 mb-3">
                                        <div class="podium-card bronze">
                                            <div class="podium-rank">3</div>
                                            <div class="podium-avatar mb-2">
                                                <i class="fas fa-user-circle fa-3x" style="color: #cd7f32;"></i>
                                            </div>
                                            <h5><?php echo htmlspecialchars($global_leaderboard[2]['first_name'] . ' ' . $global_leaderboard[2]['last_name']); ?></h5>
                                            <p class="text-muted"><?php echo htmlspecialchars($global_leaderboard[2]['school_name'] ?: 'Independent'); ?></p>
                                            <div class="eco-points">
                                                <i class="fas fa-coins me-1"></i>
                                                <?php echo number_format($global_leaderboard[2]['eco_points']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Full Leaderboard -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Full Rankings</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($global_leaderboard as $index => $leader): ?>
                            <div class="leaderboard-item <?php echo $leader['id'] == $user['id'] ? 'border border-success' : ''; ?>">
                                <div class="leaderboard-rank <?php echo $index < 3 ? ($index == 0 ? 'gold' : ($index == 1 ? 'silver' : 'bronze')) : ''; ?>">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?php echo htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']); ?>
                                        <?php if ($leader['id'] == $user['id']): ?>
                                        <span class="badge bg-success ms-2">You</span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <i class="fas fa-school me-1"></i>
                                        <?php echo htmlspecialchars($leader['school_name'] ?: 'Independent'); ?>
                                    </small>
                                    <div class="mt-1">
                                        <span class="badge bg-info me-2">
                                            <i class="<?php echo $leader['level_icon']; ?> me-1"></i>
                                            <?php echo htmlspecialchars($leader['level_name']); ?>
                                        </span>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-medal me-1"></i>
                                            <?php echo $leader['total_badges']; ?> badges
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="eco-points">
                                        <i class="fas fa-coins me-1"></i>
                                        <?php echo number_format($leader['eco_points']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Personal Ranking -->
                <div class="tab-pane fade <?php echo $leaderboard_type === 'personal' ? 'show active' : ''; ?>"
                     id="personal" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card border-success h-100">
                                <div class="card-body">
                                    <h5 class="card-title text-success"><i class="fas fa-user me-2"></i>Your Stats</h5>
                                    <div class="d-flex align-items-center mb-3">
                                        <span class="badge bg-success me-2">Rank #<?php echo $personal_window ? $personal_window['rank'] : 'N/A'; ?></span>
                                        <span class="badge bg-secondary">Total <?php echo $personal_window ? $personal_window['total'] : 'N/A'; ?></span>
                                    </div>
                                    <p class="mb-1"><strong>Eco Points:</strong> <?php echo number_format($user['eco_points']); ?></p>
                                    <p class="mb-1"><strong>Tier:</strong> <span class="badge bg-<?php echo $user_tier['slug']; ?> text-uppercase"><?php echo $user_tier['name']; ?></span></p>
                                    <p class="mb-3"><strong>Badges:</strong> <?php echo $personal_window && isset($personal_window['rows']) ? array_reduce($personal_window['rows'], function($carry, $row) use ($user){ return $row['id'] == $user['id'] ? $row['total_badges'] : $carry; }, 0) : 0; ?></p>
                                    <?php if ($personal_window && $personal_window['rank'] > 1): ?>
                                        <?php
                                            $abovePeer = null;
                                            foreach ($personal_window['rows'] as $peer) {
                                                if ($peer['computed_rank'] < $personal_window['rank']) {
                                                    $abovePeer = $peer;
                                                }
                                            }
                                        ?>
                                        <?php if ($abovePeer): ?>
                                            <p class="mb-0 text-muted small">Only <strong><?php echo max(0, $abovePeer['eco_points'] - $user['eco_points']); ?></strong> points away from #<?php echo $abovePeer['computed_rank']; ?>!</p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-bullseye me-2"></i>Daily Actions to Climb</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item"><i class="fas fa-check-circle text-success me-2"></i>Complete a quiz to earn up to <strong>50</strong> points.</li>
                                        <li class="list-group-item"><i class="fas fa-book-open text-primary me-2"></i>Read new learning topics for <strong>10+</strong> points.</li>
                                        <li class="list-group-item"><i class="fas fa-trophy text-warning me-2"></i>Finish challenges and verify tasks for bonus points.</li>
                                        <li class="list-group-item"><i class="fas fa-calendar-check text-info me-2"></i>Maintain streaks to unlock badge multipliers.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Students Around Your Rank</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($personal_window && !empty($personal_window['rows'])): ?>
                                <?php foreach ($personal_window['rows'] as $peer): ?>
                                    <div class="leaderboard-item <?php echo $peer['id'] == $user['id'] ? 'border border-success' : ''; ?>">
                                        <div class="leaderboard-rank <?php echo $peer['id'] == $user['id'] ? 'gold' : ''; ?>">
                                            <?php echo $peer['computed_rank']; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($peer['first_name'] . ' ' . $peer['last_name']); ?>
                                                <?php if ($peer['id'] == $user['id']): ?>
                                                    <span class="badge bg-success ms-2">You</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-school me-1"></i>
                                                <?php echo htmlspecialchars($peer['school_name'] ?: 'Independent'); ?>
                                            </small>
                                            <div class="mt-1">
                                                <span class="badge bg-<?php echo $peer['tier']['slug']; ?> text-uppercase">Tier: <?php echo $peer['tier']['name']; ?></span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="eco-points"><i class="fas fa-coins me-1"></i><?php echo number_format($peer['eco_points']); ?></div>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-medal me-1"></i><?php echo $peer['total_badges']; ?> badges
                                            </small>
                                            <?php if ($peer['id'] != $user['id']): ?>
                                                <small class="text-muted">Δ <?php echo number_format($peer['eco_points'] - $user['eco_points']); ?> pts</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-4 text-center text-muted">No ranking data available yet. Start earning eco points!</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- School Rankings -->
                <div class="tab-pane fade <?php echo $leaderboard_type === 'schools' ? 'show active' : ''; ?>" 
                     id="schools" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-school me-2"></i>Top Schools</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($school_leaderboard as $index => $school): ?>
                            <div class="leaderboard-item <?php echo $school['name'] == $user['school_name'] ? 'border border-success' : ''; ?>">
                                <div class="leaderboard-rank <?php echo $index < 3 ? ($index == 0 ? 'gold' : ($index == 1 ? 'silver' : 'bronze')) : ''; ?>">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?php echo htmlspecialchars($school['name']); ?>
                                        <?php if ($school['name'] == $user['school_name']): ?>
                                        <span class="badge bg-success ms-2">Your School</span>
                                        <?php endif; ?>
                                    </h6>
                                    <?php 
                                        $city = $school['city'] ?? null;
                                        $state = $school['state'] ?? null;
                                        $location = trim(($city ?: '') . ', ' . ($state ?: ''), ', ');
                                    ?>
                                    <?php if ($location): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($location); ?>
                                    </small>
                                    <?php endif; ?>
                                    <div class="mt-1">
                                        <span class="badge bg-info me-2">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo $school['student_count']; ?> students
                                        </span>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-calculator me-1"></i>
                                            <?php echo number_format($school['avg_points_per_student']); ?> avg
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="eco-points">
                                        <i class="fas fa-coins me-1"></i>
                                        <?php echo number_format($school['total_points']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- My School Leaderboard -->
                <?php if (!empty($school_specific_leaderboard)): ?>
                <div class="tab-pane fade <?php echo $leaderboard_type === 'myschool' ? 'show active' : ''; ?>" 
                     id="myschool" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                <?php echo htmlspecialchars($user['school_name']); ?> Leaderboard
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($school_specific_leaderboard as $index => $leader): ?>
                            <div class="leaderboard-item <?php echo $leader['id'] == $user['id'] ? 'border border-success' : ''; ?>">
                                <div class="leaderboard-rank <?php echo $index < 3 ? ($index == 0 ? 'gold' : ($index == 1 ? 'silver' : 'bronze')) : ''; ?>">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?php echo htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']); ?>
                                        <?php if ($leader['id'] == $user['id']): ?>
                                        <span class="badge bg-success ms-2">You</span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">@<?php echo htmlspecialchars($leader['username']); ?></small>
                                    <div class="mt-1">
                                        <span class="badge bg-info me-2">
                                            <i class="<?php echo $leader['level_icon']; ?> me-1"></i>
                                            <?php echo htmlspecialchars($leader['level_name']); ?>
                                        </span>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-medal me-1"></i>
                                            <?php echo $leader['total_badges']; ?> badges
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="eco-points">
                                        <i class="fas fa-coins me-1"></i>
                                        <?php echo number_format($leader['eco_points']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Achievements -->
                <div class="tab-pane fade <?php echo $leaderboard_type === 'achievements' ? 'show active' : ''; ?>" 
                     id="achievements" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-star me-2"></i>Recent Achievements (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_achievements)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Recent Achievements</h5>
                                <p class="text-muted">Be the first to earn a badge this week!</p>
                            </div>
                            <?php else: ?>
                            <div class="row">
                                <?php foreach ($recent_achievements as $achievement): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-warning">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <i class="<?php echo $achievement['icon']; ?> fa-2x text-warning"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($achievement['badge_name']); ?></h6>
                                                    <p class="mb-1">
                                                        <strong><?php echo htmlspecialchars($achievement['first_name'] . ' ' . $achievement['last_name']); ?></strong>
                                                    </p>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($achievement['school_name'] ?: 'Independent'); ?> • 
                                                        <?php echo formatTimeAgo($achievement['earned_at']); ?>
                                                    </small>
                                                </div>
                                            </div>
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
        </div>
    </main>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <style>
        .podium-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .podium-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
            transition: all 0.3s ease;
        }
        
        .podium-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .podium-card.gold {
            border: 3px solid #ffd700;
            transform: scale(1.1);
        }
        
        .podium-card.silver {
            border: 3px solid #c0c0c0;
        }
        
        .podium-card.bronze {
            border: 3px solid #cd7f32;
        }
        
        .podium-rank {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .podium-card.gold .podium-rank {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #333;
        }
        
        .podium-card.silver .podium-rank {
            background: linear-gradient(45deg, #c0c0c0, #e8e8e8);
            color: #333;
        }
        
        .podium-card.bronze .podium-rank {
            background: linear-gradient(45deg, #cd7f32, #daa520);
        }
        
        .crown {
            position: absolute;
            top: -10px;
            right: 10px;
            font-size: 1.5rem;
        }
        
        [data-theme="dark"] .podium-container {
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--dark-surface) 100%);
        }
        
        [data-theme="dark"] .podium-card {
            background: var(--dark-surface);
            color: var(--dark-text);
        }
        
        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
        }
        
        .leaderboard-item:hover {
            background: #f8f9fa;
        }
        
        .leaderboard-item:last-child {
            border-bottom: none;
        }
        
        .leaderboard-rank {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            background: #e9ecef;
            color: #495057;
        }
        
        .leaderboard-rank.gold {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #333;
        }
        
        .leaderboard-rank.silver {
            background: linear-gradient(45deg, #c0c0c0, #e8e8e8);
            color: #333;
        }
        
        .leaderboard-rank.bronze {
            background: linear-gradient(45deg, #cd7f32, #daa520);
            color: white;
        }
    </style>
</body>
</html>
