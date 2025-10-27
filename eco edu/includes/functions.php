<?php
// Essential functions for EcoEdu platform

// Sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Submit quiz attempt with automatic scoring and point handling
function submitQuizAttempt($user_id, $quiz_id, $raw_answers) {
    global $pdo;

    $questions = getQuizQuestions($quiz_id);
    if (empty($questions)) {
        return [
            'success' => false,
            'message' => 'Quiz has no questions configured.',
        ];
    }

    $quiz = getQuizById($quiz_id);
    if (!$quiz) {
        return [
            'success' => false,
            'message' => 'Quiz not found or inactive.',
        ];
    }

    $score = 0;
    $total_questions = count($questions);
    $answer_rows = [];

    foreach ($questions as $question) {
        $question_id = $question['id'];
        $user_answer = isset($raw_answers[$question_id]) ? trim($raw_answers[$question_id]) : '';

        if ($user_answer === '') {
            continue;
        }

        $is_correct = strtolower($user_answer) === strtolower($question['correct_answer']);
        if ($is_correct) {
            $score++;
        }

        $answer_rows[] = [
            'question_id'    => $question_id,
            'selected_answer'=> $user_answer,
            'is_correct'     => $is_correct,
        ];
    }

    $attempt_id = saveQuizAttempt($user_id, $quiz_id, $score, $total_questions, $answer_rows);
    if (!$attempt_id) {
        return [
            'success' => false,
            'message' => 'Failed to record quiz attempt.',
        ];
    }

    $percentage = $total_questions > 0 ? ($score / $total_questions) * 100 : 0;
    $passed = $percentage >= $quiz['pass_percentage'];
    $points_earned = ($passed ? $quiz['points_per_question'] * $total_questions : $score * $quiz['points_per_question']);

    try {
        $stmt = $pdo->prepare("UPDATE quiz_attempts SET is_passed = ?, points_earned = ?, completed_at = NOW() WHERE id = ?");
        $stmt->execute([$passed ? 1 : 0, $points_earned, $attempt_id]);
    } catch (Exception $e) {
        // Ignore update failure; main attempt already saved
    }

    return [
        'success'         => true,
        'score'           => $percentage,
        'correct_answers' => $score,
        'total_questions' => $total_questions,
        'is_passed'       => $passed,
        'points_earned'   => $points_earned,
        'message'         => $passed ? 'Great job! You passed the quiz.' : 'Quiz completed. Try again to improve your score!',
    ];
}

