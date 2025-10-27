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

// Handle profile update
$message = '';
$message_type = '';

if ($_POST && isset($_POST['update_profile'])) {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $school_name = sanitizeInput($_POST['school_name']);
    $grade_level = sanitizeInput($_POST['grade_level']);
    $bio = sanitizeInput($_POST['bio']);
    $profile_image = $user['profile_image']; // Keep current image by default
    
    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profiles/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_info = pathinfo($_FILES['profile_image']['name']);
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_extension = strtolower($file_info['extension']);
        
        if (in_array($file_extension, $allowed_types)) {
            // Check file size (max 5MB)
            if ($_FILES['profile_image']['size'] <= 5 * 1024 * 1024) {
                $new_filename = 'profile_' . $user['id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Delete old profile image if it's not the default
                    if ($user['profile_image'] !== 'default-avatar.png' && file_exists('uploads/profiles/' . $user['profile_image'])) {
                        unlink('uploads/profiles/' . $user['profile_image']);
                    }
                    $profile_image = $new_filename;
                } else {
                    $message = 'Failed to upload profile image';
                    $message_type = 'error';
                }
            } else {
                $message = 'Profile image must be less than 5MB';
                $message_type = 'error';
            }
        } else {
            $message = 'Invalid file type. Please upload JPG, PNG, GIF, or WebP images only.';
            $message_type = 'error';
        }
    }
    
    // Update profile if no upload errors
    if (empty($message)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, school_name = ?, grade_level = ?, bio = ?, profile_image = ? WHERE id = ?");
            if ($stmt->execute([$first_name, $last_name, $school_name, $grade_level, $bio, $profile_image, $_SESSION['user_id']])) {
                $message = 'Profile updated successfully!';
                $message_type = 'success';
                // Refresh user data
                $user = getUserById($_SESSION['user_id']);
            } else {
                $message = 'Failed to update profile';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error updating profile: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get user statistics
$user_stats = [];

// Quiz statistics
try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_attempts,
        COUNT(CASE WHEN is_passed = 1 THEN 1 END) as passed_quizzes,
        AVG(score) as avg_score,
        MAX(score) as best_score
        FROM quiz_attempts WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user_stats['quizzes'] = $stmt->fetch();
} catch (Exception $e) {
    $user_stats['quizzes'] = ['total_attempts' => 0, 'passed_quizzes' => 0, 'avg_score' => 0, 'best_score' => 0];
}

// Challenge statistics
try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_challenges,
        COUNT(CASE WHEN status = 'verified' THEN 1 END) as completed_challenges,
        SUM(points_earned) as total_points_earned
        FROM challenge_participation WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user_stats['challenges'] = $stmt->fetch();
} catch (Exception $e) {
    $user_stats['challenges'] = ['total_challenges' => 0, 'completed_challenges' => 0, 'total_points_earned' => 0];
}

