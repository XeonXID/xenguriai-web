<?php
require_once __DIR__ . '/../utils/jwt.php';

class AuthMiddleware {
    
    public static function verify() {
        // Enable error logging
        error_log("AuthMiddleware::verify() called");
        
        $token = JWT::getBearerToken();
        
        if (!$token) {
            error_log("No token found in request");
            Response::error('Unauthorized - No token provided', 401);
        }
        
        error_log("Token found: " . substr($token, 0, 20) . "...");
        
        $payload = JWT::decode($token);
        
        if (!$payload) {
            error_log("Token validation failed");
            Response::error('Unauthorized - Invalid token', 401);
        }
        
        error_log("Token validated for user: " . ($payload['username'] ?? 'unknown'));
        return $payload;
    }
    
    public static function optional() {
        $token = JWT::getBearerToken();
        
        if ($token) {
            $payload = JWT::decode($token);
            if ($payload) {
                return $payload;
            }
        }
        
        return null;
    }
}