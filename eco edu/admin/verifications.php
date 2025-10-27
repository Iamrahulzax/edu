<?php
require_once 'admin_header.php';
// $user is already available from admin_header.php

// Handle verification actions
$message = '';
$message_type = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'verify_challenge':
                $participation_id = (int)$_POST['participation_id'];
                $points_awarded = (int)$_POST['points_awarded'];
                $verification_notes = sanitizeInput($_POST['verification_notes']);
                
                try {
                    $pdo->beginTransaction();
                    
                    // Get participation details
                    $stmt = $pdo->prepare("SELECT cp.*, c.points_reward, c.title FROM challenge_participation cp JOIN challenges c ON cp.challenge_id = c.id WHERE cp.id = ?");
                    $stmt->execute([$participation_id]);
                    $participation = $stmt->fetch();
                    
                    if ($participation) {
                        // Update participation status
                        $stmt = $pdo->prepare("UPDATE challenge_participation SET status = 'verified', points_earned = ?, verification_notes = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
                        $stmt->execute([$points_awarded, $verification_notes, $_SESSION['user_id'], $participation_id]);
                        
                        // Award points to user
                        addEcoPoints($participation['user_id'], $points_awarded, "Challenge: " . $participation['title']);
                        
                        $pdo->commit();
                        $message = 'Challenge verified successfully and points awarded!';
                        $message_type = 'success';
                    } else {
                        throw new Exception("Participation not found");
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = 'Failed to verify challenge: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'reject_challenge':
                $participation_id = (int)$_POST['participation_id'];
                $verification_notes = sanitizeInput($_POST['verification_notes']);
                
                $stmt = $pdo->prepare("UPDATE challenge_participation SET status = 'rejected', verification_notes = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
                if ($stmt->execute([$verification_notes, $_SESSION['user_id'], $participation_id])) {
                    $message = 'Challenge submission rejected';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to reject challenge submission';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'submitted';
$challenge_filter = isset($_GET['challenge']) ? (int)$_GET['challenge'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query for pending verifications
$where_conditions = ["cp.status = ?"];
$params = [$status_filter];

if ($challenge_filter) {
    $where_conditions[] = "cp.challenge_id = ?";
    $params[] = $challenge_filter;
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR c.title LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get challenge submissions
$query = "SELECT cp.*, u.first_name, u.last_name, u.username, u.school_name, 
          c.title as challenge_title, c.points_reward, c.verification_type,
          v.first_name as verified_by_name, v.last_name as verified_by_lastname
          FROM challenge_participation cp
          JOIN users u ON cp.user_id = u.id
          JOIN challenges c ON cp.challenge_id = c.id
          LEFT JOIN users v ON cp.verified_by = v.id
          $where_clause
          ORDER BY cp.submitted_at DESC, cp.joined_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

// Get challenges for filter
$stmt = $pdo->prepare("SELECT id, title FROM challenges WHERE is_active = 1 ORDER BY title");
$stmt->execute();
$challenges = $stmt->fetchAll();

// Get verification statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(CASE WHEN status = 'submitted' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'verified' THEN 1 END) as verified,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
    COUNT(*) as total
    FROM challenge_participation 
    WHERE status IN ('submitted', 'verified', 'rejected')");
$stmt->execute();
$verification_stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Challenge Verifications - EcoEdu Admin</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        .submission-card {
            transition: all 0.3s ease;
            border-left: 5px solid #dee2e6;
        }
        .submission-card.pending {
            border-left-color: #ffc107;
        }
        .submission-card.verified {
            border-left-color: #28a745;
        }
        .submission-card.rejected {
            border-left-color: #dc3545;
        }
        .submission-image {
            max-width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        .verification-actions {
            position: sticky;
            bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
                <a class="nav-link text-white active" href="verifications.php">
                    <i class="fas fa-check-circle me-2"></i>Verifications
                    <?php if ($verification_stats['pending'] > 0): ?>
                    <span class="badge bg-warning ms-2"><?php echo $verification_stats['pending']; ?></span>
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
                <h2 class="mb-0">Challenge Verifications</h2>
            </div>
            <div>
                <button class="btn btn-outline-secondary" onclick="toggleTheme()">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $verification_stats['pending']; ?></h4>
                                <p class="mb-0">Pending Review</p>
                            </div>
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $verification_stats['verified']; ?></h4>
                                <p class="mb-0">Verified</p>
                            </div>
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $verification_stats['rejected']; ?></h4>
                                <p class="mb-0">Rejected</p>
                            </div>
                            <i class="fas fa-times-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $verification_stats['total']; ?></h4>
                                <p class="mb-0">Total Submissions</p>
                            </div>
                            <i class="fas fa-list fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Student name or challenge...">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Pending Review</option>
                            <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="challenge" class="form-label">Challenge</label>
                        <select class="form-select" id="challenge" name="challenge">
                            <option value="">All Challenges</option>
                            <?php foreach ($challenges as $challenge): ?>
                            <option value="<?php echo $challenge['id']; ?>" <?php echo $challenge_filter == $challenge['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($challenge['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <a href="verifications.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Submissions -->
        <div class="row">
            <?php if (empty($submissions)): ?>
            <div class="col-12">
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-4x text-muted mb-4"></i>
                        <h5 class="text-muted">No submissions found</h5>
                        <p class="text-muted">
                            <?php if ($status_filter === 'submitted'): ?>
                            All caught up! No pending verifications.
                            <?php else: ?>
                            No submissions match your current filters.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($submissions as $submission): ?>
            <div class="col-lg-6 mb-4">
                <div class="card submission-card <?php echo $submission['status']; ?> h-100">
                    <div class="card-body">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title"><?php echo htmlspecialchars($submission['challenge_title']); ?></h5>
                                <h6 class="text-muted">
                                    <?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?>
                                    <small class="text-muted">(@<?php echo htmlspecialchars($submission['username']); ?>)</small>
                                </h6>
                                <?php if ($submission['school_name']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($submission['school_name']); ?></small>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-<?php 
                                echo $submission['status'] === 'submitted' ? 'warning' : 
                                    ($submission['status'] === 'verified' ? 'success' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($submission['status']); ?>
                            </span>
                        </div>

                        <!-- Submission Content -->
                        <?php if ($submission['submission_text']): ?>
                        <div class="mb-3">
                            <h6>Submission Details:</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- File Submission -->
                        <?php if ($submission['submission_file']): ?>
                        <div class="mb-3">
                            <h6>Submitted File:</h6>
                            <?php 
                            $file_extension = strtolower(pathinfo($submission['submission_file'], PATHINFO_EXTENSION));
                            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): 
                            ?>
                            <img src="../uploads/challenges/<?php echo htmlspecialchars($submission['submission_file']); ?>" 
                                 class="submission-image" alt="Submission">
                            <?php else: ?>
                            <a href="../uploads/challenges/<?php echo htmlspecialchars($submission['submission_file']); ?>" 
                               target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download me-1"></i>Download File
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Challenge Info -->
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <i class="fas fa-coins text-warning"></i>
                                <small class="d-block text-muted"><?php echo $submission['points_reward']; ?> points</small>
                            </div>
                            <div class="col-6">
                                <i class="fas fa-<?php echo $submission['verification_type'] === 'photo' ? 'camera' : ($submission['verification_type'] === 'video' ? 'video' : 'file-text'); ?> text-info"></i>
                                <small class="d-block text-muted"><?php echo ucfirst($submission['verification_type']); ?></small>
                            </div>
                        </div>

                        <!-- Timestamps -->
                        <div class="border-top pt-3">
                            <small class="text-muted">
                                Submitted: <?php echo formatTimeAgo($submission['submitted_at']); ?>
                                <?php if ($submission['verified_at']): ?>
                                <br>Verified: <?php echo formatTimeAgo($submission['verified_at']); ?>
                                by <?php echo htmlspecialchars($submission['verified_by_name'] . ' ' . $submission['verified_by_lastname']); ?>
                                <?php endif; ?>
                            </small>
                        </div>

                        <!-- Verification Notes -->
                        <?php if ($submission['verification_notes']): ?>
                        <div class="mt-3">
                            <h6>Verification Notes:</h6>
                            <p class="text-muted small"><?php echo nl2br(htmlspecialchars($submission['verification_notes'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Points Earned -->
                        <?php if ($submission['points_earned'] > 0): ?>
                        <div class="mt-3">
                            <span class="badge bg-success">
                                <i class="fas fa-coins me-1"></i>
                                +<?php echo $submission['points_earned']; ?> points awarded
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions for Pending Submissions -->
                    <?php if ($submission['status'] === 'submitted'): ?>
                    <div class="card-footer bg-transparent">
                        <div class="verification-actions">
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="participation_id" value="<?php echo $submission['id']; ?>">
                                <div class="form-outline mb-3">
                                    <input type="number" class="form-control" name="points_awarded" 
                                           value="<?php echo $submission['points_reward']; ?>" 
                                           min="0" max="<?php echo $submission['points_reward']; ?>">
                                    <label class="form-label">Points to Award</label>
                                </div>
                                <div class="form-outline mb-3">
                                    <textarea class="form-control" name="verification_notes" rows="2" 
                                              placeholder="Add verification notes (optional)"></textarea>
                                    <label class="form-label">Verification Notes</label>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="action" value="verify_challenge" class="btn btn-success flex-fill">
                                        <i class="fas fa-check me-1"></i>Verify & Award Points
                                    </button>
                                    <button type="submit" name="action" value="reject_challenge" class="btn btn-danger">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
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
        
        // Auto-refresh pending verifications every 30 seconds
        if (window.location.search.includes('status=submitted')) {
            setInterval(() => {
                // Only refresh if no forms are being filled
                const activeElement = document.activeElement;
                if (!activeElement || (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA')) {
                    window.location.reload();
                }
            }, 30000);
        }
    </script>
</body>
</html>
