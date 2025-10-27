<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['challenge_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$challenge_id = (int)$input['challenge_id'];
$user_id = $_SESSION['user_id'];

try {
    // Check if challenge exists and is active
    $stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ? AND is_active = 1");
    $stmt->execute([$challenge_id]);
    $challenge = $stmt->fetch();
    
    if (!$challenge) {
        echo json_encode(['success' => false, 'message' => 'Challenge not found or inactive']);
        exit;
    }
    
    // Check if user already joined
    $stmt = $pdo->prepare("SELECT id FROM challenge_participation WHERE user_id = ? AND challenge_id = ?");
    $stmt->execute([$user_id, $challenge_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Already joined this challenge']);
        exit;
    }
    
    // Check if challenge has participant limit
    if ($challenge['max_participants'] > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM challenge_participation WHERE challenge_id = ?");
        $stmt->execute([$challenge_id]);
        $current_participants = $stmt->fetch()['count'];
        
        if ($current_participants >= $challenge['max_participants']) {
            echo json_encode(['success' => false, 'message' => 'Challenge is full']);
            exit;
        }
    }
    
    // Check if challenge is within date range
    if ($challenge['start_date'] && $challenge['start_date'] > date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'Challenge has not started yet']);
        exit;
    }
    
    if ($challenge['end_date'] && $challenge['end_date'] < date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'Challenge has ended']);
        exit;
    }
    
    // Join the challenge
    $stmt = $pdo->prepare("INSERT INTO challenge_participation (user_id, challenge_id, status) VALUES (?, ?, 'joined')");
    $stmt->execute([$user_id, $challenge_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Successfully joined challenge!',
        'challenge_id' => $challenge_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
