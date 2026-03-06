<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Chat.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user = AuthMiddleware::verify();

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

try {
    $chatModel = new Chat();
    
   
    $chats = $chatModel->getAllByUser($user['user_id'], $limit);
    
   
    $today = [];
    $yesterday = [];
    $older = [];
    
    $todayDate = date('Y-m-d');
    $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
    
    foreach ($chats as $c) {
        $chatDate = date('Y-m-d', strtotime($c['updated_at']));
        
        if ($chatDate === $todayDate) {
            $today[] = $c;
        } elseif ($chatDate === $yesterdayDate) {
            $yesterday[] = $c;
        } else {
            $older[] = $c;
        }
    }
    
    Response::success([
        'today' => $today,
        'yesterday' => $yesterday,
        'older' => $older
    ]);
    
} catch (Exception $e) {
    error_log('History Error: ' . $e->getMessage());
    Response::error('Failed to load chat history', 500);
}