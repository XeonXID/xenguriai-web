<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once __DIR__ . '/../../models/User.php';
// JWT sengaja tidak dipanggil di sini sesuai request kamu
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Cek data sesuai field curl: username, email, password
if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
    Response::error('Username, email and password required');
}

$user = new User();
// Panggil fungsi register
$result = $user->register($data['username'], $data['email'], $data['password']);

if ($result['success']) {
    Response::success([
        'user' => $result['user']
    ], 'Registration successful');
} else {
    Response::error($result['message'], 400);
}
