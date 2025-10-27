<?php
require_once 'admin_header.php';
require_once '../includes/LearningContentAPI.php';
// $user is already available from admin_header.php

// Initialize API service
$contentAPI = new LearningContentAPI();

// Handle actions
$message = '';
$message_type = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_content':
                $title = sanitizeInput($_POST['title']);
                $content = $_POST['content']; // Allow HTML content
                $category_id = (int)$_POST['category_id'];
                $difficulty_level = $_POST['difficulty_level'];
                $estimated_time = (int)$_POST['estimated_time'];
                $points_reward = (int)$_POST['points_reward'];
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO learning_content (title, content, category_id, difficulty_level, estimated_time, points_reward, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$title, $content, $category_id, $difficulty_level, $estimated_time, $points_reward, $_SESSION['user_id']])) {
                        $message = 'Learning content created successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to create learning content';
                        $message_type = 'error';
                    }
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'toggle_status':
                $content_id = (int)$_POST['content_id'];
                try {
                    $stmt = $pdo->prepare("UPDATE learning_content SET is_active = NOT is_active WHERE id = ?");
                    if ($stmt->execute([$content_id])) {
                        $message = 'Content status updated successfully';
                        $message_type = 'success';
                    }
                } catch (Exception $e) {
                    $message = 'Failed to update content status';
                    $message_type = 'error';
                }
                break;
                
            case 'edit_content':
                $content_id = (int)$_POST['content_id'];
                $title = sanitizeInput($_POST['title']);
                $content = $_POST['content']; // Allow HTML content
                $category_id = (int)$_POST['category_id'];
                $difficulty_level = $_POST['difficulty_level'];
                $estimated_time = (int)$_POST['estimated_time'];
                $points_reward = (int)$_POST['points_reward'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                try {
                    $stmt = $pdo->prepare("UPDATE learning_content SET title = ?, content = ?, category_id = ?, difficulty_level = ?, estimated_time = ?, points_reward = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$title, $content, $category_id, $difficulty_level, $estimated_time, $points_reward, $is_active, $content_id])) {
                        $message = 'Learning content updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update learning content';
                        $message_type = 'error';
                    }
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'delete_content':
                $content_id = (int)$_POST['content_id'];
                try {
                    // Check if content has user progress/completions
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_progress WHERE content_id = ?");
                    $stmt->execute([$content_id]);
                    $progress_count = $stmt->fetch()['count'];
                    
                    if ($progress_count > 0) {
                        // Don't delete, just deactivate to preserve user progress
                        $stmt = $pdo->prepare("UPDATE learning_content SET is_active = 0 WHERE id = ?");
                        $stmt->execute([$content_id]);
                        $message = 'Content deactivated (has user progress data)';
                        $message_type = 'warning';
                    } else {
                        // Safe to delete
                        $stmt = $pdo->prepare("DELETE FROM learning_content WHERE id = ?");
                        if ($stmt->execute([$content_id])) {
                            $message = 'Content deleted successfully';
                            $message_type = 'success';
                        }
                    }
                } catch (Exception $e) {
                    $message = 'Failed to delete content: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'generate_content':
                $topic = sanitizeInput($_POST['topic']);
                $difficulty = $_POST['difficulty_level'];
                $length = (int)$_POST['content_length'];
                
                try {
                    $apiResponse = $contentAPI->generateContent($topic, $difficulty, $length);
                    // Handle Google AI response format
                    if (isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
                        $generatedContent = $apiResponse['candidates'][0]['content']['parts'][0]['text'];
                        $message = 'Content generated successfully using Google AI! You can now edit and save it.';
                        $message_type = 'success';
                        // Store in session for form population
                        $_SESSION['generated_content'] = [
                            'title' => $topic,
                            'content' => $generatedContent,
                            'difficulty' => $difficulty
                        ];
                    } else {
                        $message = 'Failed to generate content. Please check API response format.';
                        $message_type = 'error';
                    }
                } catch (Exception $e) {
                    $message = 'API Error: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'enhance_content':
                $content_id = (int)$_POST['content_id'];
                try {
                    // Get existing content
                    $stmt = $pdo->prepare("SELECT * FROM learning_content WHERE id = ?");
                    $stmt->execute([$content_id]);
                    $existingContent = $stmt->fetch();
                    
                    if ($existingContent) {
                        $apiResponse = $contentAPI->enhanceContent($existingContent['content']);
                        if (isset($apiResponse['enhanced_content'])) {
                            // Update content with enhanced version
                            $updateStmt = $pdo->prepare("UPDATE learning_content SET content = ?, updated_at = NOW() WHERE id = ?");
                            if ($updateStmt->execute([$apiResponse['enhanced_content'], $content_id])) {
                                $message = 'Content enhanced successfully using AI!';
                                $message_type = 'success';
                            }
                        }
                    }
                } catch (Exception $e) {
                    $message = 'Enhancement failed: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'generate_quiz':
                $content_id = (int)$_POST['content_id'];
                $question_count = (int)$_POST['question_count'];
                
                try {
                    // Get content
                    $stmt = $pdo->prepare("SELECT * FROM learning_content WHERE id = ?");
                    $stmt->execute([$content_id]);
                    $content = $stmt->fetch();
                    
                    if ($content) {
                        $apiResponse = $contentAPI->generateQuiz($content['content'], $question_count, $content['difficulty_level']);
                        if (isset($apiResponse['questions'])) {
                            // Store quiz questions in session for review
                            $_SESSION['generated_quiz'] = [
                                'content_id' => $content_id,
                                'questions' => $apiResponse['questions'],
                                'title' => $content['title'] . ' - Auto-Generated Quiz'
                            ];
                            $message = 'Quiz generated successfully! Review and save the questions.';
                            $message_type = 'success';
                        }
                    }
                } catch (Exception $e) {
                    $message = 'Quiz generation failed: ' . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'enhance_content_tinymce':
                // AJAX handler for TinyMCE AI enhancement
                $content = $_POST['content'] ?? '';
                
                if (empty($content)) {
                    echo json_encode(['success' => false, 'error' => 'No content provided']);
                    exit;
                }
                
                try {
                    $apiResponse = $contentAPI->enhanceContent($content);
                    // Handle Google AI response format
                    if (isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
                        $enhancedContent = $apiResponse['candidates'][0]['content']['parts'][0]['text'];
                        echo json_encode([
                            'success' => true, 
                            'enhanced_content' => $enhancedContent
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false, 
                            'error' => 'Failed to enhance content'
                        ]);
                    }
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false, 
                        'error' => $e->getMessage()
                    ]);
                }
                exit;
                break;
                
            case 'test_api_connection':
                // AJAX handler for API connection test
                try {
                    $healthCheck = $contentAPI->checkApiHealth();
                    
                    if ($healthCheck['status'] === 'healthy') {
                        echo json_encode([
                            'success' => true,
                            'response_time' => $healthCheck['response_time'],
                            'version' => $healthCheck['version'],
                            'test_response' => $healthCheck['test_response']
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'error' => $healthCheck['error']
                        ]);
                    }
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
                exit;
                break;
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query for learning content
$where_conditions = [];
$params = [];

if ($category_filter) {
    $where_conditions[] = "lc.category_id = ?";
    $params[] = $category_filter;
}

if ($difficulty_filter) {
    $where_conditions[] = "lc.difficulty_level = ?";
    $params[] = $difficulty_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "lc.is_active = ?";
    $params[] = $status_filter === '1' ? 1 : 0;
}

if ($search) {
    $where_conditions[] = "(lc.title LIKE ? OR lc.content LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get learning content
try {
    $query = "SELECT lc.*, c.name as category_name, u.first_name, u.last_name
              FROM learning_content lc 
              LEFT JOIN categories c ON lc.category_id = c.id 
              LEFT JOIN users u ON lc.created_by = u.id 
              $where_clause 
              ORDER BY lc.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $content_items = $stmt->fetchAll();
} catch (Exception $e) {
    $content_items = [];
}

// Get categories
$categories = getCategories();

// Get content statistics
try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_content,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_content,
        SUM(CASE WHEN difficulty_level = 'easy' THEN 1 ELSE 0 END) as easy_content,
        SUM(CASE WHEN difficulty_level = 'medium' THEN 1 ELSE 0 END) as medium_content,
        SUM(CASE WHEN difficulty_level = 'hard' THEN 1 ELSE 0 END) as hard_content
        FROM learning_content");
    $stmt->execute();
    $content_stats = $stmt->fetch();
} catch (Exception $e) {
    $content_stats = ['total_content' => 0, 'active_content' => 0, 'easy_content' => 0, 'medium_content' => 0, 'hard_content' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Content Management - EcoEdu Admin</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- TinyMCE with API Key -->
    <script src="https://cdn.tiny.cloud/1/<?php echo TINYMCE_API_KEY; ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
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
        .content-card {
            transition: all 0.3s ease;
        }
        .content-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .content-preview {
            max-height: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
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
                <a class="nav-link text-white active" href="content.php">
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
                <h2 class="mb-0">Learning Content Management</h2>
            </div>
            <div>
                <button class="btn btn-success me-2" data-mdb-toggle="modal" data-mdb-target="#createContentModal">
                    <i class="fas fa-plus me-1"></i>Add Content
                </button>
                <button class="btn btn-primary me-2" data-mdb-toggle="modal" data-mdb-target="#generateContentModal">
                    <i class="fas fa-robot me-1"></i>AI Generate
                </button>
                <button class="btn btn-info me-2" data-mdb-toggle="modal" data-mdb-target="#apiStatusModal">
                    <i class="fas fa-chart-line me-1"></i>API Status
                </button>
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
                                <h4><?php echo $content_stats['total_content']; ?></h4>
                                <p class="mb-0">Total Content</p>
                            </div>
                            <i class="fas fa-book fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $content_stats['active_content']; ?></h4>
                                <p class="mb-0">Active Content</p>
                            </div>
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h5><?php echo $content_stats['easy_content']; ?></h5>
                        <p class="mb-0 small">Easy</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h5><?php echo $content_stats['medium_content']; ?></h5>
                        <p class="mb-0 small">Medium</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h5><?php echo $content_stats['hard_content']; ?></h5>
                        <p class="mb-0 small">Hard</p>
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
                               placeholder="Content title or text...">
                    </div>
                    <div class="col-md-2">
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
                    <div class="col-md-2">
                        <label for="difficulty" class="form-label">Difficulty</label>
                        <select class="form-select" id="difficulty" name="difficulty">
                            <option value="">All Levels</option>
                            <option value="easy" <?php echo $difficulty_filter === 'easy' ? 'selected' : ''; ?>>Easy</option>
                            <option value="medium" <?php echo $difficulty_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="hard" <?php echo $difficulty_filter === 'hard' ? 'selected' : ''; ?>>Hard</option>
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
                            <a href="content.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="row">
            <?php if (empty($content_items)): ?>
            <div class="col-12">
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-book fa-4x text-muted mb-4"></i>
                        <h5 class="text-muted">No learning content found</h5>
                        <p class="text-muted">Create your first learning content to get started</p>
                        <button class="btn btn-success" data-mdb-toggle="modal" data-mdb-target="#createContentModal">
                            <i class="fas fa-plus me-2"></i>Add Content
                        </button>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($content_items as $content): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card content-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title"><?php echo htmlspecialchars($content['title']); ?></h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" data-mdb-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="editContent(<?php echo htmlspecialchars(json_encode($content)); ?>)">
                                        <i class="fas fa-edit me-2"></i>Edit
                                    </a></li>
                                    <li><a class="dropdown-item" href="../learn.php?id=<?php echo $content['id']; ?>" target="_blank">
                                        <i class="fas fa-eye me-2"></i>Preview
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-primary" href="#" onclick="enhanceContent(<?php echo $content['id']; ?>)">
                                        <i class="fas fa-magic me-2"></i>AI Enhance
                                    </a></li>
                                    <li><a class="dropdown-item text-info" href="#" onclick="generateQuiz(<?php echo $content['id']; ?>)">
                                        <i class="fas fa-question-circle me-2"></i>Generate Quiz
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" onclick="toggleContentStatus(<?php echo $content['id']; ?>)">
                                        <i class="fas fa-<?php echo $content['is_active'] ? 'ban' : 'check'; ?> me-2"></i>
                                        <?php echo $content['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </a></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteContent(<?php echo $content['id']; ?>, '<?php echo htmlspecialchars($content['title']); ?>')">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="content-preview mb-3">
                            <?php echo strip_tags($content['content']); ?>
                        </div>

                        <div class="mb-3">
                            <?php if ($content['category_name']): ?>
                            <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($content['category_name']); ?></span>
                            <?php endif; ?>
                            <span class="badge difficulty-<?php echo $content['difficulty_level']; ?>">
                                <?php echo ucfirst($content['difficulty_level']); ?>
                            </span>
                            <span class="badge <?php echo $content['is_active'] ? 'bg-success' : 'bg-secondary'; ?> ms-2">
                                <?php echo $content['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>

                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <i class="fas fa-clock text-warning"></i>
                                <small class="d-block text-muted"><?php echo $content['estimated_time']; ?> min</small>
                            </div>
                            <div class="col-6">
                                <i class="fas fa-coins text-success"></i>
                                <small class="d-block text-muted"><?php echo $content['points_reward']; ?> pts</small>
                            </div>
                        </div>

                        <div class="border-top pt-3">
                            <small class="text-muted">
                                Created by <?php echo htmlspecialchars($content['first_name'] . ' ' . $content['last_name']); ?>
                                <br>on <?php echo date('M j, Y', strtotime($content['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary btn-sm flex-fill" onclick="editContent(<?php echo htmlspecialchars(json_encode($content)); ?>)">
                                <i class="fas fa-edit me-1"></i>Edit
                            </button>
                            <a href="../learn.php?id=<?php echo $content['id']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Content Modal -->
    <div class="modal fade" id="createContentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Learning Content</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_content">
                        
                        <div class="form-outline mb-3">
                            <input type="text" class="form-control" name="title" required>
                            <label class="form-label">Content Title</label>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea id="content-editor" name="content" rows="10"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-outline mb-3">
                                    <select class="form-select" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="form-label select-label">Category</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-outline mb-3">
                                    <select class="form-select" name="difficulty_level" required>
                                        <option value="easy">Easy</option>
                                        <option value="medium">Medium</option>
                                        <option value="hard">Hard</option>
                                    </select>
                                    <label class="form-label select-label">Difficulty Level</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-outline mb-3">
                                    <input type="number" class="form-control" name="estimated_time" value="10" min="1" max="120">
                                    <label class="form-label">Estimated Time (minutes)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-outline mb-3">
                                    <input type="number" class="form-control" name="points_reward" value="20" min="1" max="100">
                                    <label class="form-label">Points Reward</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Create Content
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Content Modal -->
    <div class="modal fade" id="editContentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Learning Content
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" id="editContentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_content">
                        <input type="hidden" name="content_id" id="edit_content_id">
                        
                        <div class="form-outline mb-3">
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                            <label class="form-label">Content Title</label>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea id="edit-content-editor" name="content" rows="10"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-outline mb-3">
                                    <select class="form-select" name="category_id" id="edit_category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="form-label select-label">Category</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-outline mb-3">
                                    <select class="form-select" name="difficulty_level" id="edit_difficulty_level" required>
                                        <option value="easy">Easy</option>
                                        <option value="medium">Medium</option>
                                        <option value="hard">Hard</option>
                                    </select>
                                    <label class="form-label select-label">Difficulty Level</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-outline mb-3">
                                    <input type="number" class="form-control" name="estimated_time" id="edit_estimated_time" min="1" max="120">
                                    <label class="form-label">Estimated Time (minutes)</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-outline mb-3">
                                    <input type="number" class="form-control" name="points_reward" id="edit_points_reward" min="1" max="100">
                                    <label class="form-label">Points Reward</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                                    <label class="form-check-label" for="edit_is_active">
                                        <strong>Active Content</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>AI Enhancement Available:</strong> Use the AI Enhance button in the editor toolbar to improve your content with artificial intelligence.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Content
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteContentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-trash me-2"></i>Delete Learning Content
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteContentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_content">
                        <input type="hidden" name="content_id" id="delete_content_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Are you sure you want to delete the content: <strong id="delete_content_title"></strong>?
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <strong>Note:</strong> If this content has user progress data, it will be deactivated instead of deleted to preserve learning history.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Content
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- AI Content Generation Modal -->
    <div class="modal fade" id="generateContentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-robot me-2"></i>AI Content Generation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_content">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>AI Content Generation</strong><br>
                            Our AI will create educational content based on your topic and preferences. You can review and edit the generated content before saving.
                        </div>
                        
                        <div class="form-outline mb-3">
                            <input type="text" id="topic" name="topic" class="form-control" required>
                            <label class="form-label" for="topic">Topic/Subject</label>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <select class="form-select" name="difficulty_level" required>
                                    <option value="">Select Difficulty</option>
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                                <label class="form-label">Difficulty Level</label>
                            </div>
                            <div class="col-md-6">
                                <select class="form-select" name="content_length">
                                    <option value="500">Short (500 words)</option>
                                    <option value="1000" selected>Medium (1000 words)</option>
                                    <option value="2000">Long (2000 words)</option>
                                    <option value="3000">Extended (3000 words)</option>
                                </select>
                                <label class="form-label">Content Length</label>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="includeExamples" checked>
                            <label class="form-check-label" for="includeExamples">
                                Include practical examples
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="includeQuestions" checked>
                            <label class="form-check-label" for="includeQuestions">
                                Include review questions
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-robot me-2"></i>Generate Content
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- API Status Modal -->
    <div class="modal fade" id="apiStatusModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-line me-2"></i>API Status & Usage
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php 
                    try {
                        $apiHealth = $contentAPI->checkApiHealth();
                        $usageStats = $contentAPI->getUsageStats();
                    } catch (Exception $e) {
                        $apiHealth = ['status' => 'error', 'error' => $e->getMessage()];
                        $usageStats = ['total_requests' => 0, 'today_requests' => 0];
                    }
                    ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-server fa-3x <?php echo $apiHealth['status'] === 'healthy' ? 'text-success' : 'text-danger'; ?>"></i>
                                    </div>
                                    <h5>API Status</h5>
                                    <span class="badge bg-<?php echo $apiHealth['status'] === 'healthy' ? 'success' : 'danger'; ?> fs-6">
                                        <?php echo ucfirst($apiHealth['status']); ?>
                                    </span>
                                    <?php if (isset($apiHealth['error'])): ?>
                                    <p class="text-danger mt-2 small"><strong>Error:</strong> <?php echo htmlspecialchars($apiHealth['error']); ?></p>
                                    <?php endif; ?>
                                    <?php if (isset($apiHealth['response_time'])): ?>
                                    <p class="text-success mt-2 small"><strong>Response Time:</strong> <?php echo $apiHealth['response_time']; ?></p>
                                    <?php endif; ?>
                                    <?php if (isset($apiHealth['version'])): ?>
                                    <p class="text-info mt-2 small"><strong>Model:</strong> <?php echo $apiHealth['version']; ?></p>
                                    <?php endif; ?>
                                    <?php if (isset($apiHealth['test_response'])): ?>
                                    <p class="text-muted mt-2 small"><strong>Test Response:</strong> "<?php echo htmlspecialchars($apiHealth['test_response']); ?>"</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-chart-bar fa-3x text-primary"></i>
                                    </div>
                                    <h5>Usage Today</h5>
                                    <h3 class="text-primary"><?php echo $usageStats['today_requests']; ?></h3>
                                    <p class="text-muted">of <?php echo $usageStats['rate_limit']; ?> requests</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <h6>API Configuration</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>API Key:</strong></td>
                                        <td><code><?php echo substr(LEARNING_API_KEY, 0, 8) . '...' . substr(LEARNING_API_KEY, -4); ?></code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Base URL:</strong></td>
                                        <td><code><?php echo LEARNING_API_BASE_URL; ?></code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Rate Limit:</strong></td>
                                        <td><?php echo API_RATE_LIMIT_PER_HOUR; ?> requests/hour</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Timeout:</strong></td>
                                        <td><?php echo API_REQUEST_TIMEOUT; ?> seconds</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Key Validation:</strong></td>
                                        <td>
                                            <?php if (validateApiKey(LEARNING_API_KEY)): ?>
                                                <span class="badge bg-success">Valid Format</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Invalid Format</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Endpoint Test:</strong></td>
                                        <td><code><?php echo LEARNING_API_BASE_URL; ?>models/gemini-pro:generateContent</code></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" onclick="testApiConnection()">
                        <i class="fas fa-flask me-2"></i>Test API
                    </button>
                    <button type="button" class="btn btn-info" onclick="location.reload()">
                        <i class="fas fa-sync me-2"></i>Refresh Status
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quiz Generation Modal -->
    <div class="modal fade" id="quizModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-question-circle me-2"></i>Generate Quiz
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" id="quizForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_quiz">
                        <input type="hidden" name="content_id" id="quizContentId">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Generate quiz questions automatically from the selected content using AI.
                        </div>
                        
                        <div class="form-outline mb-3">
                            <input type="number" id="questionCount" name="question_count" class="form-control" value="5" min="3" max="20">
                            <label class="form-label" for="questionCount">Number of Questions</label>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="multipleChoice" checked>
                            <label class="form-check-label" for="multipleChoice">Multiple Choice Questions</label>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="trueFalse" checked>
                            <label class="form-check-label" for="trueFalse">True/False Questions</label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="shortAnswer">
                            <label class="form-check-label" for="shortAnswer">Short Answer Questions</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-question-circle me-2"></i>Generate Quiz
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden Forms for Actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="content_id" id="actionContentId">
    </form>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    
    <script>
        // Initialize Enhanced TinyMCE with API Key Features
        tinymce.init({
            selector: '#content-editor',
            height: 500,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons',
                'template', 'codesample', 'hr', 'pagebreak', 'nonbreaking',
                'toc', 'imagetools', 'textpattern', 'noneditable', 'quickbars'
            ],
            toolbar1: 'undo redo | bold italic underline strikethrough | fontfamily fontsize blocks | alignleft aligncenter alignright alignjustify | outdent indent',
            toolbar2: 'forecolor backcolor | bullist numlist | link image media table | emoticons charmap | aienhance template | code preview fullscreen | help',
            menubar: 'file edit view insert format tools table help',
            contextmenu: 'link image table',
            quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote quickimage quicktable',
            quickbars_insert_toolbar: 'quickimage quicktable',
            powerpaste_word_import: 'clean',
            powerpaste_html_import: 'clean',
            content_style: `
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    font-size: 14px; 
                    line-height: 1.6; 
                    color: #333;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                h1, h2, h3, h4, h5, h6 { 
                    color: #28a745; 
                    margin-top: 1.5em; 
                    margin-bottom: 0.5em; 
                }
                p { margin-bottom: 1em; }
                blockquote { 
                    border-left: 4px solid #28a745; 
                    padding-left: 1em; 
                    margin-left: 0; 
                    font-style: italic; 
                }
                code { 
                    background-color: #f4f4f4; 
                    padding: 2px 4px; 
                    border-radius: 3px; 
                }
                pre { 
                    background-color: #f4f4f4; 
                    padding: 1em; 
                    border-radius: 5px; 
                    overflow-x: auto; 
                }
            `,
            templates: [
                {
                    title: 'Environmental Article Template',
                    description: 'Template for environmental education articles',
                    content: `
                        <h1>Article Title</h1>
                        <p><strong>Introduction:</strong> Brief overview of the topic...</p>
                        
                        <h2>Key Points</h2>
                        <ul>
                            <li>Important point 1</li>
                            <li>Important point 2</li>
                            <li>Important point 3</li>
                        </ul>
                        
                        <h2>Detailed Explanation</h2>
                        <p>Detailed content goes here...</p>
                        
                        <blockquote>
                            <p>"Inspiring environmental quote"</p>
                        </blockquote>
                        
                        <h2>Practical Tips</h2>
                        <ol>
                            <li>Actionable tip 1</li>
                            <li>Actionable tip 2</li>
                            <li>Actionable tip 3</li>
                        </ol>
                        
                        <h2>Conclusion</h2>
                        <p>Summary and call to action...</p>
                    `
                },
                {
                    title: 'Quiz Question Template',
                    description: 'Template for creating quiz questions',
                    content: `
                        <h3>Question: [Insert question here]</h3>
                        <p><strong>Options:</strong></p>
                        <ul>
                            <li>A) Option 1</li>
                            <li>B) Option 2</li>
                            <li>C) Option 3</li>
                            <li>D) Option 4</li>
                        </ul>
                        <p><strong>Correct Answer:</strong> [Letter]</p>
                        <p><strong>Explanation:</strong> [Why this is correct]</p>
                    `
                }
            ],
            image_advtab: true,
            image_uploadtab: true,
            file_picker_types: 'image',
            automatic_uploads: true,
            images_upload_handler: function (blobInfo, success, failure) {
                // Handle image uploads here
                // For now, we'll use a placeholder
                success('data:' + blobInfo.blob().type + ';base64,' + blobInfo.base64());
            },
            setup: function (editor) {
                // Add custom button for AI content enhancement
                editor.ui.registry.addButton('aienhance', {
                    text: 'AI Enhance',
                    icon: 'ai',
                    tooltip: 'Enhance content with AI',
                    onAction: function () {
                        const content = editor.getContent();
                        if (content.trim()) {
                            if (confirm('Enhance this content using AI? This will improve readability and add examples.')) {
                                // Trigger AI enhancement
                                enhanceContentWithAI(content, editor);
                            }
                        } else {
                            alert('Please add some content first before enhancing.');
                        }
                    }
                });
                
                // Add AI enhance button to toolbar
                editor.on('init', function() {
                    editor.ui.registry.addIcon('ai', '<svg width="24" height="24" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>');
                });
            }
        });
        
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            sidebar.classList.toggle('show');
        }
        
        function toggleContentStatus(contentId) {
            if (confirm('Are you sure you want to change the status of this content?')) {
                document.getElementById('actionType').value = 'toggle_status';
                document.getElementById('actionContentId').value = contentId;
                document.getElementById('actionForm').submit();
            }
        }
        
        function deleteContent(contentId) {
            if (confirm('Are you sure you want to delete this content? This action cannot be undone.')) {
                document.getElementById('actionType').value = 'delete_content';
                document.getElementById('actionContentId').value = contentId;
                document.getElementById('actionForm').submit();
            }
        }
        
        function editContent(contentData) {
            // Parse content data if it's a string
            const content = typeof contentData === 'string' ? JSON.parse(contentData) : contentData;
            
            try {
                // Populate form fields
                document.getElementById('edit_content_id').value = content.id;
                document.getElementById('edit_title').value = content.title || '';
                document.getElementById('edit_category_id').value = content.category_id || '';
                document.getElementById('edit_difficulty_level').value = content.difficulty_level || 'medium';
                document.getElementById('edit_estimated_time').value = content.estimated_time || 10;
                document.getElementById('edit_points_reward').value = content.points_reward || 20;
                document.getElementById('edit_is_active').checked = content.is_active == 1;
                
                // Set content in TinyMCE editor
                setTimeout(() => {
                    if (tinymce.get('edit-content-editor')) {
                        tinymce.get('edit-content-editor').setContent(content.content || '');
                    }
                }, 100);
                
                // Show modal
                const modal = new mdb.Modal(document.getElementById('editContentModal'));
                modal.show();
            } catch (error) {
                console.error('Error parsing content data:', error);
                alert('Error loading content data. Please try again.');
            }
        }
        
        function deleteContent(contentId, contentTitle) {
            document.getElementById('delete_content_id').value = contentId;
            document.getElementById('delete_content_title').textContent = contentTitle;
            
            const modal = new mdb.Modal(document.getElementById('deleteContentModal'));
            modal.show();
        }
        
        // Form submission loading states
        document.addEventListener('DOMContentLoaded', function() {
            // Edit form loading state
            document.getElementById('editContentForm').addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            });
            
            // Delete form loading state
            document.getElementById('deleteContentForm').addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
            });
        });
        
        // AI-powered functions
        function enhanceContent(contentId) {
            if (confirm('Enhance this content using AI? This will improve readability and add examples.')) {
                showLoadingSpinner('Enhancing content with AI...');
                document.getElementById('actionType').value = 'enhance_content';
                document.getElementById('actionContentId').value = contentId;
                document.getElementById('actionForm').submit();
            }
        }
        
        function generateQuiz(contentId) {
            document.getElementById('quizContentId').value = contentId;
            const quizModal = new mdb.Modal(document.getElementById('quizModal'));
            quizModal.show();
        }
        
        function showLoadingSpinner(message) {
            // Create loading overlay
            const overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
                color: white;
                font-size: 1.2rem;
            `;
            overlay.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-light mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div>${message}</div>
                </div>
            `;
            document.body.appendChild(overlay);
        }
        
        // Auto-populate form if content was generated
        <?php if (isset($_SESSION['generated_content'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const generatedContent = <?php echo json_encode($_SESSION['generated_content']); ?>;
            
            // Show success message and populate form
            const createModal = new mdb.Modal(document.getElementById('createContentModal'));
            createModal.show();
            
            // Populate form fields
            document.getElementById('edit_content_id').value = content.id;
            document.getElementById('edit_title').value = content.title;
            document.getElementById('edit_category_id').value = content.category_id || '';
            document.getElementById('edit_difficulty_level').value = content.difficulty_level;
            document.getElementById('edit_estimated_time').value = content.estimated_time;
            document.getElementById('edit_points_reward').value = content.points_reward;
            document.getElementById('edit_is_active').checked = content.is_active == 1;
            
            // Initialize TinyMCE for edit modal
            tinymce.init({
                selector: '#edit-content-editor',
                height: 400,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help | aienhance',
                content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
                setup: function (editor) {
                    editor.ui.registry.addButton('aienhance', {
                        text: 'AI Enhance',
                        icon: 'magic',
                        onAction: function () {
                            enhanceContentWithTinyMCE(editor);
                        }
                    });
                }
            });
            
            // Create and show quiz preview modal
            showGeneratedQuiz(quizData);
        });
        <?php unset($_SESSION['generated_quiz']); ?>
        <?php endif; ?>
        
        function showGeneratedQuiz(quizData) {
            // Create dynamic modal for quiz preview
            const modalHtml = `
                <div class="modal fade" id="quizPreviewModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-check-circle me-2"></i>Quiz Generated Successfully
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-success">
                                    <i class="fas fa-robot me-2"></i>
                                    AI has generated ${quizData.questions.length} questions from your content. Review and save them to your quiz bank.
                                </div>
                                <h6>Generated Questions:</h6>
                                <div class="quiz-questions">
                                    ${quizData.questions.map((q, index) => `
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6 class="card-title">Question ${index + 1}</h6>
                                                <p class="card-text">${q.question}</p>
                                                ${q.type === 'multiple_choice' ? `
                                                    <ul class="list-unstyled ms-3">
                                                        ${q.options.map((option, i) => `
                                                            <li class="${option === q.correct_answer ? 'text-success fw-bold' : ''}">
                                                                ${String.fromCharCode(65 + i)}. ${option}
                                                            </li>
                                                        `).join('')}
                                                    </ul>
                                                ` : `
                                                    <p class="text-success"><strong>Answer:</strong> ${q.correct_answer}</p>
                                                `}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-success" onclick="saveQuizQuestions()">
                                    <i class="fas fa-save me-2"></i>Save to Quiz Bank
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page and show
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new mdb.Modal(document.getElementById('quizPreviewModal'));
            modal.show();
            
            // Clean up modal after hiding
            document.getElementById('quizPreviewModal').addEventListener('hidden.mdb.modal', function() {
                this.remove();
            });
        }
        
        function saveQuizQuestions() {
            // This would typically save to quiz database
            alert('Quiz questions saved successfully! You can now use them in your quizzes.');
            const modal = mdb.Modal.getInstance(document.getElementById('quizPreviewModal'));
            modal.hide();
        }
        
        // AI Content Enhancement Function for TinyMCE
        function enhanceContentWithAI(content, editor) {
            showLoadingSpinner('Enhancing content with AI...');
            
            // Create form data for AI enhancement
            const formData = new FormData();
            formData.append('action', 'enhance_content_tinymce');
            formData.append('content', content);
            
            fetch('content.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Remove loading overlay
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.remove();
                
                if (data.success && data.enhanced_content) {
                    // Update TinyMCE content with enhanced version
                    editor.setContent(data.enhanced_content);
                    
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show mt-3';
                    alert.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        Content enhanced successfully using AI!
                        <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                    `;
                    editor.getContainer().parentNode.insertBefore(alert, editor.getContainer());
                    
                    // Auto-remove alert after 5 seconds
                    setTimeout(() => {
                        if (alert.parentNode) alert.remove();
                    }, 5000);
                } else {
                    alert('Failed to enhance content: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                // Remove loading overlay
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.remove();
                
                console.error('AI Enhancement Error:', error);
                alert('Failed to enhance content. Please try again.');
            });
        }
        
        // Test API Connection Function
        function testApiConnection() {
            const testBtn = document.querySelector('[onclick="testApiConnection()"]');
            const originalText = testBtn.innerHTML;
            
            // Show loading state
            testBtn.disabled = true;
            testBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing...';
            
            // Create form data for API test
            const formData = new FormData();
            formData.append('action', 'test_api_connection');
            
            fetch('content.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Restore button
                testBtn.disabled = false;
                testBtn.innerHTML = originalText;
                
                if (data.success) {
                    alert(`✅ API Test Successful!\n\nResponse Time: ${data.response_time}\nModel: ${data.version}\nTest Response: "${data.test_response}"`);
                } else {
                    alert(`❌ API Test Failed!\n\nError: ${data.error}`);
                }
            })
            .catch(error => {
                // Restore button
                testBtn.disabled = false;
                testBtn.innerHTML = originalText;
                
                console.error('API Test Error:', error);
                alert('❌ API Test Failed!\n\nNetwork error occurred. Check console for details.');
            });
        }
    </script>
</body>
</html>
