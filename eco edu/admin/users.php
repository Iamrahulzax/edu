<?php
require_once 'admin_header.php';
// $user is already available from admin_header.php

// Handle actions
$message = '';
$message_type = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_status':
                $user_id = (int)$_POST['user_id'];
                $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $message = 'User status updated successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update user status';
                    $message_type = 'error';
                }
                break;
                
            case 'update_role':
                $user_id = (int)$_POST['user_id'];
                $new_role = $_POST['role'];
                if (in_array($new_role, ['student', 'teacher', 'admin'])) {
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    if ($stmt->execute([$new_role, $user_id])) {
                        $message = 'User role updated successfully';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update user role';
                        $message_type = 'error';
                    }
                }
                break;
                
            case 'delete_user':
                $user_id = (int)$_POST['user_id'];
                if ($user_id != $_SESSION['user_id']) { // Don't allow admin to delete themselves
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$user_id])) {
                        $message = 'User deleted successfully';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete user';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Cannot delete your own account';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($role_filter) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = $status_filter === '1' ? 1 : 0;
}

if ($search) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ? OR school_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_users = $stmt->fetch()['total'];
$total_pages = ceil($total_users / $per_page);

// Get users
$query = "SELECT * FROM users $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user statistics
$stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$stmt->execute();
$role_stats = [];
while ($row = $stmt->fetch()) {
    $role_stats[$row['role']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - EcoEdu Admin</title>
    
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
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #28a745, #20c997);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .status-badge {
            font-size: 0.75rem;
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
                <a class="nav-link text-white active" href="users.php">
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
                <h2 class="mb-0">User Management</h2>
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
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $role_stats['student'] ?? 0; ?></h4>
                                <p class="mb-0">Students</p>
                            </div>
                            <i class="fas fa-user-graduate fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $role_stats['teacher'] ?? 0; ?></h4>
                                <p class="mb-0">Teachers</p>
                            </div>
                            <i class="fas fa-chalkboard-teacher fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $role_stats['admin'] ?? 0; ?></h4>
                                <p class="mb-0">Admins</p>
                            </div>
                            <i class="fas fa-user-shield fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $total_users; ?></h4>
                                <p class="mb-0">Total Users</p>
                            </div>
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Management Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-tools me-2"></i>Quick Management Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-outline-primary w-100" onclick="bulkAction('activate')">
                                    <i class="fas fa-user-check me-2"></i>Bulk Activate
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-outline-warning w-100" onclick="bulkAction('deactivate')">
                                    <i class="fas fa-user-times me-2"></i>Bulk Deactivate
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-outline-success w-100" onclick="exportUsers()">
                                    <i class="fas fa-file-export me-2"></i>Export Users
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-outline-info w-100" onclick="sendBulkEmail()">
                                    <i class="fas fa-envelope-bulk me-2"></i>Send Email
                                </button>
                            </div>
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
                               placeholder="Name, username, email...">
                    </div>
                    <div class="col-md-2">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="">All Roles</option>
                            <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Users (<?php echo $total_users; ?> total)</h5>
                <button class="btn btn-success btn-sm" data-mdb-toggle="modal" data-mdb-target="#createUserModal">
                    <i class="fas fa-plus me-1"></i>Add User
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No users found</h5>
                    <p class="text-muted">Try adjusting your search criteria</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>School</th>
                                <th>Points</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-3">
                                            <?php echo strtoupper(substr($u['first_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></h6>
                                            <small class="text-muted">@<?php echo htmlspecialchars($u['username']); ?></small>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $u['role'] === 'admin' ? 'danger' : 
                                            ($u['role'] === 'teacher' ? 'warning' : 'primary'); 
                                    ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['school_name']): ?>
                                        <small><?php echo htmlspecialchars($u['school_name']); ?></small>
                                        <?php if ($u['grade_level']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($u['grade_level']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo number_format($u['eco_points']); ?></span>
                                </td>
                                <td>
                                    <span class="badge status-badge bg-<?php echo $u['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="editUser(<?php echo $u['id']; ?>)" 
                                                data-mdb-toggle="modal" data-mdb-target="#editUserModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-outline-warning" onclick="toggleUserStatus(<?php echo $u['id']; ?>, <?php echo $u['is_active'] ? 'false' : 'true'; ?>)">
                                            <i class="fas fa-<?php echo $u['is_active'] ? 'ban' : 'check'; ?>"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $u['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">Previous</a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">Next</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Hidden Forms for Actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="user_id" id="actionUserId">
        <input type="hidden" name="role" id="actionRole">
    </form>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            sidebar.classList.toggle('show');
        }
        
        function toggleUserStatus(userId, newStatus) {
            if (confirm('Are you sure you want to ' + (newStatus === 'true' ? 'activate' : 'deactivate') + ' this user?')) {
                document.getElementById('actionType').value = 'toggle_status';
                document.getElementById('actionUserId').value = userId;
                document.getElementById('actionForm').submit();
            }
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                document.getElementById('actionType').value = 'delete_user';
                document.getElementById('actionUserId').value = userId;
                document.getElementById('actionForm').submit();
            }
        }
        
        function editUser(userId) {
            // This would open an edit modal - implementation depends on your needs
            console.log('Edit user:', userId);
        }
        
        // New management functions
        function bulkAction(action) {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Please select users first');
                return;
            }
            
            const actionText = action === 'activate' ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${actionText} ${checkboxes.length} selected users?`)) {
                // Implementation for bulk actions
                console.log(`Bulk ${action} for ${checkboxes.length} users`);
            }
        }
        
        function exportUsers() {
            if (confirm('Export all users to CSV?')) {
                // Implementation for user export
                window.location.href = 'export_users.php';
            }
        }
        
        function sendBulkEmail() {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Please select users to send email to');
                return;
            }
            
            const subject = prompt('Email subject:');
            if (subject) {
                const message = prompt('Email message:');
                if (message) {
                    console.log(`Send email to ${checkboxes.length} users: ${subject}`);
                    // Implementation for bulk email
                }
            }
        }
    </script>
</body>
</html>
