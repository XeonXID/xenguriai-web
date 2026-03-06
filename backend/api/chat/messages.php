<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Chat.php';
require_once __DIR__ . '/../../models/Message.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user = AuthMiddleware::verify();

if (!isset($_GET['chat_id'])) {
    Response::error('Chat ID required');
}

$chatId = intval($_GET['chat_id']);


$chatModel = new Chat();
$chat = $chatModel->getById($chatId);

if (!$chat || $chat['user_id'] != $user['user_id']) {
    Response::error('Chat not found', 404);
}

$messageModel = new Message();
$messages = $messageModel->getByChat($chatId);

Response::success([
    'chat' => $chat,
    'messages' => $messages
]);