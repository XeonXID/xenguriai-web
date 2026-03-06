<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';
require_once '../../services/GroqService.php';
require_once '../../utils/jwt.php';

try {
   
    $token = JWT::getBearerToken();
    if (!$token) {
        throw new Exception("Token tidak ditemukan di header", 401);
    }

   
    $userData = JWT::decode($token);
    if (!$userData) {
        throw new Exception("Token tidak valid atau expired", 401);
    }

   
    $currentUserId = null;
    if (isset($userData['id'])) {
        $currentUserId = $userData['id'];
    } elseif (isset($userData['user_id'])) {
        $currentUserId = $userData['user_id'];
    }

    if (!$currentUserId) {
       
        error_log("DEBUG JWT DATA: " . json_encode($userData));
        throw new Exception("Data User ID tidak ditemukan dalam token", 400);
    }

   
    $chatId = isset($_GET['chat_id']) ? intval($_GET['chat_id']) : null;
    if (!$chatId) {
        throw new Exception("Parameter chat_id wajib diisi", 400);
    }

    $database = new Database();
    $db = $database->getConnection();

   
    $checkStmt = $db->prepare("SELECT id FROM chats WHERE id = ? AND user_id = ?");
    $checkStmt->execute([$chatId, $currentUserId]);
    
    if (!$checkStmt->fetch()) {
       
        throw new Exception("Chat tidak ditemukan atau kamu tidak punya akses ke chat ini", 403);
    }

   
    $groqService = new GroqService($chatId, $currentUserId, $db);
    $success = $groqService->deleteChat();

    if ($success) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Chat ID $chatId berhasil dihapus"
        ]);
    } else {
        throw new Exception("Gagal menghapus chat dari database", 500);
    }

} catch (Exception $e) {
   
    $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($statusCode);
    
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}