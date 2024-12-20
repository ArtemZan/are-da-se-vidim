<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['eventId']) || !isset($data['startDate']) || 
        !isset($data['endDate']) || !isset($data['preferenceScore'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO date_preferences 
            (event_id, user_id, start_date, end_date, preference_score) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['eventId'],
            $_SESSION['user']['id'],
            $data['startDate'],
            $data['endDate'],
            $data['preferenceScore']
        ]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} 