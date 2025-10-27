<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?message=' . urlencode('Please login to access admin panel') . '&type=error');
    exit;
}

// Get user details and check admin role
$user = getUserById($_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: ../login.php?message=' . urlencode('User not found') . '&type=error');
    exit;
}

if ($user['role'] !== 'admin') {
    header('Location: ../login.php?message=' . urlencode('Admin access required') . '&type=error');
    exit;
}

// Handle challenge actions
$message = '';
$message_type = '';

if ($_POST) {
    switch ($_POST['action']) {
        case 'add_challenge':
            $category_id = (int)$_POST['category_id'];
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $instructions = sanitizeInput($_POST['instructions']);
            $ecopoints_reward = (int)$_POST['ecopoints_reward'];
            $difficulty_level = $_POST['difficulty_level'];
            $estimated_time = sanitizeInput($_POST['estimated_time']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("INSERT INTO eco_challenges 
                                     (category_id, title, description, instructions, ecopoints_reward, difficulty_level, estimated_time, is_active) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$category_id, $title, $description, $instructions, $ecopoints_reward, $difficulty_level, $estimated_time, $is_active]);
                
                $message = 'New challenge "' . $title . '" added successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error adding challenge: ' . $e->getMessage();
                $message_type = 'error';
            }
            break;
            
        case 'edit_challenge':
            $challenge_id = (int)$_POST['challenge_id'];
            $category_id = (int)$_POST['category_id'];
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $instructions = sanitizeInput($_POST['instructions']);
            $ecopoints_reward = (int)$_POST['ecopoints_reward'];
            $difficulty_level = $_POST['difficulty_level'];
            $estimated_time = sanitizeInput($_POST['estimated_time']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("UPDATE eco_challenges SET 
                                     category_id = ?, title = ?, description = ?, instructions = ?, 
                                     ecopoints_reward = ?, difficulty_level = ?, estimated_time = ?, is_active = ?, 
                                     updated_at = NOW() 
                                     WHERE id = ?");
                $stmt->execute([$category_id, $title, $description, $instructions, $ecopoints_reward, $difficulty_level, $estimated_time, $is_active, $challenge_id]);
                
                $message = 'Challenge "' . $title . '" updated successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error updating challenge: ' . $e->getMessage();
                $message_type = 'error';
            }
            break;
            
        case 'delete_challenge':
            $challenge_id = (int)$_POST['challenge_id'];
            
            try {
                // Check if challenge has submissions
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM challenge_submissions WHERE challenge_id = ?");
                $stmt->execute([$challenge_id]);
                $submission_count = $stmt->fetch()['count'];
                
                if ($submission_count > 0) {
                    // Don't delete, just deactivate
                    $stmt = $pdo->prepare("UPDATE eco_challenges SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$challenge_id]);
                    $message = 'Challenge deactivated (has existing submissions)';
                    $message_type = 'warning';
                } else {
                    // Safe to delete
                    $stmt = $pdo->prepare("DELETE FROM eco_challenges WHERE id = ?");
                    $stmt->execute([$challenge_id]);
                    $message = 'Challenge deleted successfully!';
                    $message_type = 'success';
                }
            } catch (Exception $e) {
                $message = 'Error deleting challenge: ' . $e->getMessage();
                $message_type = 'error';
            }
            break;
            
        case 'toggle_status':
            $challenge_id = (int)$_POST['challenge_id'];
            $new_status = (int)$_POST['new_status'];
            
            try {
                $stmt = $pdo->prepare("UPDATE eco_challenges SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $challenge_id]);
                
                $status_text = $new_status ? 'activated' : 'deactivated';
                $message = 'Challenge ' . $status_text . ' successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error updating challenge status: ' . $e->getMessage();
                $message_type = 'error';
            }
            break;
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get challenge categories
try {
    $stmt = $pdo->query("SELECT * FROM challenge_categories ORDER BY name");
    $categories = $stmt->fetchAll();
    
    // If no categories found, create default ones
    if (empty($categories)) {
        $default_categories = [
            ['Tree Plantation', 'Plant trees and contribute to reforestation', 'fas fa-tree', '#28a745'],
            ['Plastic Reduction', 'Reduce plastic usage and promote alternatives', 'fas fa-recycle', '#17a2b8'],
            ['Clean-Up Drive', 'Participate in environmental cleanup activities', 'fas fa-broom', '#ffc107'],
            ['Recycling Action', 'Collect and recycle waste materials', 'fas fa-sync-alt', '#6f42c1'],
            ['Water Conservation', 'Promote water saving and awareness', 'fas fa-tint', '#007bff'],
            ['Energy Saving', 'Implement energy conservation practices', 'fas fa-bolt', '#fd7e14'],
            ['Wildlife Protection', 'Support local wildlife and biodiversity', 'fas fa-paw', '#20c997'],
            ['Sustainable Transport', 'Use eco-friendly transportation methods', 'fas fa-bicycle', '#6c757d']
        ];
        
        $insert_stmt = $pdo->prepare("INSERT INTO challenge_categories (name, description, icon, color) VALUES (?, ?, ?, ?)");
        foreach ($default_categories as $cat) {
            $insert_stmt->execute($cat);
        }
        
        // Fetch categories again
        $stmt = $pdo->query("SELECT * FROM challenge_categories ORDER BY name");
        $categories = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // Create fallback categories array for display
    $categories = [
        ['id' => 1, 'name' => 'Tree Plantation', 'description' => 'Plant trees and contribute to reforestation', 'icon' => 'fas fa-tree', 'color' => '#28a745'],
        ['id' => 2, 'name' => 'Plastic Reduction', 'description' => 'Reduce plastic usage and promote alternatives', 'icon' => 'fas fa-recycle', 'color' => '#17a2b8'],
        ['id' => 3, 'name' => 'Clean-Up Drive', 'description' => 'Participate in environmental cleanup activities', 'icon' => 'fas fa-broom', 'color' => '#ffc107'],
        ['id' => 4, 'name' => 'Recycling Action', 'description' => 'Collect and recycle waste materials', 'icon' => 'fas fa-sync-alt', 'color' => '#6f42c1'],
        ['id' => 5, 'name' => 'Water Conservation', 'description' => 'Promote water saving and awareness', 'icon' => 'fas fa-tint', 'color' => '#007bff'],
        ['id' => 6, 'name' => 'Energy Saving', 'description' => 'Implement energy conservation practices', 'icon' => 'fas fa-bolt', 'color' => '#fd7e14'],
        ['id' => 7, 'name' => 'Wildlife Protection', 'description' => 'Support local wildlife and biodiversity', 'icon' => 'fas fa-paw', 'color' => '#20c997'],
        ['id' => 8, 'name' => 'Sustainable Transport', 'description' => 'Use eco-friendly transportation methods', 'icon' => 'fas fa-bicycle', 'color' => '#6c757d']
    ];
    
    $message = 'Database connection issue. Using default categories. Error: ' . $e->getMessage();
    $message_type = 'warning';
}

// Get challenges with filters
try {
    $where_conditions = [];
    $params = [];
    
    if ($category_filter) {
        $where_conditions[] = "ec.category_id = ?";
        $params[] = $category_filter;
    }
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "ec.is_active = ?";
        $params[] = ($status_filter === 'active') ? 1 : 0;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "SELECT ec.*, cc.name as category_name, cc.icon as category_icon, cc.color as category_color,
                     COUNT(cs.id) as submission_count,
                     COUNT(CASE WHEN cs.status = 'approved' THEN 1 END) as approved_count
              FROM eco_challenges ec 
              LEFT JOIN challenge_categories cc ON ec.category_id = cc.id 
              LEFT JOIN challenge_submissions cs ON ec.id = cs.challenge_id
              $where_clause 
              GROUP BY ec.id
              ORDER BY ec.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $challenges = $stmt->fetchAll();
} catch (Exception $e) {
    $challenges = [];
}

// Get statistics
try {
    $stats = [];
    
    // Total challenges
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM eco_challenges");
    $stats['total_challenges'] = $stmt->fetch()['total'];
    
    // Active challenges
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM eco_challenges WHERE is_active = 1");
    $stats['active_challenges'] = $stmt->fetch()['active'];
    
    // Total submissions
    $stmt = $pdo->query("SELECT COUNT(*) as submissions FROM challenge_submissions");
    $stats['total_submissions'] = $stmt->fetch()['submissions'];
    
    // Total EcoPoints available
    $stmt = $pdo->query("SELECT SUM(ecopoints_reward) as total_points FROM eco_challenges WHERE is_active = 1");
    $stats['total_ecopoints'] = $stmt->fetch()['total_points'] ?: 0;
    
} catch (Exception $e) {
    $stats = ['total_challenges' => 0, 'active_challenges' => 0, 'total_submissions' => 0, 'total_ecopoints' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌱 Challenge Management - EcoEdu Admin</title>
    
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
        .challenge-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #dee2e6;
        }
        .challenge-card.active {
            border-left-color: #28a745;
        }
        .challenge-card.inactive {
            border-left-color: #dc3545;
            opacity: 0.7;
        }
        .challenge-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .difficulty-badge {
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .difficulty-easy { background: #d4edda; color: #155724; }
        .difficulty-medium { background: #fff3cd; color: #856404; }
        .difficulty-hard { background: #f8d7da; color: #721c24; }
        .category-badge {
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 600;
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
        .ecopoints-display {
            background: linear-gradient(135deg, #ffc107, #ff8f00);
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            font-weight: bold;
            display: inline-block;
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
                    <a class="nav-link text-white active" href="challenges.php">
                        <i class="fas fa-trophy me-2"></i>Challenges
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="green_challenges.php">
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
                <h1><i class="fas fa-trophy me-2"></i>🌱 Challenge Management</h1>
                <p class="text-muted">Create and manage environmental challenges for students</p>
            </div>
            <div>
                <button class="btn btn-success" data-mdb-toggle="modal" data-mdb-target="#addChallengeModal">
                    <i class="fas fa-plus me-2"></i>Create New Challenge
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

        <!-- Debug Info (remove in production) -->
        <?php if (empty($categories)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>No categories found!</strong> Please run the setup script first: 
            <a href="../setup_challenges.php" class="btn btn-sm btn-primary ms-2">Run Setup</a>
        </div>
        <?php else: ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Debug:</strong> Found <?php echo count($categories); ?> categories loaded successfully.
            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="text-primary mb-2">
                        <i class="fas fa-trophy fa-2x"></i>
                    </div>
                    <h3 class="text-primary"><?php echo $stats['total_challenges']; ?></h3>
                    <p class="text-muted mb-0">Total Challenges</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="text-success mb-2">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <h3 class="text-success"><?php echo $stats['active_challenges']; ?></h3>
                    <p class="text-muted mb-0">Active Challenges</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="text-info mb-2">
                        <i class="fas fa-paper-plane fa-2x"></i>
                    </div>
                    <h3 class="text-info"><?php echo $stats['total_submissions']; ?></h3>
                    <p class="text-muted mb-0">Total Submissions</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="text-warning mb-2">
                        <i class="fas fa-coins fa-2x"></i>
                    </div>
                    <h3 class="text-warning"><?php echo number_format($stats['total_ecopoints']); ?></h3>
                    <p class="text-muted mb-0">Available EcoPoints</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
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
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>✅ Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>❌ Inactive</option>
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

        <!-- Challenges List -->
        <div class="row">
            <?php if (empty($challenges)): ?>
            <div class="col-12">
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-trophy fa-4x text-muted mb-4"></i>
                        <h3 class="text-muted">No Challenges Found</h3>
                        <p class="text-muted">Create your first environmental challenge to get started!</p>
                        <button class="btn btn-success" data-mdb-toggle="modal" data-mdb-target="#addChallengeModal">
                            <i class="fas fa-plus me-2"></i>Create Challenge
                        </button>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($challenges as $challenge): ?>
            <div class="col-lg-6 col-12 mb-4">
                <div class="card challenge-card <?php echo $challenge['is_active'] ? 'active' : 'inactive'; ?> h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($challenge['title']); ?></h6>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="difficulty-badge difficulty-<?php echo $challenge['difficulty_level']; ?>">
                                    <?php echo ucfirst($challenge['difficulty_level']); ?>
                                </span>
                                <?php if ($challenge['category_name']): ?>
                                <span class="category-badge" style="background-color: <?php echo $challenge['category_color']; ?>; color: white;">
                                    <i class="<?php echo $challenge['category_icon']; ?> me-1"></i>
                                    <?php echo htmlspecialchars($challenge['category_name']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="ecopoints-display">
                                <i class="fas fa-coins me-1"></i>
                                <?php echo $challenge['ecopoints_reward']; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Challenge Description -->
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($challenge['description']); ?></p>
                        
                        <!-- Instructions Preview -->
                        <?php if ($challenge['instructions']): ?>
                        <div class="mb-3">
                            <strong>Instructions:</strong>
                            <p class="text-muted mt-1" style="font-size: 0.9rem;">
                                <?php echo substr(htmlspecialchars($challenge['instructions']), 0, 150) . '...'; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Challenge Details -->
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Time: <?php echo htmlspecialchars($challenge['estimated_time']); ?>
                                </small>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    Created: <?php echo date('M j, Y', strtotime($challenge['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Submission Stats -->
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-info">
                                    <i class="fas fa-paper-plane me-1"></i>
                                    <?php echo $challenge['submission_count']; ?> Submissions
                                </small>
                            </div>
                            <div class="col-6">
                                <small class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <?php echo $challenge['approved_count']; ?> Approved
                                </small>
                            </div>
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="mb-3">
                            <?php if ($challenge['is_active']): ?>
                            <span class="badge bg-success fs-6">
                                <i class="fas fa-check-circle me-1"></i>Active
                            </span>
                            <?php else: ?>
                            <span class="badge bg-danger fs-6">
                                <i class="fas fa-times-circle me-1"></i>Inactive
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="card-footer bg-light">
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary btn-sm" onclick="editChallenge(<?php echo htmlspecialchars(json_encode($challenge)); ?>)">
                                <i class="fas fa-edit me-1"></i>Edit
                            </button>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $challenge['is_active'] ? 0 : 1; ?>">
                                <button type="submit" class="btn btn-<?php echo $challenge['is_active'] ? 'warning' : 'success'; ?> btn-sm">
                                    <i class="fas fa-<?php echo $challenge['is_active'] ? 'pause' : 'play'; ?> me-1"></i>
                                    <?php echo $challenge['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                            <button class="btn btn-danger btn-sm" onclick="deleteChallenge(<?php echo $challenge['id']; ?>, '<?php echo htmlspecialchars($challenge['title']); ?>')">
                                <i class="fas fa-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Challenge Modal -->
    <div class="modal fade" id="addChallengeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Create New Challenge
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" id="addChallengeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_challenge">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Track Your Plant Challenge Example:</strong> Create a challenge where students plant a seed, track its growth with weekly photos, and document the journey over 30 days.
                        </div>
                        
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
                                    <option value="easy">🟢 Easy (1-2 hours)</option>
                                    <option value="medium">🟡 Medium (Half day)</option>
                                    <option value="hard">🔴 Hard (Multiple days)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Challenge Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   placeholder="e.g., Track Your Plant Growth Journey">
                            <div class="form-text">Create an engaging title that describes the environmental action</div>
                            <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="fillPlantExample()">
                                <i class="fas fa-seedling me-1"></i>Use "Track Your Plant" Example
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Short Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="2" required 
                                      placeholder="Plant a seed and document its growth journey with weekly photos over 30 days..."></textarea>
                            <div class="form-text">Brief description that appears on the challenge card</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="instructions" class="form-label">Detailed Instructions *</label>
                            <textarea class="form-control" id="instructions" name="instructions" rows="5" required 
                                      placeholder="1. Choose a plant seed (bean, sunflower, or herb)&#10;2. Plant it in a small pot with good soil&#10;3. Place in a sunny location&#10;4. Water regularly and take a photo every week&#10;5. Document growth changes and measurements&#10;6. Submit final photo showing plant growth after 30 days"></textarea>
                            <div class="form-text">Step-by-step instructions for completing the challenge</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ecopoints_reward" class="form-label">EcoPoints Reward *</label>
                                <input type="number" class="form-control" id="ecopoints_reward" name="ecopoints_reward" 
                                       min="10" max="500" required placeholder="150" value="150">
                                <div class="form-text">Points awarded upon completion (10-500)</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="estimated_time" class="form-label">Estimated Time *</label>
                                <input type="text" class="form-control" id="estimated_time" name="estimated_time" required 
                                       placeholder="30 days (5 min daily)">
                                <div class="form-text">How long the challenge takes to complete</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">
                                    <strong>Active Challenge</strong> - Students can immediately start this challenge
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-success">
                            <i class="fas fa-camera me-2"></i>
                            <strong>Photo Requirements:</strong> All challenges require photo proof for verification. Students will upload images showing their completed environmental action.
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

    <!-- Edit Challenge Modal -->
    <div class="modal fade" id="editChallengeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Challenge
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" id="editChallengeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_challenge">
                        <input type="hidden" name="challenge_id" id="edit_challenge_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_category_id" class="form-label">Category *</label>
                                <select class="form-select" id="edit_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_difficulty_level" class="form-label">Difficulty Level *</label>
                                <select class="form-select" id="edit_difficulty_level" name="difficulty_level" required>
                                    <option value="">Select Difficulty</option>
                                    <option value="easy">🟢 Easy</option>
                                    <option value="medium">🟡 Medium</option>
                                    <option value="hard">🔴 Hard</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Challenge Title *</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Short Description *</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="2" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_instructions" class="form-label">Detailed Instructions *</label>
                            <textarea class="form-control" id="edit_instructions" name="instructions" rows="5" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_ecopoints_reward" class="form-label">EcoPoints Reward *</label>
                                <input type="number" class="form-control" id="edit_ecopoints_reward" name="ecopoints_reward" 
                                       min="10" max="500" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_estimated_time" class="form-label">Estimated Time *</label>
                                <input type="text" class="form-control" id="edit_estimated_time" name="estimated_time" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    <strong>Active Challenge</strong>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Challenge
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteChallengeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-trash me-2"></i>Delete Challenge
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteChallengeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_challenge">
                        <input type="hidden" name="challenge_id" id="delete_challenge_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Are you sure you want to delete the challenge: <strong id="delete_challenge_title"></strong>?
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <strong>Note:</strong> If this challenge has existing submissions, it will be deactivated instead of deleted to preserve data integrity.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Challenge
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
        
        // Fill Track Your Plant example
        function fillPlantExample() {
            document.getElementById('title').value = 'Track Your Plant Growth Journey';
            document.getElementById('description').value = 'Plant a seed and document its growth journey with weekly photos over 30 days to learn about plant life cycles and environmental care.';
            document.getElementById('instructions').value = '1. Choose a plant seed (bean, sunflower, or herb)\n2. Plant it in a small pot with good soil\n3. Place in a sunny location near a window\n4. Water regularly (check soil moisture daily)\n5. Take a photo every week showing growth progress\n6. Measure and record plant height weekly\n7. Document any changes in leaves, stems, or flowers\n8. Submit final photo collage showing 30-day growth journey\n9. Include a short description of what you learned about plant care';
            document.getElementById('ecopoints_reward').value = 150;
            document.getElementById('estimated_time').value = '30 days (5 minutes daily care)';
            document.getElementById('category_id').value = '1'; // Tree Plantation category
            document.getElementById('difficulty_level').value = 'medium';
        }
        
        // Edit challenge function
        function editChallenge(challenge) {
            document.getElementById('edit_challenge_id').value = challenge.id;
            document.getElementById('edit_category_id').value = challenge.category_id;
            document.getElementById('edit_difficulty_level').value = challenge.difficulty_level;
            document.getElementById('edit_title').value = challenge.title;
            document.getElementById('edit_description').value = challenge.description;
            document.getElementById('edit_instructions').value = challenge.instructions;
            document.getElementById('edit_ecopoints_reward').value = challenge.ecopoints_reward;
            document.getElementById('edit_estimated_time').value = challenge.estimated_time;
            document.getElementById('edit_is_active').checked = challenge.is_active == 1;
            
            const modal = new mdb.Modal(document.getElementById('editChallengeModal'));
            modal.show();
        }
        
        // Delete challenge function
        function deleteChallenge(challengeId, challengeTitle) {
            document.getElementById('delete_challenge_id').value = challengeId;
            document.getElementById('delete_challenge_title').textContent = challengeTitle;
            
            const modal = new mdb.Modal(document.getElementById('deleteChallengeModal'));
            modal.show();
        }
        
        // Form submission loading states
        document.getElementById('addChallengeForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
        });
        
        document.getElementById('editChallengeForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
        });
        
        document.getElementById('deleteChallengeForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
        });
        
        // Challenge card hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const challengeCards = document.querySelectorAll('.challenge-card');
            
            challengeCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('inactive')) {
                        this.style.transform = 'translateY(-5px)';
                        this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '';
                });
            });
        });
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = this.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            });
        });
    </script>
</body>
</html>
