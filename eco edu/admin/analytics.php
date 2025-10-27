<?php
require_once 'admin_header.php';
// $user is already available from admin_header.php

// Get analytics data with error handling
$analytics = [];

// User statistics
try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN role = 'student' THEN 1 END) as students,
        COUNT(CASE WHEN role = 'teacher' THEN 1 END) as teachers,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_7d
        FROM users");
    $stmt->execute();
    $analytics['users'] = $stmt->fetch();
} catch (Exception $e) {
    $analytics['users'] = ['total_users' => 0, 'students' => 0, 'teachers' => 0, 'new_users_30d' => 0, 'new_users_7d' => 0];
}

// Content statistics
try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_content,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_content,
        AVG(points_reward) as avg_points,
        AVG(estimated_time) as avg_time
        FROM learning_content");
    $stmt->execute();
    $analytics['content'] = $stmt->fetch();
} catch (Exception $e) {
    $analytics['content'] = ['total_content' => 0, 'active_content' => 0, 'avg_points' => 0, 'avg_time' => 0];
}

// Quiz statistics
try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(DISTINCT q.id) as total_quizzes,
        COUNT(DISTINCT qa.id) as total_attempts,
        AVG(qa.score) as avg_score,
        COUNT(CASE WHEN qa.is_passed = 1 THEN 1 END) as passed_attempts
        FROM quizzes q
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id");
    $stmt->execute();
    $analytics['quizzes'] = $stmt->fetch();
} catch (Exception $e) {
    $analytics['quizzes'] = ['total_quizzes' => 0, 'total_attempts' => 0, 'avg_score' => 0, 'passed_attempts' => 0];
}

// Challenge statistics
try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(DISTINCT c.id) as total_challenges,
        COUNT(DISTINCT cp.id) as total_participations,
        COUNT(CASE WHEN cp.status = 'verified' THEN 1 END) as completed_challenges,
        SUM(cp.points_earned) as total_points_awarded
        FROM challenges c
        LEFT JOIN challenge_participation cp ON c.id = cp.challenge_id");
    $stmt->execute();
    $analytics['challenges'] = $stmt->fetch();
} catch (Exception $e) {
    $analytics['challenges'] = ['total_challenges' => 0, 'total_participations' => 0, 'completed_challenges' => 0, 'total_points_awarded' => 0];
}

