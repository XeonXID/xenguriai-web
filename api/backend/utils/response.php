<?php
class Response {
    public static function setCORSHeaders() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    public static function json($data, $status = 200) {
        self::setCORSHeaders();
        http_response_code($status);
        header('Content-Type: application/json');
        
        echo json_encode($data);
        exit;
    }

    public static function success($data, $message = 'Success') {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    public static function error($message, $status = 400) {
        self::json([
            'success' => false,
            'message' => $message
        ], $status);
    }

    public static function unauthorized() {
        self::error('Unauthorized', 401);
    }
}