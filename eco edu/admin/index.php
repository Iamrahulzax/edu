<?php
require_once 'admin_header.php';
// $user is already available from admin_header.php

// Get dashboard statistics (with error handling)
$stats = [];

// Total users
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $stmt->execute();
    $stats['total_students'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['total_students'] = 0;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
    $stmt->execute();
    $stats['total_teachers'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['total_teachers'] = 0;
}

// Total content
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM quizzes WHERE is_active = 1");
    $stmt->execute();
    $stats['total_quizzes'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['total_quizzes'] = 0;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM challenges WHERE is_active = 1");
    $stmt->execute();
    $stats['total_challenges'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['total_challenges'] = 0;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM learning_content WHERE is_active = 1");
    $stmt->execute();
    $stats['total_content'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['total_content'] = 0;
}

// Activity stats (with error handling)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM quiz_attempts WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $stats['weekly_quiz_attempts'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['weekly_quiz_attempts'] = 0;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM challenge_participation WHERE joined_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $stats['weekly_challenge_joins'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['weekly_challenge_joins'] = 0;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $stats['weekly_new_users'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['weekly_new_users'] = 0;
}

// Recent activities (with error handling)
try {
    $stmt = $pdo->prepare("
        SELECT 'user_registered' as type, CONCAT(first_name, ' ', last_name) as title, created_at as date, school_name as details
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND role = 'student'
        ORDER BY date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_activities = [];
}

// Pending verifications (with error handling)
try {
    $stmt = $pdo->prepare("
        SELECT cp.*, u.first_name, u.last_name, c.title as challenge_title
        FROM challenge_participation cp
        JOIN users u ON cp.user_id = u.id
        JOIN challenges c ON cp.challenge_id = c.id
        WHERE cp.status = 'submitted'
        ORDER BY cp.submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $pending_verifications = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_verifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EcoEdu</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .stat-card-admin {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 5px solid;
        }
        .stat-card-admin:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
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
                <a class="nav-link text-white active" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a class="nav-link text-white" href="users.php">
                    <i class="fas fa-users-cog me-2"></i>Users Management
                </a>
                <a class="nav-link text-white" href="content.php">
                    <i class="fas fa-book me-2"></i>Learning Content
                </a>
                <a class="nav-link text-white" href="quizzes.php">
                    <i class="fas fa-question-circle me-2"></i>Quizzes
                </a>
                <a class="nav-link text-white" href="challenges.php">
                    <i class="fas fa-trophy me-2"></i>Challenges
                </a>
                <a class="nav-link text-white" href="verifications.php">
                    <i class="fas fa-check-circle me-2"></i>Verifications
                    <?php if (count($pending_verifications) > 0): ?>
                    <span class="badge bg-warning ms-2"><?php echo count($pending_verifications); ?></span>
                    <?php endif; ?>
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
                <h2 class="mb-0">Admin Dashboard</h2>
            </div>
            <div>
                <button class="btn btn-outline-secondary" onclick="toggleTheme()">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card-admin" style="border-left-color: #28a745;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-success mb-1"><?php echo number_format($stats['total_students']); ?></h3>
                            <p class="text-muted mb-0">Total Students</p>
                        </div>
                        <i class="fas fa-user-graduate fa-2x text-success"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card-admin" style="border-left-color: #17a2b8;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-info mb-1"><?php echo number_format($stats['total_teachers']); ?></h3>
                            <p class="text-muted mb-0">Total Teachers</p>
                        </div>
                        <i class="fas fa-chalkboard-teacher fa-2x text-info"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card-admin" style="border-left-color: #ffc107;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-warning mb-1"><?php echo number_format($stats['total_quizzes']); ?></h3>
                            <p class="text-muted mb-0">Active Quizzes</p>
                        </div>
                        <i class="fas fa-question-circle fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card-admin" style="border-left-color: #dc3545;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-danger mb-1"><?php echo number_format($stats['total_challenges']); ?></h3>
                            <p class="text-muted mb-0">Active Challenges</p>
                        </div>
                        <i class="fas fa-trophy fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Activity Stats -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stat-card-admin" style="border-left-color: #6f42c1;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="text-primary mb-1"><?php echo number_format($stats['weekly_new_users']); ?></h4>
                            <p class="text-muted mb-0">New Users (7 days)</p>
                        </div>
                        <i class="fas fa-user-plus fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stat-card-admin" style="border-left-color: #20c997;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="text-success mb-1"><?php echo number_format($stats['weekly_quiz_attempts']); ?></h4>
                            <p class="text-muted mb-0">Quiz Attempts (7 days)</p>
                        </div>
                        <i class="fas fa-brain fa-2x text-success"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stat-card-admin" style="border-left-color: #fd7e14;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="text-warning mb-1"><?php echo number_format($stats['weekly_challenge_joins']); ?></h4>
                            <p class="text-muted mb-0">Challenge Joins (7 days)</p>
                        </div>
                        <i class="fas fa-flag fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Activities -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activities)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No recent activities</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <tbody>
                                    <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <i class="fas <?php 
                                                echo $activity['type'] === 'user_registered' ? 'fa-user-plus text-success' : 
                                                    ($activity['type'] === 'quiz_completed' ? 'fa-brain text-primary' : 'fa-trophy text-warning'); 
                                            ?> me-2"></i>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                            <?php if ($activity['details']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($activity['details']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <small class="text-muted"><?php echo formatTimeAgo($activity['date']); ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pending Verifications -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Pending Verifications</h5>
                        <?php if (count($pending_verifications) > 0): ?>
                        <span class="badge bg-warning"><?php echo count($pending_verifications); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_verifications)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="text-muted">All caught up!</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($pending_verifications as $verification): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <h6 class="mb-1"><?php echo htmlspecialchars($verification['challenge_title']); ?></h6>
                            <p class="mb-1">
                                <strong><?php echo htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']); ?></strong>
                            </p>
                            <small class="text-muted">Submitted <?php echo formatTimeAgo($verification['submitted_at']); ?></small>
                            <div class="mt-2">
                                <a href="verifications.php?id=<?php echo $verification['id']; ?>" class="btn btn-sm btn-primary">
                                    Review
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="text-center">
                            <a href="verifications.php" class="btn btn-outline-primary btn-sm">View All</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="quizzes.php?action=create" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Create Quiz
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="challenges.php?action=create" class="btn btn-outline-success w-100">
                                    <i class="fas fa-plus me-2"></i>Create Challenge
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="content.php?action=create" class="btn btn-outline-info w-100">
                                    <i class="fas fa-plus me-2"></i>Add Content
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="users.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-users me-2"></i>Manage Users
                                </a>
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
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            sidebar.classList.toggle('show');
        }
        
        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            // You can implement AJAX refresh here
        }, 30000);
    </script>
</body>
</html>