// Top performing schools
try {
    $stmt = $pdo->prepare("SELECT 
        school_name,
        COUNT(*) as student_count,
        AVG(eco_points) as avg_points,
        SUM(eco_points) as total_points
        FROM users 
        WHERE role = 'student' AND school_name IS NOT NULL AND school_name != ''
        GROUP BY school_name
        ORDER BY avg_points DESC
        LIMIT 10");
    $stmt->execute();
    $analytics['top_schools'] = $stmt->fetchAll();
} catch (Exception $e) {
    $analytics['top_schools'] = [];
}

// Top students
try {
    $stmt = $pdo->prepare("SELECT 
        first_name, last_name, school_name, eco_points, total_badges
        FROM users 
        WHERE role = 'student'
        ORDER BY eco_points DESC
        LIMIT 10");
    $stmt->execute();
    $analytics['top_students'] = $stmt->fetchAll();
} catch (Exception $e) {
    $analytics['top_students'] = [];
}

// Monthly user registrations (last 12 months)
try {
    $stmt = $pdo->prepare("SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as registrations
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month");
    $stmt->execute();
    $analytics['monthly_registrations'] = $stmt->fetchAll();
} catch (Exception $e) {
    $analytics['monthly_registrations'] = [];
}

// Activity by day of week
try {
    $stmt = $pdo->prepare("SELECT 
        DAYNAME(created_at) as day_name,
        COUNT(*) as activity_count
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
        ORDER BY DAYOFWEEK(created_at)");
    $stmt->execute();
    $analytics['daily_activity'] = $stmt->fetchAll();
} catch (Exception $e) {
    $analytics['daily_activity'] = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - EcoEdu Admin</title>
    
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
        .analytics-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .chart-container {
            position: relative;
            height: 300px;
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
                <a class="nav-link text-white" href="quizzes.php">
                    <i class="fas fa-question-circle me-2"></i>Quizzes
                </a>
                <a class="nav-link text-white" href="challenges.php">
                    <i class="fas fa-trophy me-2"></i>Challenges
                </a>
                <a class="nav-link text-white" href="verifications.php">
                    <i class="fas fa-check-circle me-2"></i>Verifications
                </a>
                <a class="nav-link text-white active" href="analytics.php">
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
                <h2 class="mb-0">Analytics Dashboard</h2>
            </div>
            <div>
                <button class="btn btn-outline-secondary" onclick="toggleTheme()">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="analytics-card text-center">
                    <div class="metric-value text-primary"><?php echo number_format($analytics['users']['total_users']); ?></div>
                    <div class="metric-label">Total Users</div>
                    <div class="mt-3">
                        <small class="text-success">
                            <i class="fas fa-arrow-up me-1"></i>
                            +<?php echo $analytics['users']['new_users_7d']; ?> this week
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="analytics-card text-center">
                    <div class="metric-value text-success"><?php echo number_format($analytics['content']['active_content']); ?></div>
                    <div class="metric-label">Active Content</div>
                    <div class="mt-3">
                        <small class="text-muted">
                            Avg: <?php echo round($analytics['content']['avg_time']); ?> min read
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="analytics-card text-center">
                    <div class="metric-value text-warning"><?php echo number_format($analytics['quizzes']['total_attempts']); ?></div>
                    <div class="metric-label">Quiz Attempts</div>
                    <div class="mt-3">
                        <small class="text-info">
                            <?php echo $analytics['quizzes']['total_attempts'] > 0 ? round(($analytics['quizzes']['passed_attempts'] / $analytics['quizzes']['total_attempts']) * 100) : 0; ?>% pass rate
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="analytics-card text-center">
                    <div class="metric-value text-info"><?php echo number_format($analytics['challenges']['total_participations']); ?></div>
                    <div class="metric-label">Challenge Joins</div>
                    <div class="mt-3">
                        <small class="text-success">
                            <?php echo number_format($analytics['challenges']['total_points_awarded']); ?> points awarded
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>User Registrations (Last 12 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="registrationsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>User Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="userDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity by Day -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Activity by Day of Week</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="dailyActivityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-school me-2"></i>Top Performing Schools</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($analytics['top_schools'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-school fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No school data available</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>School</th>
                                        <th>Students</th>
                                        <th>Avg Points</th>
                                        <th>Total Points</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analytics['top_schools'] as $index => $school): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-<?php echo $index < 3 ? ($index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'info')) : 'light'; ?> me-2">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                                <?php echo htmlspecialchars($school['school_name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo $school['student_count']; ?></td>
                                        <td><?php echo number_format($school['avg_points']); ?></td>
                                        <td><?php echo number_format($school['total_points']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-medal me-2"></i>Top Students</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($analytics['top_students'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No student data available</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>School</th>
                                        <th>Points</th>
                                        <th>Badges</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analytics['top_students'] as $index => $student): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-<?php echo $index < 3 ? ($index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'info')) : 'light'; ?> me-2">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($student['school_name'] ?: 'Independent'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo number_format($student['eco_points']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?php echo $student['total_badges']; ?></span>
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

        // User Registrations Chart
        const registrationsCtx = document.getElementById('registrationsChart').getContext('2d');
        const registrationsChart = new Chart(registrationsCtx, {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($analytics['monthly_registrations'], 'month')) . "'"; ?>],
                datasets: [{
                    label: 'New Registrations',
                    data: [<?php echo implode(',', array_column($analytics['monthly_registrations'], 'registrations')); ?>],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // User Distribution Chart
        const userDistCtx = document.getElementById('userDistributionChart').getContext('2d');
        const userDistChart = new Chart(userDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Students', 'Teachers'],
                datasets: [{
                    data: [<?php echo $analytics['users']['students']; ?>, <?php echo $analytics['users']['teachers']; ?>],
                    backgroundColor: ['#007bff', '#28a745'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Daily Activity Chart
        const dailyActivityCtx = document.getElementById('dailyActivityChart').getContext('2d');
        const dailyActivityChart = new Chart(dailyActivityCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($analytics['daily_activity'], 'day_name')) . "'"; ?>],
                datasets: [{
                    label: 'Activity Count',
                    data: [<?php echo implode(',', array_column($analytics['daily_activity'], 'activity_count')); ?>],
                    backgroundColor: '#17a2b8',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
