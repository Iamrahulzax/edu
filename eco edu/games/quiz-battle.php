<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
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

// Handle quiz submission
$quiz_result = null;
if ($_POST && isset($_POST['submit_quiz'])) {
    $answers = $_POST['answers'] ?? [];
    $time_taken = (int)$_POST['time_taken'];
    $quiz_questions = json_decode($_POST['quiz_data'], true);
    
    $correct_answers = 0;
    $total_questions = count($quiz_questions);
    
    foreach ($quiz_questions as $index => $question) {
        if (isset($answers[$index]) && $answers[$index] == $question['correct_answer']) {
            $correct_answers++;
        }
    }
    
    $score = ($correct_answers / $total_questions) * 100;
    $points_earned = $correct_answers * 10; // 10 points per correct answer
    
    // Time bonus (extra points for quick completion)
    if ($time_taken < 60) { // Under 1 minute
        $points_earned += 20;
    } elseif ($time_taken < 120) { // Under 2 minutes
        $points_earned += 10;
    }
    
    // Save game result
    try {
        $stmt = $pdo->prepare("INSERT INTO game_results (user_id, game_type, score, points_earned, time_taken, game_data) VALUES (?, 'quiz_battle', ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $score, $points_earned, $time_taken, json_encode(['correct' => $correct_answers, 'total' => $total_questions])]);
        
        // Award points to user
        addEcoPoints($user['id'], $points_earned, "Quiz Battle - Score: {$score}%");
        
        $quiz_result = [
            'score' => $score,
            'correct' => $correct_answers,
            'total' => $total_questions,
            'points' => $points_earned,
            'time' => $time_taken
        ];
        
    } catch (Exception $e) {
        $error_message = "Failed to save quiz result: " . $e->getMessage();
    }
}

// Generate quiz questions
$quiz_questions = [
    [
        'question' => 'What percentage of Earth\'s water is freshwater?',
        'options' => ['97%', '3%', '10%', '25%'],
        'correct_answer' => 1
    ],
    [
        'question' => 'Which gas is primarily responsible for global warming?',
        'options' => ['Oxygen', 'Nitrogen', 'Carbon Dioxide', 'Hydrogen'],
        'correct_answer' => 2
    ],
    [
        'question' => 'How long does it take for a plastic bottle to decompose?',
        'options' => ['10 years', '50 years', '100 years', '450+ years'],
        'correct_answer' => 3
    ],
    [
        'question' => 'What is the most abundant greenhouse gas in Earth\'s atmosphere?',
        'options' => ['Carbon Dioxide', 'Methane', 'Water Vapor', 'Nitrous Oxide'],
        'correct_answer' => 2
    ],
    [
        'question' => 'Which renewable energy source is the fastest growing?',
        'options' => ['Solar', 'Wind', 'Hydroelectric', 'Geothermal'],
        'correct_answer' => 0
    ],
    [
        'question' => 'What percentage of the world\'s oxygen is produced by the ocean?',
        'options' => ['20%', '50%', '70%', '90%'],
        'correct_answer' => 2
    ],
    [
        'question' => 'Which country produces the most renewable energy?',
        'options' => ['USA', 'Germany', 'China', 'Japan'],
        'correct_answer' => 2
    ],
    [
        'question' => 'How many trees does it take to produce one ton of paper?',
        'options' => ['5 trees', '12 trees', '17 trees', '24 trees'],
        'correct_answer' => 2
    ],
    [
        'question' => 'What is the main cause of deforestation?',
        'options' => ['Urban development', 'Agriculture', 'Mining', 'Natural disasters'],
        'correct_answer' => 1
    ],
    [
        'question' => 'Which appliance uses the most energy in a typical home?',
        'options' => ['Refrigerator', 'Air Conditioner', 'Water Heater', 'Television'],
        'correct_answer' => 1
    ]
];

// Shuffle questions for variety
shuffle($quiz_questions);
$quiz_questions = array_slice($quiz_questions, 0, 5); // Take only 5 questions
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eco Quiz Battle - EcoEdu</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .quiz-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .quiz-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            font-size: 1.5rem;
            font-weight: bold;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(255,107,107,0.3);
        }
        
        .question-counter {
            background: linear-gradient(45deg, #4834d4, #686de0);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .option-btn {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 15px 20px;
            margin: 10px 0;
            transition: all 0.3s ease;
            cursor: pointer;
            width: 100%;
            text-align: left;
        }
        
        .option-btn:hover {
            background: #e3f2fd;
            border-color: #2196f3;
            transform: translateX(10px);
        }
        
        .option-btn.selected {
            background: linear-gradient(45deg, #4caf50, #45a049);
            color: white;
            border-color: #4caf50;
        }
        
        .progress-ring {
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        
        .progress-ring-circle {
            stroke: #e9ecef;
            stroke-width: 8;
            fill: transparent;
            r: 52;
            cx: 60;
            cy: 60;
        }
        
        .progress-ring-meter {
            stroke: #4caf50;
            stroke-width: 8;
            stroke-linecap: round;
            fill: transparent;
            r: 52;
            cx: 60;
            cy: 60;
            stroke-dasharray: 326.73;
            stroke-dashoffset: 326.73;
            transition: stroke-dashoffset 0.5s ease;
        }
        
        .result-card {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(76,175,80,0.3);
        }
        
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #f39c12;
            animation: confetti-fall 3s linear infinite;
        }
        
        @keyframes confetti-fall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="quiz-container">
        <!-- Timer -->
        <div class="timer" id="timer">
            <i class="fas fa-clock me-2"></i>
            <span id="time-display">2:00</span>
        </div>

        <div class="container">
            <?php if ($quiz_result): ?>
            <!-- Quiz Results -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="result-card">
                        <div class="mb-4">
                            <i class="fas fa-trophy fa-4x mb-3"></i>
                            <h2>Quiz Complete!</h2>
                        </div>
                        
                        <div class="row text-center mb-4">
                            <div class="col-md-3">
                                <div class="h3"><?php echo $quiz_result['score']; ?>%</div>
                                <small>Final Score</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h3"><?php echo $quiz_result['correct']; ?>/<?php echo $quiz_result['total']; ?></div>
                                <small>Correct Answers</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h3">+<?php echo $quiz_result['points']; ?></div>
                                <small>EcoPoints Earned</small>
                            </div>
                            <div class="col-md-3">
                                <div class="h3"><?php echo gmdate("i:s", $quiz_result['time']); ?></div>
                                <small>Time Taken</small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <?php if ($quiz_result['score'] >= 80): ?>
                                <h4><i class="fas fa-star me-2"></i>Excellent Work!</h4>
                                <p>You're an eco-expert! Keep up the great work!</p>
                            <?php elseif ($quiz_result['score'] >= 60): ?>
                                <h4><i class="fas fa-thumbs-up me-2"></i>Good Job!</h4>
                                <p>You're on the right track! Keep learning!</p>
                            <?php else: ?>
                                <h4><i class="fas fa-book me-2"></i>Keep Learning!</h4>
                                <p>Every expert was once a beginner. Try again!</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex gap-3 justify-content-center">
                            <a href="quiz-battle.php" class="btn btn-light btn-lg">
                                <i class="fas fa-redo me-2"></i>Play Again
                            </a>
                            <a href="../games.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-gamepad me-2"></i>More Games
                            </a>
                            <a href="../leaderboard.php" class="btn btn-warning btn-lg">
                                <i class="fas fa-medal me-2"></i>Leaderboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Quiz Game -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Header -->
                    <div class="quiz-card text-center mb-4">
                        <h2><i class="fas fa-brain me-3"></i>Eco Quiz Battle</h2>
                        <p class="lead">Test your environmental knowledge! You have 2 minutes to answer 5 questions.</p>
                        <div class="question-counter">
                            Question <span id="current-question">1</span> of <?php echo count($quiz_questions); ?>
                        </div>
                    </div>

                    <!-- Quiz Form -->
                    <form id="quiz-form" method="POST">
                        <input type="hidden" name="submit_quiz" value="1">
                        <input type="hidden" name="time_taken" id="time-taken" value="0">
                        <input type="hidden" name="quiz_data" value="<?php echo htmlspecialchars(json_encode($quiz_questions)); ?>">
                        
                        <?php foreach ($quiz_questions as $index => $question): ?>
                        <div class="quiz-card question-slide" id="question-<?php echo $index; ?>" style="<?php echo $index > 0 ? 'display: none;' : ''; ?>">
                            <h4 class="mb-4"><?php echo htmlspecialchars($question['question']); ?></h4>
                            
                            <div class="options">
                                <?php foreach ($question['options'] as $opt_index => $option): ?>
                                <div class="option-btn" onclick="selectOption(<?php echo $index; ?>, <?php echo $opt_index; ?>)">
                                    <input type="radio" name="answers[<?php echo $index; ?>]" value="<?php echo $opt_index; ?>" style="display: none;">
                                    <i class="fas fa-circle me-3"></i>
                                    <?php echo htmlspecialchars($option); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-center mt-4">
                                <?php if ($index > 0): ?>
                                <button type="button" class="btn btn-outline-secondary me-3" onclick="previousQuestion()">
                                    <i class="fas fa-arrow-left me-2"></i>Previous
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($index < count($quiz_questions) - 1): ?>
                                <button type="button" class="btn btn-primary" onclick="nextQuestion()" id="next-btn-<?php echo $index; ?>" disabled>
                                    Next<i class="fas fa-arrow-right ms-2"></i>
                                </button>
                                <?php else: ?>
                                <button type="submit" class="btn btn-success btn-lg pulse" id="submit-btn" disabled>
                                    <i class="fas fa-check me-2"></i>Submit Quiz
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    
    <script>
        let currentQuestion = 0;
        let timeLeft = 120; // 2 minutes
        let timerInterval;
        let startTime = Date.now();
        
        // Start timer
        function startTimer() {
            timerInterval = setInterval(function() {
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                document.getElementById('time-display').textContent = 
                    minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    document.getElementById('quiz-form').submit();
                }
                
                // Change timer color when time is running out
                const timer = document.getElementById('timer');
                if (timeLeft <= 30) {
                    timer.style.background = 'linear-gradient(45deg, #e74c3c, #c0392b)';
                    timer.classList.add('pulse');
                }
            }, 1000);
        }
        
        function selectOption(questionIndex, optionIndex) {
            // Remove selection from all options in this question
            const options = document.querySelectorAll(`#question-${questionIndex} .option-btn`);
            options.forEach(opt => opt.classList.remove('selected'));
            
            // Select clicked option
            const selectedOption = options[optionIndex];
            selectedOption.classList.add('selected');
            selectedOption.querySelector('input[type="radio"]').checked = true;
            
            // Enable next/submit button
            const nextBtn = document.getElementById(`next-btn-${questionIndex}`);
            const submitBtn = document.getElementById('submit-btn');
            
            if (nextBtn) {
                nextBtn.disabled = false;
            }
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
        
        function nextQuestion() {
            if (currentQuestion < <?php echo count($quiz_questions) - 1; ?>) {
                document.getElementById(`question-${currentQuestion}`).style.display = 'none';
                currentQuestion++;
                document.getElementById(`question-${currentQuestion}`).style.display = 'block';
                document.getElementById('current-question').textContent = currentQuestion + 1;
                
                // Scroll to top
                window.scrollTo(0, 0);
            }
        }
        
        function previousQuestion() {
            if (currentQuestion > 0) {
                document.getElementById(`question-${currentQuestion}`).style.display = 'none';
                currentQuestion--;
                document.getElementById(`question-${currentQuestion}`).style.display = 'block';
                document.getElementById('current-question').textContent = currentQuestion + 1;
                
                // Scroll to top
                window.scrollTo(0, 0);
            }
        }
        
        // Handle form submission
        document.getElementById('quiz-form').addEventListener('submit', function() {
            const timeTaken = Math.floor((Date.now() - startTime) / 1000);
            document.getElementById('time-taken').value = timeTaken;
            clearInterval(timerInterval);
        });
        
        // Start the quiz
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!$quiz_result): ?>
            startTimer();
            <?php else: ?>
            // Create confetti effect for good scores
            <?php if ($quiz_result['score'] >= 80): ?>
            createConfetti();
            <?php endif; ?>
            <?php endif; ?>
        });
        
        function createConfetti() {
            const colors = ['#f39c12', '#e74c3c', '#9b59b6', '#3498db', '#2ecc71'];
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.animationDelay = Math.random() * 3 + 's';
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => {
                        confetti.remove();
                    }, 3000);
                }, i * 100);
            }
        }
    </script>
</body>
</html>