// Get user badges
try {
    $stmt = $pdo->prepare("SELECT b.name, b.description, b.icon, ub.earned_at 
                          FROM user_badges ub 
                          JOIN badges b ON ub.badge_id = b.id 
                          WHERE ub.user_id = ? 
                          ORDER BY ub.earned_at DESC");
    $stmt->execute([$user['id']]);
    $user_badges = $stmt->fetchAll();
} catch (Exception $e) {
    $user_badges = [];
}

// Get recent activities
try {
    $stmt = $pdo->prepare("
        SELECT 'quiz' as type, q.title, qa.completed_at as date, qa.score, qa.is_passed
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.user_id = ?
        UNION ALL
        SELECT 'challenge' as type, c.title, cp.joined_at as date, cp.points_earned as score, (cp.status = 'verified') as is_passed
        FROM challenge_participation cp
        JOIN challenges c ON cp.challenge_id = c.id
        WHERE cp.user_id = ?
        ORDER BY date DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_activities = [];
}

// Get user level info
try {
    $stmt = $pdo->prepare("SELECT * FROM levels WHERE id = ?");
    $stmt->execute([$user['level_id']]);
    $current_level = $stmt->fetch();
    
    // Get next level
    $stmt = $pdo->prepare("SELECT * FROM levels WHERE min_points > ? ORDER BY min_points ASC LIMIT 1");
    $stmt->execute([$user['eco_points']]);
    $next_level = $stmt->fetch();
} catch (Exception $e) {
    $current_level = ['name' => 'Eco Newbie', 'icon' => 'fas fa-seedling', 'min_points' => 0];
    $next_level = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - EcoEdu</title>
    
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
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
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
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Header -->
                <div class="col-12 mb-4">
                    <div class="card profile-header">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3 text-center">
                                    <div class="profile-avatar mb-3 position-relative">
                                        <?php 
                                        $profile_image_path = 'uploads/profiles/' . ($user['profile_image'] ?? 'default-avatar.png');
                                        if ($user['profile_image'] && $user['profile_image'] !== 'default-avatar.png' && file_exists($profile_image_path)): 
                                        ?>
                                            <img src="<?php echo $profile_image_path; ?>" alt="Profile Picture" class="profile-image">
                                        <?php else: ?>
                                            <div class="default-avatar">
                                                <i class="fas fa-user-circle fa-6x text-success"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="profile-image-overlay">
                                            <button class="btn btn-sm btn-light rounded-circle" data-mdb-toggle="modal" data-mdb-target="#editProfileModal" title="Change Photo">
                                                <i class="fas fa-camera"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" data-mdb-toggle="modal" data-mdb-target="#editProfileModal">
                                        <i class="fas fa-edit me-1"></i>Edit Profile
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <h2 class="mb-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                                    <p class="text-muted mb-2">@<?php echo htmlspecialchars($user['username']); ?></p>
                                    <?php if ($user['school_name']): ?>
                                    <p class="mb-2">
                                        <i class="fas fa-school me-2"></i>
                                        <?php echo htmlspecialchars($user['school_name']); ?>
                                        <?php if ($user['grade_level']): ?>
                                        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($user['grade_level']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                    <?php if (isset($user['bio']) && $user['bio']): ?>
                                    <p class="text-muted"><?php echo htmlspecialchars($user['bio']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-muted">
                                        <i class="fas fa-calendar me-2"></i>
                                        Joined <?php echo date('F Y', strtotime($user['created_at'])); ?>
                                    </p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="level-info mb-3">
                                        <i class="<?php echo $current_level['icon'] ?? 'fas fa-seedling'; ?> fa-3x text-warning mb-2"></i>
                                        <h5><?php echo htmlspecialchars($current_level['name'] ?? 'Eco Newbie'); ?></h5>
                                        <div class="eco-points">
                                            <i class="fas fa-coins me-1"></i>
                                            <?php echo number_format($user['eco_points']); ?> Points
                                        </div>
                                        <?php if ($next_level): ?>
                                        <div class="progress mt-2">
                                            <div class="progress-bar" style="width: <?php echo min(100, ($user['eco_points'] / $next_level['min_points']) * 100); ?>%"></div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $next_level['min_points'] - $user['eco_points']; ?> points to <?php echo $next_level['name']; ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="col-lg-8 mb-4">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-primary">
                                    <i class="fas fa-brain"></i>
                                </div>
                                <div class="stat-value text-primary"><?php echo $user_stats['quizzes']['total_attempts']; ?></div>
                                <div class="stat-label">Quiz Attempts</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-value text-success"><?php echo $user_stats['quizzes']['passed_quizzes']; ?></div>
                                <div class="stat-label">Quizzes Passed</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-warning">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="stat-value text-warning"><?php echo $user_stats['challenges']['completed_challenges']; ?></div>
                                <div class="stat-label">Challenges Done</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-info">
                                    <i class="fas fa-medal"></i>
                                </div>
                                <div class="stat-value text-info"><?php echo count($user_badges); ?></div>
                                <div class="stat-label">Badges Earned</div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_activities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No recent activity</p>
                                <a href="dashboard.php" class="btn btn-primary">Start Learning</a>
                            </div>
                            <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($recent_activities as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker">
                                        <i class="fas fa-<?php echo $activity['type'] === 'quiz' ? 'brain' : 'trophy'; ?> text-<?php echo $activity['is_passed'] ? 'success' : 'warning'; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1">
                                            <?php echo $activity['type'] === 'quiz' ? 'Completed Quiz' : 'Joined Challenge'; ?>: 
                                            <?php echo htmlspecialchars($activity['title']); ?>
                                        </h6>
                                        <p class="text-muted mb-1">
                                            <?php if ($activity['type'] === 'quiz'): ?>
                                                Score: <?php echo round($activity['score']); ?>% 
                                                <span class="badge bg-<?php echo $activity['is_passed'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $activity['is_passed'] ? 'Passed' : 'Failed'; ?>
                                                </span>
                                            <?php else: ?>
                                                <?php if ($activity['score'] > 0): ?>
                                                <span class="badge bg-success">+<?php echo $activity['score']; ?> points</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </p>
                                        <small class="text-muted"><?php echo formatTimeAgo($activity['date']); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Badges -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-medal me-2"></i>Badges Earned</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($user_badges)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-medal fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No badges earned yet</p>
                                <a href="challenges.php" class="btn btn-outline-primary btn-sm">Start Challenges</a>
                            </div>
                            <?php else: ?>
                            <div class="badges-grid">
                                <?php foreach ($user_badges as $badge): ?>
                                <div class="badge-item text-center mb-3">
                                    <i class="<?php echo $badge['icon']; ?> fa-2x text-warning mb-2"></i>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($badge['name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($badge['description']); ?></small>
                                    <br><small class="text-muted">Earned <?php echo formatTimeAgo($badge['earned_at']); ?></small>
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

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <!-- Profile Photo Upload -->
                        <div class="mb-4 text-center">
                            <div class="profile-photo-preview mb-3">
                                <?php 
                                $profile_image_path = 'uploads/profiles/' . ($user['profile_image'] ?? 'default-avatar.png');
                                if ($user['profile_image'] && $user['profile_image'] !== 'default-avatar.png' && file_exists($profile_image_path)): 
                                ?>
                                    <img src="<?php echo $profile_image_path; ?>" alt="Profile Picture" class="preview-image" id="imagePreview">
                                <?php else: ?>
                                    <div class="preview-placeholder" id="imagePreview">
                                        <i class="fas fa-user-circle fa-4x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="profileImageInput" class="btn btn-outline-primary">
                                    <i class="fas fa-camera me-2"></i>Choose Photo
                                </label>
                                <input type="file" id="profileImageInput" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this)">
                            </div>
                            <small class="text-muted">JPG, PNG, GIF, or WebP. Max size: 5MB</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-outline mb-3">
                                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    <label class="form-label">First Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-outline mb-3">
                                    <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    <label class="form-label">Last Name</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-outline mb-3">
                            <input type="text" class="form-control" name="school_name" value="<?php echo htmlspecialchars($user['school_name']); ?>">
                            <label class="form-label">School Name</label>
                        </div>
                        
                        <div class="form-outline mb-3">
                            <input type="text" class="form-control" name="grade_level" value="<?php echo htmlspecialchars($user['grade_level']); ?>">
                            <label class="form-label">Grade Level</label>
                        </div>
                        
                        <div class="form-outline mb-3">
                            <textarea class="form-control" name="bio" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <label class="form-label">Bio</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
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
        
        /* Profile Image Styles */
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #28a745;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .profile-avatar {
            position: relative;
            display: inline-block;
        }
        
        .profile-image-overlay {
            position: absolute;
            bottom: 10px;
            right: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .profile-avatar:hover .profile-image-overlay {
            opacity: 1;
        }
        
        .preview-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
        }
        
        .preview-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px dashed #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            background: #f8f9fa;
        }
        
        .profile-photo-preview {
            position: relative;
        }
        
        .photo-upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }
        
        .profile-photo-preview:hover .photo-upload-overlay {
            opacity: 1;
        }
        
        .default-avatar {
            filter: drop-shadow(0 4px 15px rgba(40, 167, 69, 0.3));
        }
        
        /* Animation for image change */
        .image-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
    
    <script>
        // Image preview functionality
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Create new image element
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'preview-image image-fade-in';
                    img.alt = 'Profile Picture Preview';
                    
                    // Replace preview content
                    preview.innerHTML = '';
                    preview.appendChild(img);
                    
                    // Add upload overlay
                    const overlay = document.createElement('div');
                    overlay.className = 'photo-upload-overlay';
                    overlay.innerHTML = '<i class="fas fa-camera text-white fa-2x"></i>';
                    overlay.onclick = () => input.click();
                    preview.appendChild(overlay);
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Add drag and drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const preview = document.getElementById('imagePreview');
            const fileInput = document.getElementById('profileImageInput');
            
            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                preview.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });
            
            // Highlight drop area when item is dragged over it
            ['dragenter', 'dragover'].forEach(eventName => {
                preview.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                preview.addEventListener(eventName, unhighlight, false);
            });
            
            // Handle dropped files
            preview.addEventListener('drop', handleDrop, false);
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            function highlight(e) {
                preview.style.border = '3px dashed #007bff';
                preview.style.background = 'rgba(0, 123, 255, 0.1)';
            }
            
            function unhighlight(e) {
                preview.style.border = '';
                preview.style.background = '';
            }
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length > 0) {
                    fileInput.files = files;
                    previewImage(fileInput);
                }
            }
            
            // Add click to upload functionality
            preview.addEventListener('click', function() {
                fileInput.click();
            });
            
            // File size validation
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) { // 5MB
                        alert('File size must be less than 5MB');
                        this.value = '';
                        return;
                    }
                    
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Please upload only JPG, PNG, GIF, or WebP images');
                        this.value = '';
                        return;
                    }
                }
            });
        });
    </script>
</body>
</html>
