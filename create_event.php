<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['eventName']) || !isset($data['timeRequired'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    $eventName = $data['eventName'];
    $timeRequired = $data['timeRequired'];
    
    // Generate a unique share link
    $shareLink = bin2hex(random_bytes(8));
    
    try {
        $stmt = $pdo->prepare("INSERT INTO events (event_name, time_required, share_link) VALUES (?, ?, ?)");
        $stmt->execute([$eventName, $timeRequired, $shareLink]);
        
        $response = [
            'success' => true,
            'shareLink' => $_SERVER['HTTP_HOST'] . '/view_event.php?id=' . $shareLink
        ];
        
        echo json_encode($response);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} 