<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user = getUserById($_SESSION['user_id']);
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid content ID']);
    exit;
}

$content_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT lc.*, c.name as category_name 
                          FROM learning_content lc 
                          LEFT JOIN categories c ON lc.category_id = c.id 
                          WHERE lc.id = ?");
    $stmt->execute([$content_id]);
    $content = $stmt->fetch();
    
    if ($content) {
        echo json_encode([
            'success' => true,
            'content' => $content
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Content not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