// Get user by ID
function getUserById($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// Get user badges
function getUserBadges($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, ub.earned_at 
            FROM user_badges ub 
            JOIN badges b ON ub.badge_id = b.id 
            WHERE ub.user_id = ? 
            ORDER BY ub.earned_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get quizzes
function getQuizzes($category_id = null, $limit = null) {
    global $pdo;
    try {
        $sql = "SELECT q.*, c.name as category_name FROM quizzes q 
                LEFT JOIN categories c ON q.category_id = c.id 
                WHERE q.is_active = 1";
        
        $params = [];
        if ($category_id) {
            $sql .= " AND q.category_id = ?";
            $params[] = $category_id;
        }
        
        $sql .= " ORDER BY q.created_at DESC";
        
        if ($limit) {
            // MySQL does not allow binding LIMIT directly when emulated prepares are disabled
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get challenges
function getChallenges($category_id = null, $limit = null) {
    global $pdo;
    try {
        $sql = "SELECT c.*, cat.name as category_name FROM challenges c 
                LEFT JOIN categories cat ON c.category_id = cat.id 
                WHERE c.is_active = 1";
        
        $params = [];
        if ($category_id) {
            $sql .= " AND c.category_id = ?";
            $params[] = $category_id;
        }
        
        $sql .= " ORDER BY c.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get categories
function getCategories() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get global leaderboard
function getGlobalLeaderboard($limit = 50) {
    global $pdo;
    try {
        $badgeJoin = "LEFT JOIN (SELECT user_id, COUNT(*) AS total_badges FROM user_badges GROUP BY user_id) AS ub ON ub.user_id = u.id";
        $sql = "SELECT u.*, l.name AS level_name, l.icon AS level_icon, COALESCE(ub.total_badges, 0) AS total_badges
                FROM users u
                LEFT JOIN levels l ON u.level_id = l.id
                $badgeJoin
                WHERE u.is_active = 1 AND u.role = 'student'
                ORDER BY u.eco_points DESC, u.updated_at DESC
                LIMIT " . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get school leaderboard
function getSchoolLeaderboard($limit = 20) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT 
                school_name as name,
                COUNT(*) as student_count,
                SUM(eco_points) as total_points,
                AVG(eco_points) as avg_points_per_student
             FROM users 
             WHERE role = 'student' 
                AND is_active = 1 
                AND school_name IS NOT NULL 
                AND school_name != ''
             GROUP BY school_name 
             ORDER BY total_points DESC 
             LIMIT " . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get school-specific leaderboard (students from same school)
function getSchoolStudentLeaderboard($school_name, $limit = 20) {
    global $pdo;
    try {
        $badgeJoin = "LEFT JOIN (SELECT user_id, COUNT(*) AS total_badges FROM user_badges GROUP BY user_id) AS ub ON ub.user_id = u.id";
        $sql = "SELECT u.*, l.name AS level_name, l.icon AS level_icon, COALESCE(ub.total_badges, 0) AS total_badges
                FROM users u
                LEFT JOIN levels l ON u.level_id = l.id
                $badgeJoin
                WHERE u.is_active = 1
                    AND u.role = 'student'
                    AND u.school_name = ?
                ORDER BY u.eco_points DESC, u.updated_at DESC
                LIMIT " . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$school_name]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getUserRankWindow($user_id, $range = 3) {
    global $pdo;
    try {
        $user = getUserById($user_id);
        if (!$user) {
            return null;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = 'student'");
        $stmt->execute();
        $total_students = (int)$stmt->fetchColumn();
        if ($total_students === 0) {
            return [
                'rank' => 0,
                'total' => 0,
                'rows' => []
            ];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = 'student' AND eco_points > ?");
        $stmt->execute([(int)$user['eco_points']]);
        $rank = (int)$stmt->fetchColumn() + 1;

        $offset = max(0, $rank - 1 - $range);
        $limit = ($range * 2) + 1;

        $badgeJoin = "LEFT JOIN (SELECT user_id, COUNT(*) AS total_badges FROM user_badges GROUP BY user_id) AS ub ON ub.user_id = u.id";
        $sql = "SELECT u.*, l.name AS level_name, l.icon AS level_icon, COALESCE(ub.total_badges, 0) AS total_badges
                FROM users u
                LEFT JOIN levels l ON u.level_id = l.id
                $badgeJoin
                WHERE u.is_active = 1 AND u.role = 'student'
                ORDER BY u.eco_points DESC, u.updated_at DESC
                LIMIT " . (int)$offset . ", " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as $idx => &$row) {
            $row['computed_rank'] = $offset + $idx + 1;
            $row['tier'] = getTierForPoints((int)$row['eco_points']);
        }
        unset($row);

        return [
            'rank' => $rank,
            'total' => $total_students,
            'rows' => $rows,
        ];
    } catch (Exception $e) {
        return null;
    }
}

function getTierForPoints($points) {
    if ($points >= 2000) {
        return ['name' => 'Platinum', 'slug' => 'platinum'];
    }
    if ($points >= 1000) {
        return ['name' => 'Gold', 'slug' => 'gold'];
    }
    if ($points >= 500) {
        return ['name' => 'Silver', 'slug' => 'silver'];
    }
    if ($points >= 200) {
        return ['name' => 'Bronze', 'slug' => 'bronze'];
    }
    return ['name' => 'Explorer', 'slug' => 'explorer'];
}

function ensureTierTrackingTable() {
    global $pdo;
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_tier_history (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        tier_slug VARCHAR(20) NOT NULL,
        entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_tier (user_id, tier_slug),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $initialized = true;
}

function updateUserTierTracking($user_id, $tier_slug) {
    global $pdo;
    ensureTierTrackingTable();

    $stmt = $pdo->prepare("SELECT tier_slug, entered_at FROM user_tier_history WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if ($row && $row['tier_slug'] === $tier_slug) {
        return $row;
    }

    $stmt = $pdo->prepare("REPLACE INTO user_tier_history (user_id, tier_slug, entered_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $tier_slug]);

    return ['tier_slug' => $tier_slug, 'entered_at' => date('Y-m-d H:i:s')];
}

function awardTierBadgeIfEligible($user_id, $tier_slug, $entered_at) {
    global $pdo;
    $badgeMap = [
        'bronze' => ['name' => 'Bronze Champion', 'icon' => 'fas fa-award'],
        'silver' => ['name' => 'Silver Hero', 'icon' => 'fas fa-trophy'],
        'gold' => ['name' => 'Gold Legend', 'icon' => 'fas fa-crown'],
        'platinum' => ['name' => 'Platinum Guardian', 'icon' => 'fas fa-gem'],
    ];

    if (!isset($badgeMap[$tier_slug])) {
        return false;
    }

    $entered = strtotime($entered_at);
    if ($entered === false || (time() - $entered) < 7 * 24 * 60 * 60) {
        return false;
    }

    $badgeName = $badgeMap[$tier_slug]['name'];
    $badgeIcon = $badgeMap[$tier_slug]['icon'];

    $stmt = $pdo->prepare("SELECT id FROM badges WHERE name = ?");
    $stmt->execute([$badgeName]);
    $badge = $stmt->fetch();

    if (!$badge) {
        $stmt = $pdo->prepare("INSERT INTO badges (name, description, icon, badge_type, criteria_type, criteria_value, is_active) VALUES (?, ?, ?, 'achievement', 'quizzes', 0, 1)");
        $stmt->execute([$badgeName, 'Held ' . ucfirst($tier_slug) . ' tier for 7 days', $badgeIcon]);
        $badge_id = $pdo->lastInsertId();
    } else {
        $badge_id = $badge['id'];
    }

    $stmt = $pdo->prepare("SELECT id FROM user_badges WHERE user_id = ? AND badge_id = ?");
    $stmt->execute([$user_id, $badge_id]);
    if ($stmt->fetch()) {
        return false;
    }

    $stmt = $pdo->prepare("INSERT INTO user_badges (user_id, badge_id, earned_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $badge_id]);

    return true;
}

// Get recent achievements
function getRecentAchievements($limit = 10) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT ub.*, u.first_name, u.last_name, u.school_name, b.name as badge_name, b.icon as badge_icon
            FROM user_badges ub
            JOIN users u ON ub.user_id = u.id
            JOIN badges b ON ub.badge_id = b.id
            WHERE u.is_active = 1
            ORDER BY ub.earned_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Add eco points to user
function addEcoPoints($user_id, $points, $description = '') {
    global $pdo;
    try {
        // Update user points
        $stmt = $pdo->prepare("UPDATE users SET eco_points = eco_points + ? WHERE id = ?");
        $stmt->execute([$points, $user_id]);
        
        // Log the transaction
        $stmt = $pdo->prepare("
            INSERT INTO point_transactions (user_id, points, transaction_type, description) 
            VALUES (?, ?, 'earned', ?)
        ");
        $stmt->execute([$user_id, $points, $description]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Format time ago
function formatTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

// Check if user is admin
function isAdmin($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        return $user && $user['role'] === 'admin';
    } catch (Exception $e) {
        return false;
    }
}

// Get learning content
function getLearningContent($category_id = null, $limit = null) {
    global $pdo;
    try {
        $sql = "SELECT lc.*, c.name as category_name FROM learning_content lc 
                LEFT JOIN categories c ON lc.category_id = c.id 
                WHERE lc.is_published = 1";
        
        $params = [];
        if ($category_id) {
            $sql .= " AND lc.category_id = ?";
            $params[] = $category_id;
        }
        
        $sql .= " ORDER BY lc.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

// Send email (basic function)
function sendEmail($to, $subject, $message) {
    // Basic email function - can be enhanced with PHPMailer
    $headers = "From: noreply@ecoedu.com\r\n";
    $headers .= "Reply-To: noreply@ecoedu.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Login user
function loginUser($username_email, $password) {
    global $pdo;
    try {
        // Check if input is email or username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) AND is_active = 1");
        $stmt->execute([$username_email, $username_email]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Log activity
            logActivity($user['id'], 'login', 'User logged in');
            
            return [
                'success' => true,
                'user' => $user,
                'message' => 'Login successful'
            ];
        }
        
        return [
            'success' => false,
            'user' => null,
            'message' => 'Invalid email/username or password'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'user' => null,
            'message' => 'Login failed. Please try again.'
        ];
    }
}

// Register user
function registerUser($username, $email, $password, $first_name, $last_name, $school_name, $grade_level) {
    global $pdo;
    try {
        // Check if email already exists
        if (emailExists($email)) {
            return [
                'success' => false,
                'message' => 'Email address already exists'
            ];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, first_name, last_name, email, password, school_name, grade_level, role, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'student', NOW())
        ");
        
        $hashedPassword = hashPassword($password);
        
        $result = $stmt->execute([
            $username,
            $first_name,
            $last_name, 
            $email,
            $hashedPassword,
            $school_name,
            $grade_level
        ]);
        
        if ($result) {
            $user_id = $pdo->lastInsertId();
            
            // Log activity
            logActivity($user_id, 'register', 'User registered');
            
            return [
                'success' => true,
                'user_id' => $user_id,
                'message' => 'Registration successful'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Registration failed. Please try again.'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Registration failed: ' . $e->getMessage()
        ];
    }
}

// Check if email exists
function emailExists($email) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

// Logout user
function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    
    session_destroy();
    return true;
}

// Get user level
function getUserLevel($eco_points) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM levels 
            WHERE min_points <= ? 
            ORDER BY min_points DESC 
            LIMIT 1
        ");
        $stmt->execute([$eco_points]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return ['name' => 'Eco Newbie', 'icon' => 'fas fa-seedling', 'min_points' => 0];
    }
}

// Update user level
function updateUserLevel($user_id) {
    global $pdo;
    try {
        $user = getUserById($user_id);
        if (!$user) return false;
        
        $level = getUserLevel($user['eco_points']);
        if (!$level) return false;
        
        $stmt = $pdo->prepare("UPDATE users SET level_id = ? WHERE id = ?");
        return $stmt->execute([$level['id'], $user_id]);
    } catch (Exception $e) {
        return false;
    }
}

// Log user activity
function logActivity($user_id, $activity_type, $description) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_activity (user_id, activity_type, description) 
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$user_id, $activity_type, $description]);
    } catch (Exception $e) {
        return false;
    }
}

// Get quiz by ID
function getQuizById($quiz_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT q.*, c.name as category_name 
            FROM quizzes q 
            LEFT JOIN categories c ON q.category_id = c.id 
            WHERE q.id = ? AND q.is_active = 1
        ");
        $stmt->execute([$quiz_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// Get quiz questions
function getQuizQuestions($quiz_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM quiz_questions 
            WHERE quiz_id = ? 
            ORDER BY question_order, id
        ");
        $stmt->execute([$quiz_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get challenge by ID
function getChallengeById($challenge_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, cat.name as category_name 
            FROM challenges c 
            LEFT JOIN categories cat ON c.category_id = cat.id 
            WHERE c.id = ? AND c.is_active = 1
        ");
        $stmt->execute([$challenge_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// Get user progress for quiz
function getUserProgress($user_id, $quiz_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM user_quiz_progress 
            WHERE user_id = ? AND quiz_id = ?
        ");
        $stmt->execute([$user_id, $quiz_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// Save quiz attempt
function saveQuizAttempt($user_id, $quiz_id, $score, $total_questions, $answers) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Insert quiz attempt
        $stmt = $pdo->prepare("
            INSERT INTO quiz_attempts (user_id, quiz_id, score, total_questions, completed_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $quiz_id, $score, $total_questions]);
        $attempt_id = $pdo->lastInsertId();
        
        // Insert individual answers
        $stmt = $pdo->prepare("
            INSERT INTO quiz_answers (attempt_id, question_id, selected_answer, is_correct) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($answers as $answer) {
            $stmt->execute([
                $attempt_id,
                $answer['question_id'],
                $answer['selected_answer'],
                $answer['is_correct'] ? 1 : 0
            ]);
        }
        
        // Update user progress
        $percentage = ($score / $total_questions) * 100;
        $stmt = $pdo->prepare("
            INSERT INTO user_quiz_progress (user_id, quiz_id, highest_score, attempts, last_attempt) 
            VALUES (?, ?, ?, 1, NOW()) 
            ON DUPLICATE KEY UPDATE 
            highest_score = GREATEST(highest_score, ?),
            attempts = attempts + 1,
            last_attempt = NOW()
        ");
        $stmt->execute([$user_id, $quiz_id, $percentage, $percentage]);
        
        // Award points for quiz completion
        $points = max(10, $score * 5); // Minimum 10 points, 5 points per correct answer
        addEcoPoints($user_id, $points, "Completed quiz: $quiz_id");
        
        $pdo->commit();
        return $attempt_id;
    } catch (Exception $e) {
        $pdo->rollback();
        return false;
    }
}

// Get user's quiz attempts
function getUserQuizAttempts($user_id, $quiz_id = null) {
    global $pdo;
    try {
        $sql = "
            SELECT qa.*, q.title as quiz_title 
            FROM quiz_attempts qa 
            JOIN quizzes q ON qa.quiz_id = q.id 
            WHERE qa.user_id = ?
        ";
        $params = [$user_id];
        
        if ($quiz_id) {
            $sql .= " AND qa.quiz_id = ?";
            $params[] = $quiz_id;
        }
        
        $sql .= " ORDER BY qa.completed_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Check if user has completed challenge
function hasUserCompletedChallenge($user_id, $challenge_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM user_challenge_progress 
            WHERE user_id = ? AND challenge_id = ? AND is_completed = 1
        ");
        $stmt->execute([$user_id, $challenge_id]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

// Complete challenge
function completeChallenge($user_id, $challenge_id, $proof_text = '', $proof_image = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_challenge_progress (user_id, challenge_id, proof_text, proof_image, completed_at, is_completed) 
            VALUES (?, ?, ?, ?, NOW(), 1)
            ON DUPLICATE KEY UPDATE 
            proof_text = VALUES(proof_text),
            proof_image = VALUES(proof_image),
            completed_at = NOW(),
            is_completed = 1
        ");
        
        $result = $stmt->execute([$user_id, $challenge_id, $proof_text, $proof_image]);
        
        if ($result) {
            $points = 50;

            try {
                $stmt = $pdo->prepare("SELECT points_reward FROM challenges WHERE id = ?");
                $stmt->execute([$challenge_id]);
                $points_row = $stmt->fetch();
                if ($points_row && (int)$points_row['points_reward'] > 0) {
                    $points = (int)$points_row['points_reward'];
                }
            } catch (Exception $e) {
                // Ignore and fallback to default
            }

            $stmt = $pdo->prepare("UPDATE challenge_participation SET status = 'verified', points_earned = ?, verified_at = NOW() WHERE user_id = ? AND challenge_id = ?");
            $stmt->execute([$points, $user_id, $challenge_id]);

            addEcoPoints($user_id, $points, "Completed challenge: $challenge_id");
            logActivity($user_id, 'challenge_completed', "Completed challenge ID: $challenge_id");
        }
        
        return $result;
    } catch (Exception $e) {
        return false;
    }
}
?>
