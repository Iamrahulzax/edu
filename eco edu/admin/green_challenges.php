<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'admin_header.php';

// Handle challenge submission actions
$message = '';
$message_type = '';

if ($_POST) {
    switch ($_POST['action']) {
        case 'approve_submission':
            $submission_id = (int)$_POST['submission_id'];
            $ecopoints = (int)$_POST['ecopoints'];
            $feedback = sanitizeInput($_POST['feedback']);
            
            try {
                $pdo->beginTransaction();
                
                // Get submission details
                $stmt = $pdo->prepare("SELECT cs.*, ec.ecopoints_reward, u.first_name, u.last_name 
                                     FROM challenge_submissions cs 
                                     JOIN eco_challenges ec ON cs.challenge_id = ec.id 
                                     JOIN users u ON cs.user_id = u.id 
                                     WHERE cs.id = ?");
                $stmt->execute([$submission_id]);
                $submission = $stmt->fetch();
                
                if ($submission) {
                    // Update submission status
                    $stmt = $pdo->prepare("UPDATE challenge_submissions SET 
                                         status = 'approved', 
                                         admin_feedback = ?, 
                                         approved_by = ?, 
                                         approved_at = NOW(), 
                                         ecopoints_awarded = ? 
                                         WHERE id = ?");
                    $stmt->execute([$feedback, $_SESSION['user_id'], $ecopoints, $submission_id]);
                    
                    // Update user progress
                    $stmt = $pdo->prepare("UPDATE user_challenge_progress SET 
                                         status = 'completed', 
                                         completed_at = NOW() 
                                         WHERE user_id = ? AND challenge_id = ?");
                    $stmt->execute([$submission['user_id'], $submission['challenge_id']]);
                    
                    // Award EcoPoints to user
                    addEcoPoints($submission['user_id'], $ecopoints, "Green Challenge: " . $submission['title']);
                    
                    // Check for rewards
                    checkAndAwardRewards($submission['user_id']);
                    
                    $pdo->commit();
                    $message = "Challenge submission approved successfully! {$submission['first_name']} {$submission['last_name']} earned {$ecopoints} EcoPoints.";
                    $message_type = 'success';
                } else {
                    $pdo->rollBack();
                    $message = 'Submission not found.';
                    $message_type = 'error';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error approving submission: ' . $e->getMessage();
                $message_type = 'error';
            }
            break;
            
        case 'reject_submission':
            $submission_id = (int)$_POST['submission_id'];
            $feedback = sanitizeInput($_POST['feedback']);
            
            try {
                // Update submission status
                $stmt = $pdo->prepare("UPDATE challenge_submissions SET 
                                     status = 'rejected', 
                                     admin_feedback = ?, 
                                     approved_by = ?, 
                                     approved_at = NOW() 
                                     WHERE id = ?");
                $stmt->execute([$feedback, $_SESSION['user_id'], $submission_id]);
                
                $message = 'Challenge submission rejected with feedback.';
                $message_type = 'warning';
            } catch (Exception $e) {
                $message = 'Error rejecting submission: ' . $e->getMessage();
                $message_type = 'error';
            }
            break;
            
        case 'add_challenge':
            $category_id = (int)$_POST['category_id'];
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $instructions = sanitizeInput($_POST['instructions']);
            $ecopoints_reward = (int)$_POST['ecopoints_reward'];
            $difficulty_level = $_POST['difficulty_level'];
            $estimated_time = sanitizeInput($_POST['estimated_time']);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO eco_challenges 
                                     (category_id, title, description, instructions, ecopoints_reward, difficulty_level, estimated_time) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$category_id, $title, $description, $instructions, $ecopoints_reward, $difficulty_level, $estimated_time]);
                
                $message = 'New challenge added successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error adding challenge: ' . $e->getMessage();
                $message_type = 'error';
            }
            break;
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';

// Get pending submissions
try {
    $where_conditions = [];
    $params = [];
    
    if ($status_filter && $status_filter !== 'all') {
        $where_conditions[] = "cs.status = ?";
        $params[] = $status_filter;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "SELECT cs.*, ec.title as challenge_title, ec.ecopoints_reward, 
                     cc.name as category_name, cc.icon as category_icon, cc.color as category_color,
                     u.first_name, u.last_name, u.email,
                     admin.first_name as admin_first_name, admin.last_name as admin_last_name
              FROM challenge_submissions cs
              JOIN eco_challenges ec ON cs.challenge_id = ec.id
              JOIN challenge_categories cc ON ec.category_id = cc.id
              JOIN users u ON cs.user_id = u.id
              LEFT JOIN users admin ON cs.approved_by = admin.id
              $where_clause
              ORDER BY cs.submission_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll();
} catch (Exception $e) {
    $submissions = [];
}

// Get challenge categories
try {
    $stmt = $pdo->query("SELECT * FROM challenge_categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Get statistics
try {
    $stats = [];
    
    // Total submissions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM challenge_submissions");
    $stats['total_submissions'] = $stmt->fetch()['total'];
    
    // Pending submissions
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM challenge_submissions WHERE status = 'pending'");
    $stats['pending_submissions'] = $stmt->fetch()['pending'];
    
    // Approved submissions
    $stmt = $pdo->query("SELECT COUNT(*) as approved FROM challenge_submissions WHERE status = 'approved'");
    $stats['approved_submissions'] = $stmt->fetch()['approved'];
    
    // Total EcoPoints awarded
    $stmt = $pdo->query("SELECT SUM(ecopoints_awarded) as total_points FROM challenge_submissions WHERE status = 'approved'");
    $stats['total_ecopoints'] = $stmt->fetch()['total_points'] ?: 0;
    
} catch (Exception $e) {
    $stats = ['total_submissions' => 0, 'pending_submissions' => 0, 'approved_submissions' => 0, 'total_ecopoints' => 0];
}

// Helper function to check and award rewards
function checkAndAwardRewards($user_id) {
    global $pdo;
    
    try {
        // Get user's total EcoPoints
        $stmt = $pdo->prepare("SELECT eco_points FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_points = $stmt->fetch()['eco_points'];
        
        // Get available rewards that user hasn't earned yet
        $stmt = $pdo->prepare("SELECT er.* FROM eco_rewards er 
                             WHERE er.points_required <= ? 
                             AND er.is_active = 1 
                             AND er.id NOT IN (SELECT reward_id FROM user_rewards WHERE user_id = ?)
                             ORDER BY er.points_required DESC");
        $stmt->execute([$user_points, $user_id]);
        $available_rewards = $stmt->fetchAll();
        
        // Award new rewards
        foreach ($available_rewards as $reward) {
            $stmt = $pdo->prepare("INSERT INTO user_rewards (user_id, reward_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $reward['id']]);
        }
        
    } catch (Exception $e) {
        // Log error but don't stop the process
        error_log("Error checking rewards: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌳 Green Challenges Management - EcoEdu Admin</title>
    
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
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .admin-sidebar.show {
            transform: translateX(0);
        }
        .admin-content {
            margin-left: 0;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        @media (min-width: 768px) {
            .admin-sidebar {
                transform: translateX(0);
            }
            .admin-content {
                margin-left: 250px;
            }
        }
        .submission-card {
            transition: all 0.3s ease;
            border-left: 5px solid #dee2e6;
        }
        .submission-card.pending {
            border-left-color: #ffc107;
        }
        .submission-card.approved {
            border-left-color: #28a745;
        }
        .submission-card.rejected {
            border-left-color: #dc3545;
        }
        .submission-photo {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: none;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .category-badge {
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body data-theme="light">
    <!-- Admin Sidebar -->
    <div class="admin-sidebar" id="adminSidebar">
        <div class="p-4">
            <h4 class="text-white mb-4">
                <i class="fas fa-leaf me-2"></i>EcoEdu Admin
            </h4>
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="users.php">
                        <i class="fas fa-users me-2"></i>Users
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="content.php">
                        <i class="fas fa-book me-2"></i>Content
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white active" href="green_challenges.php">
                        <i class="fas fa-seedling me-2"></i>Green Challenges
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="admin-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <button class="btn btn-outline-secondary d-md-none me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1><i class="fas fa-seedling me-2"></i>🌳 Green Challenges Management</h1>
                <p class="text-muted">Review and approve environmental challenge submissions</p>
            </div>
            <div>
                <button class="btn btn-success" data-mdb-toggle="modal" data-mdb-target="#addChallengeModal">
                    <i class="fas fa-plus me-2"></i>Add Challenge
                </button>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'danger'); ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="text-primary mb-2">
                        <i class="fas fa-inbox fa-2x"></i>
                    </div>
                    <h3 class="text-primary"><?php echo $stats['total_submissions']; ?></h3>
                    <p class="text-muted mb-0">Total Submissions</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="text-warning mb-2">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <h3 class="text-warning"><?php echo $stats['pending_submissions']; ?></h3>
                    <p class="text-muted mb-0">Pending Review</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="text-success mb-2">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <h3 class="text-success"><?php echo $stats['approved_submissions']; ?></h3>
                    <p class="text-muted mb-0">Approved</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="text-info mb-2">
                        <i class="fas fa-coins fa-2x"></i>
                    </div>
                    <h3 class="text-info"><?php echo number_format($stats['total_ecopoints']); ?></h3>
                    <p class="text-muted mb-0">EcoPoints Awarded</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status Filter</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Submissions</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>⏳ Pending Review</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>✅ Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>❌ Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>Apply Filter
                        </button>
                        <a href="green_challenges.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-refresh me-1"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Submissions List -->
        <div class="row">
            <?php if (empty($submissions)): ?>
            <div class="col-12">
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-seedling fa-4x text-muted mb-4"></i>
                        <h3 class="text-muted">No Submissions Found</h3>
                        <p class="text-muted">No challenge submissions match your current filter.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($submissions as $submission): ?>
            <div class="col-lg-6 col-12 mb-4">
                <div class="card submission-card <?php echo $submission['status']; ?> h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0"><?php echo htmlspecialchars($submission['challenge_title']); ?></h6>
                            <small class="text-muted">
                                by <?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?>
                            </small>
                        </div>
                        <div>
                            <span class="category-badge" style="background-color: <?php echo $submission['category_color']; ?>; color: white;">
                                <i class="<?php echo $submission['category_icon']; ?> me-1"></i>
                                <?php echo htmlspecialchars($submission['category_name']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Submission Photo -->
                        <?php if ($submission['photo_path']): ?>
                        <div class="text-center mb-3">
                            <img src="../<?php echo htmlspecialchars($submission['photo_path']); ?>" 
                                 class="submission-photo" 
                                 alt="Challenge submission photo"
                                 onclick="showPhotoModal('<?php echo htmlspecialchars($submission['photo_path']); ?>')">
                        </div>
                        <?php endif; ?>
                        
                        <!-- Submission Details -->
                        <div class="mb-3">
                            <strong>Description:</strong>
                            <p class="text-muted mt-1"><?php echo nl2br(htmlspecialchars($submission['description'])); ?></p>
                        </div>
                        
                        <?php if ($submission['location']): ?>
                        <div class="mb-3">
                            <strong><i class="fas fa-map-marker-alt me-1"></i>Location:</strong>
                            <span class="text-muted"><?php echo htmlspecialchars($submission['location']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    Submitted: <?php echo date('M j, Y g:i A', strtotime($submission['submission_date'])); ?>
                                </small>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted">
                                    <i class="fas fa-coins me-1"></i>
                                    Reward: <?php echo $submission['ecopoints_reward']; ?> EcoPoints
                                </small>
                            </div>
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="mb-3">
                            <?php
                            $status_info = [
                                'pending' => ['color' => 'warning', 'icon' => 'fa-clock', 'text' => 'Pending Review'],
                                'approved' => ['color' => 'success', 'icon' => 'fa-check-circle', 'text' => 'Approved'],
                                'rejected' => ['color' => 'danger', 'icon' => 'fa-times-circle', 'text' => 'Rejected']
                            ];
                            $current_status = $status_info[$submission['status']];
                            ?>
                            <span class="badge bg-<?php echo $current_status['color']; ?> fs-6">
                                <i class="fas <?php echo $current_status['icon']; ?> me-1"></i>
                                <?php echo $current_status['text']; ?>
                            </span>
                            
                            <?php if ($submission['ecopoints_awarded'] > 0): ?>
                            <span class="badge bg-success fs-6 ms-2">
                                <i class="fas fa-coins me-1"></i>
                                +<?php echo $submission['ecopoints_awarded']; ?> EcoPoints Awarded
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Admin Feedback -->
                        <?php if ($submission['admin_feedback']): ?>
                        <div class="alert alert-info">
                            <strong>Admin Feedback:</strong>
                            <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($submission['admin_feedback'])); ?></p>
                            <?php if ($submission['admin_first_name']): ?>
                            <small class="text-muted">
                                - <?php echo htmlspecialchars($submission['admin_first_name'] . ' ' . $submission['admin_last_name']); ?>
                                <?php if ($submission['approved_at']): ?>
                                on <?php echo date('M j, Y g:i A', strtotime($submission['approved_at'])); ?>
                                <?php endif; ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <?php if ($submission['status'] === 'pending'): ?>
                    <div class="card-footer bg-light">
                        <div class="d-flex gap-2">
                            <button class="btn btn-success flex-fill" onclick="showApprovalModal(<?php echo $submission['id']; ?>, '<?php echo htmlspecialchars($submission['challenge_title']); ?>', <?php echo $submission['ecopoints_reward']; ?>)">
                                <i class="fas fa-check me-1"></i>Approve
                            </button>
                            <button class="btn btn-danger flex-fill" onclick="showRejectionModal(<?php echo $submission['id']; ?>, '<?php echo htmlspecialchars($submission['challenge_title']); ?>')">
                                <i class="fas fa-times me-1"></i>Reject
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Approval Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>Approve Challenge Submission
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" id="approvalForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve_submission">
                        <input type="hidden" name="submission_id" id="approvalSubmissionId">
                        
                        <div class="alert alert-success">
                            <i class="fas fa-info-circle me-2"></i>
                            You are about to approve the challenge submission: <strong id="approvalChallengeTitle"></strong>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ecopoints" class="form-label">EcoPoints to Award</label>
                            <input type="number" class="form-control" id="ecopoints" name="ecopoints" min="1" max="500" required>
                            <div class="form-text">Recommended: <span id="recommendedPoints"></span> EcoPoints</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="approvalFeedback" class="form-label">Admin Feedback (Optional)</label>
                            <textarea class="form-control" id="approvalFeedback" name="feedback" rows="3" placeholder="Great work on completing this environmental challenge!"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Approve & Award Points
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>Reject Challenge Submission
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" id="rejectionForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject_submission">
                        <input type="hidden" name="submission_id" id="rejectionSubmissionId">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You are about to reject the challenge submission: <strong id="rejectionChallengeTitle"></strong>
                        </div>
                        
                        <div class="mb-3">
                            <label for="rejectionFeedback" class="form-label">Reason for Rejection *</label>
                            <textarea class="form-control" id="rejectionFeedback" name="feedback" rows="4" placeholder="Please provide clear feedback on why this submission was rejected and how the student can improve..." required></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <strong>Common rejection reasons:</strong><br>
                                • Photo doesn't clearly show the completed action<br>
                                • Description lacks sufficient detail<br>
                                • Activity doesn't match challenge requirements<br>
                                • Submission appears inauthentic
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>Reject Submission
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Challenge Submission Photo</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" class="img-fluid" alt="Challenge submission">
                </div>
            </div>
        </div>
    </div>

    <!-- Add Challenge Modal -->
    <div class="modal fade" id="addChallengeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Green Challenge
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" id="addChallengeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_challenge">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="difficulty_level" class="form-label">Difficulty Level *</label>
                                <select class="form-select" id="difficulty_level" name="difficulty_level" required>
                                    <option value="">Select Difficulty</option>
                                    <option value="easy">🟢 Easy</option>
                                    <option value="medium">🟡 Medium</option>
                                    <option value="hard">🔴 Hard</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Challenge Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required placeholder="e.g., Plant a Tree in Your Community">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Short Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="2" required placeholder="Brief description of what students need to do..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="instructions" class="form-label">Detailed Instructions *</label>
                            <textarea class="form-control" id="instructions" name="instructions" rows="4" required placeholder="Step-by-step instructions for completing this challenge..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ecopoints_reward" class="form-label">EcoPoints Reward *</label>
                                <input type="number" class="form-control" id="ecopoints_reward" name="ecopoints_reward" min="10" max="500" required placeholder="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="estimated_time" class="form-label">Estimated Time *</label>
                                <input type="text" class="form-control" id="estimated_time" name="estimated_time" required placeholder="e.g., 2-3 hours">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Create Challenge
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    
    <script>
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            sidebar.classList.toggle('show');
        }
        
        // Show approval modal
        function showApprovalModal(submissionId, challengeTitle, recommendedPoints) {
            document.getElementById('approvalSubmissionId').value = submissionId;
            document.getElementById('approvalChallengeTitle').textContent = challengeTitle;
            document.getElementById('recommendedPoints').textContent = recommendedPoints;
            document.getElementById('ecopoints').value = recommendedPoints;
            
            const modal = new mdb.Modal(document.getElementById('approvalModal'));
            modal.show();
        }
        
        // Show rejection modal
        function showRejectionModal(submissionId, challengeTitle) {
            document.getElementById('rejectionSubmissionId').value = submissionId;
            document.getElementById('rejectionChallengeTitle').textContent = challengeTitle;
            
            const modal = new mdb.Modal(document.getElementById('rejectionModal'));
            modal.show();
        }
        
        // Show photo modal
        function showPhotoModal(photoPath) {
            document.getElementById('modalPhoto').src = '../' + photoPath;
            const modal = new mdb.Modal(document.getElementById('photoModal'));
            modal.show();
        }
        
        // Form submission loading states
        document.getElementById('approvalForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Approving...';
        });
        
        document.getElementById('rejectionForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Rejecting...';
        });
        
        document.getElementById('addChallengeForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
        });
        
        // Auto-refresh pending submissions every 30 seconds
        <?php if ($status_filter === 'pending'): ?>
        setInterval(function() {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 30000);
        <?php endif; ?>
        
        // Submission card hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const submissionCards = document.querySelectorAll('.submission-card');
            
            submissionCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '';
                });
            });
        });
        
        // Photo click to enlarge
        document.addEventListener('DOMContentLoaded', function() {
            const photos = document.querySelectorAll('.submission-photo');
            photos.forEach(photo => {
                photo.style.cursor = 'pointer';
                photo.title = 'Click to enlarge';
            });
        });
    </script>
</body>
</html>
