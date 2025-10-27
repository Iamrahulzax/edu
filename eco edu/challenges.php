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

// Handle challenge actions
$message = '';
$message_type = '';

if ($_POST) {
    switch ($_POST['action']) {
        case 'start_challenge':
            $challenge_id = (int)$_POST['challenge_id'];
            try {
                // Check if user already started this challenge
                $stmt = $pdo->prepare("SELECT id FROM user_challenge_progress WHERE user_id = ? AND challenge_id = ?");
                $stmt->execute([$user['id'], $challenge_id]);
                
                if (!$stmt->fetch()) {
                    // Start the challenge
                    $stmt = $pdo->prepare("INSERT INTO user_challenge_progress (user_id, challenge_id, status) VALUES (?, ?, 'started')");
                    $stmt->execute([$user['id'], $challenge_id]);
                    $message = 'Challenge started successfully! Good luck making a difference!';
                    $message_type = 'success';
                } else {
                    $message = 'You have already started this challenge.';
                    $message_type = 'warning';
                }
            } catch (Exception $e) {
                $message = 'Error starting challenge: ' . $e->getMessage();
                $message_type = 'error';
            }
            break;
            
        case 'submit_proof':
            $challenge_id = (int)$_POST['challenge_id'];
            $description = sanitizeInput($_POST['description']);
            $location = sanitizeInput($_POST['location']);
            
            // Handle file upload
            $photo_path = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/challenges/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $photo_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                        // Submit the challenge
                        try {
                            $stmt = $pdo->prepare("INSERT INTO challenge_submissions (user_id, challenge_id, photo_path, description, location, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                            $stmt->execute([$user['id'], $challenge_id, $photo_path, $description, $location]);
                            
                            // Update progress status
                            $stmt = $pdo->prepare("UPDATE user_challenge_progress SET status = 'submitted' WHERE user_id = ? AND challenge_id = ?");
                            $stmt->execute([$user['id'], $challenge_id]);
                            
                            $message = 'Challenge submission uploaded successfully! Wait for admin approval.';
                            $message_type = 'success';
                        } catch (Exception $e) {
                            $message = 'Error submitting challenge: ' . $e->getMessage();
                            $message_type = 'error';
                        }
                    } else {
                        $message = 'Error uploading photo. Please try again.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid file type. Please upload JPG, PNG, or GIF images only.';
                    $message_type = 'error';
                }
            } else {
                $message = 'Please upload a photo as proof of completion.';
                $message_type = 'error';
            }
            break;
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';

// Get challenge categories
try {
    $stmt = $pdo->query("SELECT * FROM challenge_categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Get eco challenges with filters
try {
    $where_conditions = ["ec.is_active = 1"];
    $params = [];
    
    if ($category_filter) {
        $where_conditions[] = "ec.category_id = ?";
        $params[] = $category_filter;
    }
    
    if ($difficulty_filter) {
        $where_conditions[] = "ec.difficulty_level = ?";
        $params[] = $difficulty_filter;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $query = "SELECT ec.*, cc.name as category_name, cc.icon as category_icon, cc.color as category_color 
              FROM eco_challenges ec 
              LEFT JOIN challenge_categories cc ON ec.category_id = cc.id 
              $where_clause 
              ORDER BY ec.ecopoints_reward DESC, ec.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $challenges = $stmt->fetchAll();
} catch (Exception $e) {
    $challenges = [];
}

// Get user's challenge progress
try {
    $stmt = $pdo->prepare("
        SELECT ucp.*, cs.status as submission_status, cs.ecopoints_awarded, cs.approved_at
        FROM user_challenge_progress ucp
        LEFT JOIN challenge_submissions cs ON ucp.user_id = cs.user_id AND ucp.challenge_id = cs.challenge_id
        WHERE ucp.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $user_progress = [];
    while ($row = $stmt->fetch()) {
        $user_progress[$row['challenge_id']] = $row;
    }
} catch (Exception $e) {
    $user_progress = [];
}

// Get user's earned rewards
try {
    $stmt = $pdo->prepare("
        SELECT er.name, er.description, er.icon, er.color, ur.earned_at
        FROM user_rewards ur
        JOIN eco_rewards er ON ur.reward_id = er.id
        WHERE ur.user_id = ?
        ORDER BY ur.earned_at DESC
    ");
    $stmt->execute([$user['id']]);
    $user_rewards = $stmt->fetchAll();
} catch (Exception $e) {
    $user_rewards = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌳 Green Challenges - EcoEdu</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .challenge-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .challenge-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .category-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            border-radius: 50px;
            padding: 8px 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .difficulty-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .difficulty-easy { background: #d4edda; color: #155724; }
        .difficulty-medium { background: #fff3cd; color: #856404; }
        .difficulty-hard { background: #f8d7da; color: #721c24; }
        .ecopoints-display {
            background: linear-gradient(135deg, #ffc107, #ff8f00);
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 15px;
        }
        .progress-tracker {
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 15px;
            padding: 20px;
            color: white;
            margin-bottom: 30px;
        }
        .reward-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 25px;
            margin: 5px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .submission-form {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
        }
        .photo-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 10px;
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
                        <a class="nav-link text-white" href="games.php">
                            <i class="fas fa-gamepad me-1"></i>Games
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="challenges.php">
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
                            <h1 class="text-gradient"><i class="fas fa-seedling me-2"></i>🌳 Green Challenges</h1>
                            <p class="text-muted">Connect virtual learning with real-world environmental action!</p>
                        </div>
                        <div class="text-end">
                            <div class="ecopoints-display">
                                <i class="fas fa-coins me-2"></i>
                                <?php echo number_format($user['eco_points']); ?> EcoPoints
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($message): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'danger'); ?> alert-dismissible fade show">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Progress Tracker -->
            <?php 
            $total_challenges = count($challenges);
            $started_challenges = count($user_progress);
            $completed_challenges = 0;
            $pending_challenges = 0;
            $total_earned_points = 0;
            
            foreach ($user_progress as $progress) {
                if ($progress['submission_status'] === 'approved') {
                    $completed_challenges++;
                    $total_earned_points += $progress['ecopoints_awarded'];
                } elseif ($progress['submission_status'] === 'pending') {
                    $pending_challenges++;
                }
            }
            
            $completion_percentage = $total_challenges > 0 ? round(($completed_challenges / $total_challenges) * 100) : 0;
            ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="progress-tracker">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4><i class="fas fa-chart-line me-2"></i>Your Green Journey</h4>
                                <div class="progress mb-3" style="height: 10px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $completion_percentage; ?>%" aria-valuenow="<?php echo $completion_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <p class="mb-0"><?php echo $completed_challenges; ?> of <?php echo $total_challenges; ?> challenges completed (<?php echo $completion_percentage; ?>%)</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="d-flex justify-content-end gap-3">
                                    <div class="text-center">
                                        <div class="h3 mb-0"><?php echo $started_challenges; ?></div>
                                        <small>Started</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="h3 mb-0"><?php echo $pending_challenges; ?></div>
                                        <small>Pending</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="h3 mb-0"><?php echo $completed_challenges; ?></div>
                                        <small>Completed</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Earned Rewards -->
            <?php if (!empty($user_rewards)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5><i class="fas fa-award me-2"></i>Your Achievements</h5>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($user_rewards as $reward): ?>
                                <span class="reward-badge" style="background-color: <?php echo $reward['color']; ?>; color: white;">
                                    <i class="<?php echo $reward['icon']; ?> me-1"></i>
                                    <?php echo htmlspecialchars($reward['name']); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5><i class="fas fa-filter me-2"></i>Filter Challenges</h5>
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
                                    <label for="difficulty" class="form-label">Difficulty Level</label>
                                    <select class="form-select" id="difficulty" name="difficulty">
                                        <option value="">All Levels</option>
                                        <option value="easy" <?php echo $difficulty_filter === 'easy' ? 'selected' : ''; ?>>🟢 Easy</option>
                                        <option value="medium" <?php echo $difficulty_filter === 'medium' ? 'selected' : ''; ?>>🟡 Medium</option>
                                        <option value="hard" <?php echo $difficulty_filter === 'hard' ? 'selected' : ''; ?>>🔴 Hard</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-1"></i>Apply Filters
                                    </button>
                                    <a href="challenges.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-refresh me-1"></i>Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Green Challenges Grid -->
            <div class="row">
                <div class="col-12 mb-4">
                    <h3><i class="fas fa-leaf me-2"></i>Available Eco Challenges</h3>
                    <p class="text-muted">Choose a challenge, complete real-world environmental action, and earn EcoPoints!</p>
                </div>
                
                <?php if (empty($challenges)): ?>
                <div class="col-12">
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-seedling fa-4x text-muted mb-4"></i>
                            <h3 class="text-muted">No Green Challenges Found</h3>
                            <p class="text-muted">Try adjusting your filters or check back later for new environmental challenges.</p>
                            <a href="challenges.php" class="btn btn-success">View All Challenges</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($challenges as $challenge): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card challenge-card h-100 position-relative">
                        <!-- Difficulty Badge -->
                        <div class="difficulty-badge difficulty-<?php echo $challenge['difficulty_level']; ?>">
                            <?php echo ucfirst($challenge['difficulty_level']); ?>
                        </div>
                        
                        <!-- Category Badge -->
                        <div class="category-badge" style="background-color: <?php echo $challenge['category_color']; ?>; color: white;">
                            <i class="<?php echo $challenge['category_icon']; ?> me-1"></i>
                            <?php echo htmlspecialchars($challenge['category_name']); ?>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <!-- Challenge Title & Description -->
                            <div class="mb-3 mt-4">
                                <h5 class="card-title fw-bold"><?php echo htmlspecialchars($challenge['title']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($challenge['description']); ?></p>
                            </div>
                            
                            <!-- EcoPoints Reward -->
                            <div class="text-center mb-3">
                                <div class="ecopoints-display d-inline-block">
                                    <i class="fas fa-coins me-2"></i>
                                    <?php echo $challenge['ecopoints_reward']; ?> EcoPoints
                                </div>
                            </div>
                            
                            <!-- Challenge Details -->
                            <div class="challenge-info mb-3">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <i class="fas fa-clock text-info"></i>
                                        <small class="d-block text-muted"><?php echo $challenge['estimated_time']; ?></small>
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-camera text-primary"></i>
                                        <small class="d-block text-muted">Photo Required</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Instructions Preview -->
                            <?php if ($challenge['instructions']): ?>
                            <div class="instructions-preview mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php echo substr(htmlspecialchars($challenge['instructions']), 0, 100) . '...'; ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <!-- User Progress Status -->
                            <?php if (isset($user_progress[$challenge['id']])): ?>
                            <div class="user-progress mb-3">
                                <?php 
                                $progress = $user_progress[$challenge['id']];
                                $status = $progress['submission_status'] ?: $progress['status'];
                                $status_info = [
                                    'started' => ['color' => 'info', 'icon' => 'fa-play', 'text' => 'In Progress'],
                                    'submitted' => ['color' => 'warning', 'icon' => 'fa-clock', 'text' => 'Under Review'],
                                    'pending' => ['color' => 'warning', 'icon' => 'fa-clock', 'text' => 'Pending Approval'],
                                    'approved' => ['color' => 'success', 'icon' => 'fa-check-circle', 'text' => 'Completed'],
                                    'rejected' => ['color' => 'danger', 'icon' => 'fa-times-circle', 'text' => 'Rejected']
                                ];
                                $current_status = $status_info[$status] ?? $status_info['started'];
                                ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $current_status['color']; ?>">
                                        <i class="fas <?php echo $current_status['icon']; ?> me-1"></i>
                                        <?php echo $current_status['text']; ?>
                                    </span>
                                    <?php if ($progress['ecopoints_awarded'] > 0): ?>
                                    <span class="text-success fw-bold">
                                        <i class="fas fa-coins me-1"></i>+<?php echo $progress['ecopoints_awarded']; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($progress['approved_at']): ?>
                                <small class="text-muted">
                                    Completed <?php echo date('M j, Y', strtotime($progress['approved_at'])); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Action Button -->
                            <div class="mt-auto">
                                <?php if (isset($user_progress[$challenge['id']])): ?>
                                    <?php 
                                    $progress = $user_progress[$challenge['id']];
                                    $submission_status = $progress['submission_status'];
                                    ?>
                                    <?php if ($submission_status === 'approved'): ?>
                                    <button class="btn btn-success w-100" disabled>
                                        <i class="fas fa-trophy me-2"></i>Challenge Completed!
                                    </button>
                                    <?php elseif ($submission_status === 'pending'): ?>
                                    <button class="btn btn-warning w-100" disabled>
                                        <i class="fas fa-hourglass-half me-2"></i>Awaiting Approval
                                    </button>
                                    <?php elseif ($progress['status'] === 'submitted'): ?>
                                    <button class="btn btn-warning w-100" disabled>
                                        <i class="fas fa-hourglass-half me-2"></i>Under Review
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-info w-100" onclick="showSubmissionForm(<?php echo $challenge['id']; ?>)">
                                        <i class="fas fa-upload me-2"></i>Submit Proof
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                <form method="POST" class="d-inline w-100">
                                    <input type="hidden" name="action" value="start_challenge">
                                    <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-play me-2"></i>Start Challenge
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- How Green Challenges Work -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card" style="background: linear-gradient(135deg, #e8f5e8, #f0f8f0);">
                        <div class="card-body">
                            <h3 class="text-center mb-4">
                                <i class="fas fa-info-circle text-success me-2"></i>How Green Challenges Work
                            </h3>
                            <div class="row">
                                <div class="col-md-3 text-center mb-4">
                                    <div class="step-icon mb-3">
                                        <i class="fas fa-play-circle fa-3x text-success"></i>
                                    </div>
                                    <h5 class="text-success">1. Start Challenge</h5>
                                    <p class="text-muted">Choose an eco-friendly challenge and click "Start Challenge" to begin your green journey</p>
                                </div>
                                <div class="col-md-3 text-center mb-4">
                                    <div class="step-icon mb-3">
                                        <i class="fas fa-leaf fa-3x text-info"></i>
                                    </div>
                                    <h5 class="text-info">2. Take Action</h5>
                                    <p class="text-muted">Complete the real-world environmental activity following the provided instructions</p>
                                </div>
                                <div class="col-md-3 text-center mb-4">
                                    <div class="step-icon mb-3">
                                        <i class="fas fa-camera fa-3x text-warning"></i>
                                    </div>
                                    <h5 class="text-warning">3. Upload Proof</h5>
                                    <p class="text-muted">Take a photo of your completed action and upload it as proof with a description</p>
                                </div>
                                <div class="col-md-3 text-center mb-4">
                                    <div class="step-icon mb-3">
                                        <i class="fas fa-award fa-3x text-primary"></i>
                                    </div>
                                    <h5 class="text-primary">4. Earn Rewards</h5>
                                    <p class="text-muted">Get admin approval and earn EcoPoints, badges, and certificates for your impact!</p>
                                </div>
                            </div>
                            
                            <!-- Impact Stats -->
                            <div class="row mt-4 pt-4 border-top">
                                <div class="col-md-12 text-center">
                                    <h5 class="text-success mb-3">🌍 Make a Real Environmental Impact</h5>
                                    <div class="row">
                                        <div class="col-md-3 col-6 mb-2">
                                            <div class="h4 text-success mb-0">🌳</div>
                                            <small class="text-muted">Plant Trees</small>
                                        </div>
                                        <div class="col-md-3 col-6 mb-2">
                                            <div class="h4 text-info mb-0">♻️</div>
                                            <small class="text-muted">Reduce Waste</small>
                                        </div>
                                        <div class="col-md-3 col-6 mb-2">
                                            <div class="h4 text-warning mb-0">🧹</div>
                                            <small class="text-muted">Clean Environment</small>
                                        </div>
                                        <div class="col-md-3 col-6 mb-2">
                                            <div class="h4 text-primary mb-0">💧</div>
                                            <small class="text-muted">Save Water</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Photo Submission Modal -->
    <div class="modal fade" id="submissionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i>Submit Challenge Proof
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="submissionForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="submit_proof">
                        <input type="hidden" name="challenge_id" id="modalChallengeId">
                        
                        <div class="submission-form">
                            <div class="mb-4">
                                <label for="photo" class="form-label">
                                    <i class="fas fa-camera me-2"></i>Upload Photo Proof *
                                </label>
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/*" required>
                                <div class="form-text">Upload a clear photo showing your completed environmental action. Max size: 5MB</div>
                                <div id="photoPreview" class="mt-3"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label">
                                    <i class="fas fa-pen me-2"></i>Description *
                                </label>
                                <textarea class="form-control" id="description" name="description" rows="4" placeholder="Describe what you did, how you completed the challenge, and any additional details..." required></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label for="location" class="form-label">
                                    <i class="fas fa-map-marker-alt me-2"></i>Location (Optional)
                                </label>
                                <input type="text" class="form-control" id="location" name="location" placeholder="Where did you complete this challenge?">
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Submission Guidelines:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Photo must clearly show your completed environmental action</li>
                                    <li>Provide detailed description of what you accomplished</li>
                                    <li>Be honest and authentic in your submission</li>
                                    <li>Admin will review and approve within 24-48 hours</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane me-2"></i>Submit for Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        // Show submission modal
        function showSubmissionForm(challengeId) {
            document.getElementById('modalChallengeId').value = challengeId;
            const modal = new mdb.Modal(document.getElementById('submissionModal'));
            modal.show();
        }
        
        // Photo preview functionality
        document.getElementById('photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('photoPreview');
            
            if (file) {
                // Check file size (5MB limit)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    e.target.value = '';
                    preview.innerHTML = '';
                    return;
                }
                
                // Check file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file');
                    e.target.value = '';
                    preview.innerHTML = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <div class="text-center">
                            <img src="${e.target.result}" class="photo-preview img-fluid" alt="Photo preview">
                            <p class="text-muted mt-2">Photo preview - ${file.name}</p>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });
        
        // Form submission with loading state
        document.getElementById('submissionForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            
            // Note: Form will submit normally, loading state is just for UX
        });
        
        // Auto-scroll to challenges section if there's a message
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Scroll to top to show the message
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        <?php endif; ?>
        
        // Challenge card hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const challengeCards = document.querySelectorAll('.challenge-card');
            
            challengeCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
        
        // Animate progress bar on page load
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.querySelector('.progress-bar');
            if (progressBar) {
                const targetWidth = progressBar.style.width;
                progressBar.style.width = '0%';
                
                setTimeout(() => {
                    progressBar.style.transition = 'width 2s ease-in-out';
                    progressBar.style.width = targetWidth;
                }, 500);
            }
        });
        
        // Add success animation for completed challenges
        document.addEventListener('DOMContentLoaded', function() {
            const completedCards = document.querySelectorAll('.btn-success[disabled]');
            completedCards.forEach(btn => {
                if (btn.textContent.includes('Completed')) {
                    btn.closest('.challenge-card').style.borderLeft = '5px solid #28a745';
                    btn.closest('.challenge-card').style.background = 'linear-gradient(135deg, #f8fff9 0%, #ffffff 100%)';
                }
            });
        });
    </script>
</body>
</html>
