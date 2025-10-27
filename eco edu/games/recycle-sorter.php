<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Handle game completion with enhanced scoring system
$message = '';
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'save_score') {
    $score = intval($_POST['score']);
    $correct_sorts = intval($_POST['correct_sorts']);
    $wrong_sorts = intval($_POST['wrong_sorts']);
    $total_items = intval($_POST['total_items']);
    $level = intval($_POST['level']);
    $streak_bonus = intval($_POST['streak_bonus']);
    $time_bonus = intval($_POST['time_bonus']);
    
    try {
        $accuracy = ($total_items > 0) ? ($correct_sorts / $total_items) * 100 : 0;
        $final_score = $score + $streak_bonus + $time_bonus;
        
        $game_data = json_encode([
            'correct_sorts' => $correct_sorts,
            'wrong_sorts' => $wrong_sorts,
            'total_items' => $total_items,
            'accuracy' => $accuracy,
            'level' => $level,
            'streak_bonus' => $streak_bonus,
            'time_bonus' => $time_bonus,
            'final_score' => $final_score
        ]);
        
        $stmt = $pdo->prepare("
            INSERT INTO game_results (user_id, game_type, score, points_earned, game_data) 
            VALUES (?, 'recycle_sorter', ?, ?, ?)
        ");
        
        // Enhanced points calculation: base score + level multiplier
        $points_earned = $final_score + ($level * 5);
        $stmt->execute([$user['id'], $final_score, $points_earned, $game_data]);
        
        addEcoPoints($user['id'], $points_earned, "Recycle Sorter Game - Level $level");
        $message = "Game completed! Score: {$final_score}. Level: {$level}. +{$points_earned} eco-points earned!";
    } catch (Exception $e) {
        $message = 'Error saving score. Please try again.';
    }
}

// Get user's best scores
try {
    $stmt = $pdo->prepare("
        SELECT * FROM game_results 
        WHERE user_id = ? AND game_type = 'recycle_sorter' 
        ORDER BY score DESC 
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $high_scores = $stmt->fetchAll();
} catch (Exception $e) {
    $high_scores = [];
}

// Enhanced recyclable items data with level-based difficulty
$items = [
    // Level 1 - Easy items (basic categories)
    ['name' => 'Plastic Bottle', 'category' => 'plastic', 'image' => '🍼', 'points' => 10, 'level' => 1],
    ['name' => 'Aluminum Can', 'category' => 'metal', 'image' => '🥤', 'points' => 10, 'level' => 1],
    ['name' => 'Glass Jar', 'category' => 'glass', 'image' => '🫙', 'points' => 10, 'level' => 1],
    ['name' => 'Newspaper', 'category' => 'paper', 'image' => '📰', 'points' => 10, 'level' => 1],
    ['name' => 'Apple Core', 'category' => 'organic', 'image' => '🍎', 'points' => 10, 'level' => 1],
    
    // Level 2 - Medium items (more variety)
    ['name' => 'Plastic Cup', 'category' => 'plastic', 'image' => '🥤', 'points' => 15, 'level' => 2],
    ['name' => 'Cardboard Box', 'category' => 'paper', 'image' => '📦', 'points' => 15, 'level' => 2],
    ['name' => 'Tin Can', 'category' => 'metal', 'image' => '🥫', 'points' => 15, 'level' => 2],
    ['name' => 'Wine Bottle', 'category' => 'glass', 'image' => '🍷', 'points' => 15, 'level' => 2],
    ['name' => 'Banana Peel', 'category' => 'organic', 'image' => '🍌', 'points' => 15, 'level' => 2],
    ['name' => 'Plastic Bag', 'category' => 'plastic', 'image' => '🛍️', 'points' => 15, 'level' => 2],
    ['name' => 'Magazine', 'category' => 'paper', 'image' => '📖', 'points' => 15, 'level' => 2],
    
    // Level 3 - Hard items (tricky categories)
    ['name' => 'Milk Carton', 'category' => 'paper', 'image' => '🥛', 'points' => 20, 'level' => 3],
    ['name' => 'Pizza Box', 'category' => 'paper', 'image' => '📦', 'points' => 20, 'level' => 3],
    ['name' => 'Aluminum Foil', 'category' => 'metal', 'image' => '🍽️', 'points' => 20, 'level' => 3],
    ['name' => 'Glass Bottle', 'category' => 'glass', 'image' => '🍾', 'points' => 20, 'level' => 3],
    ['name' => 'Coffee Grounds', 'category' => 'organic', 'image' => '☕', 'points' => 20, 'level' => 3],
    ['name' => 'Yogurt Container', 'category' => 'plastic', 'image' => '🥛', 'points' => 20, 'level' => 3],
    ['name' => 'Cereal Box', 'category' => 'paper', 'image' => '📦', 'points' => 20, 'level' => 3],
    ['name' => 'Soda Can', 'category' => 'metal', 'image' => '🥤', 'points' => 20, 'level' => 3],
    ['name' => 'Jam Jar', 'category' => 'glass', 'image' => '🫙', 'points' => 20, 'level' => 3],
    ['name' => 'Vegetable Scraps', 'category' => 'organic', 'image' => '🥬', 'points' => 20, 'level' => 3],
    ['name' => 'Plastic Wrapper', 'category' => 'plastic', 'image' => '🍫', 'points' => 20, 'level' => 3],
    ['name' => 'Office Paper', 'category' => 'paper', 'image' => '📄', 'points' => 20, 'level' => 3]
];

// Game levels configuration
$game_levels = [
    1 => ['name' => 'Easy', 'time' => 60, 'spawn_rate' => 3000, 'items_count' => 5],
    2 => ['name' => 'Medium', 'time' => 45, 'spawn_rate' => 2000, 'items_count' => 8],
    3 => ['name' => 'Hard', 'time' => 30, 'spawn_rate' => 1000, 'items_count' => 12]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Sorter - EcoEdu Games</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .game-container {
            min-height: 80vh;
            background: linear-gradient(135deg, #e8f5e8, #f0f8f0);
            border-radius: 20px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .game-area {
            position: relative;
            min-height: 500px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
        }
        
        .recycle-bin {
            width: 140px;
            height: 160px;
            border: 3px dashed #666;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .recycle-bin:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .recycle-bin.drag-over {
            background-color: rgba(40, 167, 69, 0.2);
            border-color: #28a745;
            border-style: solid;
            transform: scale(1.1);
            box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
        }
        
        .item-card {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: grab;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 10px;
            position: absolute;
            z-index: 10;
            border: 2px solid #e9ecef;
        }
        
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .item-card.dragging {
            opacity: 0.5;
            transform: rotate(5deg);
        }
        
        .item-emoji {
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        
        .item-name {
            font-size: 0.8rem;
            font-weight: bold;
            text-align: center;
            color: #333;
        }
        
        /* Color-coded bins */
        .bin-plastic { 
            border-color: #007bff; 
            background: linear-gradient(135deg, rgba(0,123,255,0.1), rgba(0,123,255,0.05));
        }
        .bin-metal { 
            border-color: #6c757d; 
            background: linear-gradient(135deg, rgba(108,117,125,0.1), rgba(108,117,125,0.05));
        }
        .bin-glass { 
            border-color: #28a745; 
            background: linear-gradient(135deg, rgba(40,167,69,0.1), rgba(40,167,69,0.05));
        }
        .bin-paper { 
            border-color: #ffc107; 
            background: linear-gradient(135deg, rgba(255,193,7,0.1), rgba(255,193,7,0.05));
        }
        .bin-organic { 
            border-color: #fd7e14; 
            background: linear-gradient(135deg, rgba(253,126,20,0.1), rgba(253,126,20,0.05));
        }
        
        .score-display {
            font-size: 2.5rem;
            font-weight: bold;
            color: #28a745;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .timer-display {
            font-size: 2rem;
            font-weight: bold;
            color: #dc3545;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .level-display {
            font-size: 1.8rem;
            font-weight: bold;
            color: #6f42c1;
        }
        
        .streak-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fd7e14;
        }
        
        .feedback-popup {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 20px 40px;
            border-radius: 15px;
            font-size: 1.5rem;
            font-weight: bold;
            z-index: 1000;
            animation: fadeInOut 2s ease-in-out;
        }
        
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(0.5); }
            50% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            100% { opacity: 0; transform: translate(-50%, -50%) scale(0.5); }
        }
        
        .game-stats {
            background: rgba(255,255,255,0.9);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .level-selector {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body data-theme="light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-success fixed-top">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="../index.php">
                <i class="fas fa-leaf me-2"></i>EcoEdu
            </a>
            
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="../dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="../games.php">
                            <i class="fas fa-gamepad me-1"></i>Games
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-mdb-toggle="dropdown">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($user['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5 pt-4">
        <!-- Header -->
        <div class="text-center mb-4">
            <h1 class="display-4 text-success mb-3">
                <i class="fas fa-recycle me-3"></i>Recycle Sorter
            </h1>
            <p class="lead text-muted">Drag items to the correct recycling bins!</p>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Level Selection -->
        <div class="level-selector" id="level-selector">
            <h4 class="text-center mb-3"><i class="fas fa-layer-group me-2"></i>Choose Difficulty Level</h4>
            <div class="row justify-content-center">
                <?php foreach ($game_levels as $level_num => $level_info): ?>
                <div class="col-md-4 mb-2">
                    <button class="btn btn-outline-success w-100 level-btn" onclick="selectLevel(<?php echo $level_num; ?>)" data-level="<?php echo $level_num; ?>">
                        <i class="fas fa-<?php echo $level_num == 1 ? 'seedling' : ($level_num == 2 ? 'tree' : 'crown'); ?> me-2"></i>
                        Level <?php echo $level_num; ?>: <?php echo $level_info['name']; ?>
                        <br><small><?php echo $level_info['time']; ?>s • <?php echo $level_info['items_count']; ?> items</small>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Enhanced Game Stats -->
        <div class="game-stats" id="game-stats" style="display: none;">
            <div class="row">
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="score-display" id="score">0</div>
                        <small><i class="fas fa-star me-1"></i>Score</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="timer-display" id="timer">60</div>
                        <small><i class="fas fa-clock me-1"></i>Time Left</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="level-display" id="current-level">1</div>
                        <small><i class="fas fa-layer-group me-1"></i>Level</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="streak-display" id="streak">0</div>
                        <small><i class="fas fa-fire me-1"></i>Streak</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="text-success" style="font-size: 1.5rem;" id="correct">0</div>
                        <small><i class="fas fa-check me-1"></i>Correct</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <div class="text-danger" style="font-size: 1.5rem;" id="wrong">0</div>
                        <small><i class="fas fa-times me-1"></i>Wrong</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Game Area -->
        <div class="game-container">
            <!-- Recycling Bins -->
            <div class="row justify-content-center mb-4">
                <div class="col-auto">
                    <div class="recycle-bin bin-plastic" data-category="plastic" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <i class="fas fa-bottle-water fa-3x text-primary mb-2"></i>
                        <strong>Plastic</strong>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="recycle-bin bin-metal" data-category="metal" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <i class="fas fa-can-food fa-3x text-secondary mb-2"></i>
                        <strong>Metal</strong>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="recycle-bin bin-glass" data-category="glass" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <i class="fas fa-wine-bottle fa-3x text-success mb-2"></i>
                        <strong>Glass</strong>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="recycle-bin bin-paper" data-category="paper" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <i class="fas fa-newspaper fa-3x text-warning mb-2"></i>
                        <strong>Paper</strong>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="recycle-bin bin-organic" data-category="organic" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <i class="fas fa-apple-alt fa-3x" style="color: #fd7e14;" mb-2></i>
                        <strong>Organic</strong>
                    </div>
                </div>
            </div>

            <!-- Game Area for Items -->
            <div class="game-area" id="items-container" style="display: none;">
                <!-- Items will be spawned here by JavaScript -->
            </div>

            <!-- Game Controls -->
            <div class="text-center mt-4" id="game-controls" style="display: none;">
                <button class="btn btn-secondary btn-lg me-3" onclick="resetGame()" id="reset-btn">
                    <i class="fas fa-redo me-2"></i>Reset Game
                </button>
                <button class="btn btn-outline-secondary btn-lg" onclick="backToLevelSelect()" id="back-btn">
                    <i class="fas fa-arrow-left me-2"></i>Change Level
                </button>
            </div>
        </div>

        <!-- High Scores -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Your High Scores</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($high_scores)): ?>
                        <p class="text-muted text-center">No scores yet! Play the game to set your first record.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Score</th>
                                        <th>Accuracy</th>
                                        <th>Points Earned</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($high_scores as $index => $score): ?>
                                    <?php $data = json_decode($score['game_data'], true); ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo $score['score']; ?></strong></td>
                                        <td><?php echo number_format($data['accuracy'] ?? 0, 1); ?>%</td>
                                        <td><span class="text-success">+<?php echo $score['points_earned']; ?></span></td>
                                        <td><?php echo date('M j, Y', strtotime($score['played_at'])); ?></td>
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

    <!-- Hidden form for enhanced score submission -->
    <form id="score-form" method="POST" style="display: none;">
        <input type="hidden" name="action" value="save_score">
        <input type="hidden" name="score" id="final-score">
        <input type="hidden" name="correct_sorts" id="final-correct">
        <input type="hidden" name="wrong_sorts" id="final-wrong">
        <input type="hidden" name="level" id="final-level">
        <input type="hidden" name="streak_bonus" id="final-streak-bonus">
        <input type="hidden" name="time_bonus" id="final-time-bonus">
        <input type="hidden" name="total_items" id="final-total">
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        const items = <?php echo json_encode($items); ?>;
        const gameLevels = <?php echo json_encode($game_levels); ?>;
        
        // Enhanced game variables
        let gameActive = false;
        let score = 0;
        let correctSorts = 0;
        let wrongSorts = 0;
        let totalItems = 0;
        let timeLeft = 60;
        let currentLevel = 1;
        let streak = 0;
        let maxStreak = 0;
        let streakBonus = 0;
        let timeBonus = 0;
        let gameTimer;
        let spawnTimer;
        let currentItems = [];
        let spawnRate = 3000;
        let lastSortTime = 0;

        // Level selection function
        function selectLevel(level) {
            currentLevel = level;
            const levelConfig = gameLevels[level];
            timeLeft = levelConfig.time;
            spawnRate = levelConfig.spawn_rate;
            
            // Update UI
            document.getElementById('level-selector').style.display = 'none';
            document.getElementById('game-stats').style.display = 'block';
            document.getElementById('items-container').style.display = 'block';
            document.getElementById('game-controls').style.display = 'block';
            document.getElementById('current-level').textContent = level;
            document.getElementById('timer').textContent = timeLeft;
            
            // Highlight selected level
            document.querySelectorAll('.level-btn').forEach(btn => {
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-success');
            });
            document.querySelector(`[data-level="${level}"]`).classList.remove('btn-outline-success');
            document.querySelector(`[data-level="${level}"]`).classList.add('btn-success');
            
            startGame();
        }

        function startGame() {
            gameActive = true;
            score = 0;
            correctSorts = 0;
            wrongSorts = 0;
            totalItems = 0;
            streak = 0;
            streakBonus = 0;
            timeBonus = 0;
            lastSortTime = Date.now();
            
            // Clear any existing items
            document.getElementById('items-container').innerHTML = '';
            document.getElementById('reset-btn').disabled = false;
            
            updateDisplay();
            spawnItem();
            
            gameTimer = setInterval(() => {
                timeLeft--;
                document.getElementById('timer').textContent = timeLeft;
                
                if (timeLeft <= 0) {
                    endGame();
                }
            }, 1000);
        }

        function resetGame() {
            gameActive = false;
            clearInterval(gameTimer);
            clearTimeout(spawnTimer);
            
            // Reset all game variables
            score = 0;
            correctSorts = 0;
            wrongSorts = 0;
            totalItems = 0;
            streak = 0;
            streakBonus = 0;
            timeBonus = 0;
            timeLeft = gameLevels[currentLevel].time;
            lastSortTime = Date.now();
            
            // Clear items
            document.getElementById('items-container').innerHTML = '';
            
            updateDisplay();
            
            // Restart the game
            startGame();
        }
        
        function backToLevelSelect() {
            gameActive = false;
            clearInterval(gameTimer);
            clearTimeout(spawnTimer);
            
            // Hide game elements
            document.getElementById('game-stats').style.display = 'none';
            document.getElementById('items-container').style.display = 'none';
            document.getElementById('game-controls').style.display = 'none';
            
            // Show level selector
            document.getElementById('level-selector').style.display = 'block';
            
            // Clear items
            document.getElementById('items-container').innerHTML = '';
            
            // Reset variables
            score = 0;
            correctSorts = 0;
            wrongSorts = 0;
            totalItems = 0;
            streak = 0;
            streakBonus = 0;
            timeBonus = 0;
        }

        function spawnItem() {
            if (!gameActive) return;
            
            // Filter items by current level
            const levelItems = items.filter(item => item.level <= currentLevel);
            const randomItem = levelItems[Math.floor(Math.random() * levelItems.length)];
            
            // Create item at random position
            const itemElement = createItemElement(randomItem);
            const gameArea = document.querySelector('.game-area');
            const maxX = gameArea.offsetWidth - 120;
            const maxY = gameArea.offsetHeight - 120;
            
            itemElement.style.left = Math.random() * maxX + 'px';
            itemElement.style.top = Math.random() * maxY + 'px';
            
            gameArea.appendChild(itemElement);
            
            // Auto-remove item after 8 seconds if not sorted
            setTimeout(() => {
                if (itemElement.parentNode) {
                    itemElement.remove();
                    // Penalty for missed items
                    wrongSorts++;
                    streak = 0;
                    updateDisplay();
                    showFeedback(false, 0, "Missed!");
                }
            }, 8000);
            
            // Schedule next spawn
            spawnTimer = setTimeout(() => {
                if (gameActive) spawnItem();
            }, spawnRate);
        }

        function createItemElement(item) {
            const col = document.createElement('div');
            col.className = 'col-auto';
            
            const itemDiv = document.createElement('div');
            itemDiv.className = 'item-card';
            itemDiv.draggable = true;
            itemDiv.dataset.category = item.category;
            itemDiv.dataset.points = item.points;
            itemDiv.ondragstart = drag;
            
            itemDiv.innerHTML = `
                <div class="item-emoji">${item.image}</div>
                <small>${item.name}</small>
            `;
            
            col.appendChild(itemDiv);
            return col;
        }

        function allowDrop(ev) {
            ev.preventDefault();
            ev.target.closest('.recycle-bin').classList.add('drag-over');
        }

        function drag(ev) {
            ev.dataTransfer.setData("text", ev.target.dataset.category);
            ev.dataTransfer.setData("points", ev.target.dataset.points);
            ev.target.classList.add('dragging');
        }

        function drop(ev) {
            ev.preventDefault();
            const itemCategory = ev.dataTransfer.getData("text");
            const itemPoints = parseInt(ev.dataTransfer.getData("points"));
            const binCategory = ev.target.closest('.recycle-bin').dataset.category;
            
            ev.target.closest('.recycle-bin').classList.remove('drag-over');
            
            totalItems++;
            const currentTime = Date.now();
            const timeSinceLastSort = currentTime - lastSortTime;
            
            if (itemCategory === binCategory) {
                // Correct sort
                correctSorts++;
                let pointsEarned = 10; // Base points per your roadmap
                
                // Streak system
                streak++;
                if (streak > maxStreak) maxStreak = streak;
                
                // Streak bonus (every 3 correct in a row)
                if (streak >= 3 && streak % 3 === 0) {
                    const bonus = streak * 2;
                    streakBonus += bonus;
                    pointsEarned += bonus;
                    showFeedback(true, pointsEarned, `Streak Bonus! +${bonus}`);
                } else {
                    showFeedback(true, pointsEarned, "Correct!");
                }
                
                // Quick response bonus (under 2 seconds)
                if (timeSinceLastSort < 2000) {
                    const quickBonus = 5;
                    timeBonus += quickBonus;
                    pointsEarned += quickBonus;
                    showFeedback(true, pointsEarned, "Quick Bonus! +5");
                }
                
                score += pointsEarned;
            } else {
                // Wrong sort - penalty system per your roadmap
                wrongSorts++;
                streak = 0; // Reset streak
                const penalty = 5;
                score = Math.max(0, score - penalty); // Don't go below 0
                showFeedback(false, -penalty, "Wrong! -5 points");
            }
            
            lastSortTime = currentTime;
            
            // Remove the dragged item
            const draggedElement = document.querySelector('.dragging');
            if (draggedElement) {
                draggedElement.closest('.col-auto').remove();
            }
            
            updateDisplay();
        }

        function showFeedback(correct, points, message = '') {
            // Enhanced visual feedback system
            const feedbackDiv = document.createElement('div');
            feedbackDiv.className = 'feedback-popup';
            feedbackDiv.style.background = correct ? 'rgba(40, 167, 69, 0.9)' : 'rgba(220, 53, 69, 0.9)';
            feedbackDiv.innerHTML = `
                <i class="fas fa-${correct ? 'check' : 'times'} me-2"></i>
                ${message || (correct ? `+${points} points!` : 'Wrong bin!')}
            `;
            
            document.querySelector('.game-container').appendChild(feedbackDiv);
            
            // Remove feedback after animation
            setTimeout(() => {
                feedbackDiv.remove();
            }, 2000);
        }

        function updateDisplay() {
            document.getElementById('score').textContent = score;
            document.getElementById('correct').textContent = correctSorts;
            document.getElementById('wrong').textContent = wrongSorts;
            document.getElementById('streak').textContent = streak;
        }

        function endGame() {
            gameActive = false;
            clearInterval(gameTimer);
            clearTimeout(spawnTimer);
            
            // Calculate final bonuses
            const finalScore = score + streakBonus + timeBonus;
            
            // Fill form data
            document.getElementById('final-score').value = score;
            document.getElementById('final-correct').value = correctSorts;
            document.getElementById('final-wrong').value = wrongSorts;
            document.getElementById('final-total').value = totalItems;
            document.getElementById('final-level').value = currentLevel;
            document.getElementById('final-streak-bonus').value = streakBonus;
            document.getElementById('final-time-bonus').value = timeBonus;
            
            // Enhanced game over display
            const accuracy = totalItems > 0 ? ((correctSorts / totalItems) * 100).toFixed(1) : 0;
            const gameOverMessage = `
🎮 GAME OVER! 🎮

📊 Final Stats:
• Score: ${finalScore}
• Level: ${currentLevel}
• Accuracy: ${accuracy}%
• Correct: ${correctSorts}
• Wrong: ${wrongSorts}
• Max Streak: ${maxStreak}
• Streak Bonus: +${streakBonus}
• Time Bonus: +${timeBonus}

🏆 Total EcoPoints Earned: ${finalScore + (currentLevel * 5)}
            `;
            
            alert(gameOverMessage);
            document.getElementById('score-form').submit();
        }

        // Remove drag-over class when dragging leaves
        document.addEventListener('dragleave', (e) => {
            if (e.target.classList.contains('recycle-bin')) {
                e.target.classList.remove('drag-over');
            }
        });

        document.addEventListener('dragend', (e) => {
            e.target.classList.remove('dragging');
        });
    </script>
</body>
</html>
