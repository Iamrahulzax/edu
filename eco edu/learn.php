<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/LearningContentAPI.php';

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

// Initialize API service
$contentAPI = new LearningContentAPI();

// Handle AI content generation request
$ai_content = null;
$ai_message = '';
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'ask_ai') {
    $topic = sanitizeInput($_POST['topic']);
    $difficulty = $_POST['difficulty'] ?? 'intermediate';
    
    try {
        $apiResponse = $contentAPI->generateContent($topic, $difficulty, 800);
        // Handle Google AI response format
        if (isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
            $ai_content = [
                'title' => $topic,
                'content' => $apiResponse['candidates'][0]['content']['parts'][0]['text'],
                'difficulty' => $difficulty,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            $ai_message = 'Content generated successfully using AI!';
        } else {
            $ai_message = 'Failed to generate content. Please try again.';
        }
    } catch (Exception $e) {
        $ai_message = 'AI Error: ' . $e->getMessage();
    }
}

// Get specific content if ID is provided
$content_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$single_content = null;

if ($content_id) {
    try {
        $stmt = $pdo->prepare("SELECT lc.*, c.name as category_name FROM learning_content lc LEFT JOIN categories c ON lc.category_id = c.id WHERE lc.id = ? AND lc.is_active = 1");
        $stmt->execute([$content_id]);
        $single_content = $stmt->fetch();
        
        if ($single_content) {
            // Ensure progress table exists
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_content_progress (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    content_id INT NOT NULL,
                    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_user_content (user_id, content_id),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (content_id) REFERENCES learning_content(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Exception $e) {
                // If table creation fails, continue without awarding points
            }
            
            // Award points for reading content (only once per user per content)
            try {
                $stmt = $pdo->prepare("SELECT id FROM user_content_progress WHERE user_id = ? AND content_id = ?");
                $stmt->execute([$user['id'], $content_id]);
                if (!$stmt->fetch()) {
                    // First time reading this content
                    $stmt = $pdo->prepare("INSERT INTO user_content_progress (user_id, content_id, completed_at) VALUES (?, ?, NOW())");
                    $stmt->execute([$user['id'], $content_id]);
                    
                    // Award points (fallback to 10 if not set)
                    $pointsReward = (int)($single_content['points_reward'] ?? 0);
                    if ($pointsReward <= 0) {
                        $pointsReward = 10;
                    }
                    addEcoPoints($user['id'], $pointsReward, "Read: " . $single_content['title']);
                }
            } catch (Exception $e) {
                // Ignore progress errors to avoid blocking content viewing
            }
        }
    } catch (Exception $e) {
        $single_content = null;
    }
}

// Get filter parameters for content listing
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get learning content
$learning_content = [];
if (!$single_content) {
    try {
        $where_conditions = ["lc.is_active = 1"];
        $params = [];

        if ($category_filter) {
            $where_conditions[] = "lc.category_id = ?";
            $params[] = $category_filter;
        }

        if ($difficulty_filter) {
            $where_conditions[] = "lc.difficulty_level = ?";
            $params[] = $difficulty_filter;
        }

        if ($search) {
            $where_conditions[] = "(lc.title LIKE ? OR lc.content LIKE ?)";
            $search_term = "%$search%";
            $params = array_merge($params, [$search_term, $search_term]);
        }

        $where_clause = "WHERE " . implode(" AND ", $where_conditions);

        $query = "SELECT lc.*, c.name as category_name FROM learning_content lc LEFT JOIN categories c ON lc.category_id = c.id $where_clause ORDER BY lc.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $learning_content = $stmt->fetchAll();
    } catch (Exception $e) {
        $learning_content = [];
    }
}

// Get categories
$categories = getCategories();

// Get user's reading progress
$user_progress = [];
try {
    $stmt = $pdo->prepare("SELECT content_id FROM user_content_progress WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    while ($row = $stmt->fetch()) {
        $user_progress[$row['content_id']] = true;
    }
} catch (Exception $e) {
    // Table might not exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $single_content ? htmlspecialchars($single_content['title']) . ' - ' : ''; ?>Learn - EcoEdu</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- TinyMCE for enhanced content display -->
    <script src="https://cdn.tiny.cloud/1/<?php echo TINYMCE_API_KEY; ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .content-card {
            transition: all 0.3s ease;
            border-left: 5px solid transparent;
        }
        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .content-card.completed {
            border-left-color: #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #ffffff 100%);
        }
        .reading-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #333;
        }
        .reading-content h1, .reading-content h2, .reading-content h3 {
            color: #28a745;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .reading-content p {
            margin-bottom: 1.5rem;
        }
        .reading-content img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin: 1.5rem 0;
        }
        .reading-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 4px;
            background: linear-gradient(90deg, #28a745, #20c997);
            z-index: 9999;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body data-theme="light">
    <!-- Reading Progress Bar -->
    <div class="reading-progress" id="readingProgress"></div>

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
                        <a class="nav-link text-white active" href="learn.php">
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
            <?php if ($single_content): ?>
            <!-- Single Content View -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <!-- Back Button -->
                    <div class="mb-4">
                        <a href="learn.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Learning
                        </a>
                    </div>

                    <!-- Content Header -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h1 class="card-title"><?php echo htmlspecialchars($single_content['title']); ?></h1>
                                    <?php if ($single_content['category_name']): ?>
                                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($single_content['category_name']); ?></span>
                                    <?php endif; ?>
                                    <span class="badge difficulty-<?php echo $single_content['difficulty_level']; ?>">
                                        <?php echo ucfirst($single_content['difficulty_level']); ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <div class="eco-points mb-2">
                                        <i class="fas fa-coins me-1"></i>
                                        <?php echo $single_content['points_reward']; ?> Points
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo $single_content['estimated_time']; ?> min read
                                    </small>
                                </div>
                            </div>
                            
                            <?php if (isset($user_progress[$single_content['id']])): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                You've completed this content and earned <?php echo $single_content['points_reward']; ?> eco-points!
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Content Body -->
                    <div class="card">
                        <div class="card-body">
                            <div class="reading-content">
                                <?php echo $single_content['content']; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Content Footer -->
                    <div class="card mt-4">
                        <div class="card-body text-center">
                            <h5>Ready to test your knowledge?</h5>
                            <p class="text-muted">Take a quiz to reinforce what you've learned</p>
                            <a href="quizzes.php" class="btn btn-success">
                                <i class="fas fa-brain me-2"></i>Take a Quiz
                            </a>
                            <a href="challenges.php" class="btn btn-outline-primary ms-2">
                                <i class="fas fa-trophy me-2"></i>Join a Challenge
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Content Listing View -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="text-gradient"><i class="fas fa-book me-2"></i>Learn About Environment</h1>
                            <p class="text-muted">Discover amazing facts about our planet and how to protect it!</p>
                        </div>
                        <div class="text-end">
                            <div class="eco-points">
                                <i class="fas fa-coins me-1"></i>
                                <?php echo number_format($user['eco_points']); ?> Points
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ask AI Content Generation -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-robot me-2"></i>Ask AI to Generate Learning Content
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($ai_message): ?>
                            <div class="alert alert-<?php echo $ai_content ? 'success' : 'danger'; ?> alert-dismissible fade show">
                                <i class="fas fa-<?php echo $ai_content ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                                <?php echo htmlspecialchars($ai_message); ?>
                                <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($ai_content): ?>
                            <!-- Display Generated Content -->
                            <div class="generated-content mb-4">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-sparkles me-2"></i>Generated Content: <?php echo htmlspecialchars($ai_content['title']); ?>
                                            <span class="badge bg-<?php echo $ai_content['difficulty'] === 'beginner' ? 'success' : ($ai_content['difficulty'] === 'intermediate' ? 'warning' : 'danger'); ?> ms-2">
                                                <?php echo ucfirst($ai_content['difficulty']); ?>
                                            </span>
                                        </h6>
                                        <small class="text-muted">Generated on <?php echo $ai_content['generated_at']; ?></small>
                                    </div>
                                    <div class="card-body">
                                        <div class="reading-content">
                                            <?php echo nl2br(htmlspecialchars($ai_content['content'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- AI Content Request Form -->
                            <form method="POST" id="askAiForm">
                                <input type="hidden" name="action" value="ask_ai">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="aiTopic" class="form-label">
                                            <i class="fas fa-lightbulb me-1"></i>What would you like to learn about?
                                        </label>
                                        <input type="text" class="form-control" id="aiTopic" name="topic" 
                                               placeholder="e.g., Climate Change, Renewable Energy, Ocean Pollution..." 
                                               required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="aiDifficulty" class="form-label">
                                            <i class="fas fa-layer-group me-1"></i>Difficulty Level
                                        </label>
                                        <select class="form-select" id="aiDifficulty" name="difficulty">
                                            <option value="beginner">Beginner</option>
                                            <option value="intermediate" selected>Intermediate</option>
                                            <option value="advanced">Advanced</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary w-100" id="generateBtn">
                                            <i class="fas fa-magic me-2"></i>Generate
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        AI will create personalized learning content on your chosen topic using Google's Gemini AI.
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search learning content...">
                                </div>
                                <div class="col-md-3">
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
                                <div class="col-md-3">
                                    <label for="difficulty" class="form-label">Difficulty</label>
                                    <select class="form-select" id="difficulty" name="difficulty">
                                        <option value="">All Levels</option>
                                        <option value="easy" <?php echo $difficulty_filter === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                        <option value="medium" <?php echo $difficulty_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="hard" <?php echo $difficulty_filter === 'hard' ? 'selected' : ''; ?>>Hard</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-1"></i>Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Learning Content Grid -->
            <div class="row">
                <?php if (empty($learning_content)): ?>
                <div class="col-12">
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-book fa-4x text-muted mb-4"></i>
                            <h3 class="text-muted">No learning content found</h3>
                            <p class="text-muted">Try adjusting your search filters or check back later for new content.</p>
                            <a href="learn.php" class="btn btn-primary">View All Content</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($learning_content as $content): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card content-card h-100 <?php echo isset($user_progress[$content['id']]) ? 'completed' : ''; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title"><?php echo htmlspecialchars($content['title']); ?></h5>
                                <?php if (isset($user_progress[$content['id']])): ?>
                                <i class="fas fa-check-circle text-success fa-lg"></i>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <?php if ($content['category_name']): ?>
                                <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($content['category_name']); ?></span>
                                <?php endif; ?>
                                <span class="badge difficulty-<?php echo $content['difficulty_level']; ?>">
                                    <?php echo ucfirst($content['difficulty_level']); ?>
                                </span>
                            </div>

                            <p class="card-text text-muted">
                                <?php echo substr(strip_tags($content['content']), 0, 150) . '...'; ?>
                            </p>

                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <i class="fas fa-clock text-warning"></i>
                                    <small class="d-block text-muted"><?php echo $content['estimated_time']; ?> min</small>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-coins text-success"></i>
                                    <small class="d-block text-muted"><?php echo $content['points_reward']; ?> points</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="learn.php?id=<?php echo $content['id']; ?>" class="btn btn-primary w-100">
                                <i class="fas fa-book-open me-2"></i>
                                <?php echo isset($user_progress[$content['id']]) ? 'Read Again' : 'Start Reading'; ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        // Reading progress indicator
        <?php if ($single_content): ?>
        window.addEventListener('scroll', function() {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById('readingProgress').style.width = scrolled + '%';
        });
        <?php endif; ?>
        
        // Auto-focus search on page load
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }
        });

        // AI Content Generation Enhancement
        document.getElementById('askAiForm').addEventListener('submit', function(e) {
            const generateBtn = document.getElementById('generateBtn');
            const originalText = generateBtn.innerHTML;
            
            // Show loading state
            generateBtn.disabled = true;
            generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
            
            // Add loading overlay
            const overlay = document.createElement('div');
            overlay.id = 'aiLoadingOverlay';
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
                    <div>AI is generating your personalized content...</div>
                    <small class="text-muted mt-2 d-block">This may take a few seconds</small>
                </div>
            `;
            document.body.appendChild(overlay);
            
            // The form will submit normally, but with enhanced UX
        });

        // Auto-scroll to generated content if available
        <?php if ($ai_content): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const generatedContent = document.querySelector('.generated-content');
            if (generatedContent) {
                generatedContent.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
                
                // Add highlight effect
                generatedContent.style.animation = 'highlight 2s ease-in-out';
            }
        });
        <?php endif; ?>
        
        // Add CSS for highlight animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes highlight {
                0% { background-color: rgba(0, 123, 255, 0.1); }
                50% { background-color: rgba(0, 123, 255, 0.2); }
                100% { background-color: transparent; }
            }
            
            .generated-content .card {
                border: 2px solid #007bff;
                box-shadow: 0 8px 25px rgba(0, 123, 255, 0.15);
            }
            
            .reading-content {
                font-size: 1.05rem;
                line-height: 1.7;
            }
            
            .reading-content h1, .reading-content h2, .reading-content h3 {
                color: #007bff;
                margin-top: 1.5rem;
                margin-bottom: 1rem;
            }
        `;
        document.head.appendChild(style);
        });
    </script>
</body>
</html>
