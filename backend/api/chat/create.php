<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Chat.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$user = AuthMiddleware::verify();

$data = json_decode(file_get_contents('php://input'), true);
$title = isset($data['title']) ? $data['title'] : 'New Chat';
$model = isset($data['model']) ? $data['model'] : 'GPT-4';

$chat = new Chat();
$newChat = $chat->create($user['user_id'], $title, $model);

if ($newChat) {
    Response::success($newChat, 'Chat created');
} else {
    Response::error('Failed to create chat');
}