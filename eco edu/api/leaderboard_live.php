<?php
header('Content-Type: application/json');
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

try {
    $user_id = (int)$_SESSION['user_id'];
    $user = getUserById($user_id);

    $personal_window = getUserRankWindow($user_id);
    $global = getGlobalLeaderboard(10);

    $response = [
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'eco_points' => (int)$user['eco_points'],
            'tier' => getTierForPoints((int)$user['eco_points']),
            'rank' => $personal_window ? $personal_window['rank'] : null,
            'total_students' => $personal_window ? $personal_window['total'] : null,
        ],
        'personal_window' => $personal_window,
        'global_top' => $global,
    ];

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
