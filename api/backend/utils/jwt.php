<?php

class JWT {
    private static $secret = "your-secret-key-change-this-in-production";
    private static $algorithm = "HS256";
    
    private static function getHeaders() {
        // Method 1: Try apache_request_headers (if available)
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            error_log("apache_request_headers(): " . print_r($headers, true));
            return $headers;
        }
        
        // Method 2: Try getallheaders (if available)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            error_log("getallheaders(): " . print_r($headers, true));
            return $headers;
        }
        
        // Method 3: Build from $_SERVER
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
        
        // Also check for Authorization in $_SERVER directly
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        
        error_log("Generated headers from \$_SERVER: " . print_r($headers, true));
        return $headers;
    }
    
    public static function encode($payload, $exp = 2592000) {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algorithm]);
        $time = time();
        $payload['iat'] = $time;
        $payload['exp'] = $time + $exp;

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", self::$secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return "$base64Header.$base64Payload.$base64Signature";
    }

    public static function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            error_log("JWT decode failed: invalid parts count");
            return false;
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        if (!$payload) {
            error_log("JWT decode failed: invalid payload");
            return false;
        }
        
        if (!isset($payload['exp'])) {
            error_log("JWT decode failed: no expiration");
            return false;
        }
        
        if ($payload['exp'] < time()) {
            error_log("JWT decode failed: token expired");
            return false;
        }

        $signature = hash_hmac('sha256', "$parts[0].$parts[1]", self::$secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if (!hash_equals($base64Signature, $parts[2])) {
            error_log("JWT decode failed: invalid signature");
            return false;
        }

        error_log("JWT decode success for user: " . ($payload['username'] ?? 'unknown'));
        return $payload;
    }

    public static function getBearerToken() {
        // Log all incoming data for debugging
        error_log("=== JWT::getBearerToken() called ===");
        error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
        error_log("All _SERVER keys: " . print_r(array_keys($_SERVER), true));
        
        // Check direct PHP input for Authorization header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            error_log("Found HTTP_AUTHORIZATION: " . $auth);
            if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
                return $matches[1];
            }
        }
        
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            error_log("Found REDIRECT_HTTP_AUTHORIZATION: " . $auth);
            if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
                return $matches[1];
            }
        }
        
        // Try to get headers
        $headers = self::getHeaders();
        
        // Look for Authorization in various casings
        $authHeaderKeys = ['Authorization', 'authorization', 'AUTHORIZATION', 'HTTP_AUTHORIZATION'];
        
        foreach ($authHeaderKeys as $key) {
            if (isset($headers[$key]) && !empty($headers[$key])) {
                $auth = $headers[$key];
                error_log("Found authorization in headers[$key]: " . $auth);
                
                if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        // If still not found, check apache_request_headers directly
        if (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            error_log("apache_request_headers() direct: " . print_r($apacheHeaders, true));
            
            foreach ($apacheHeaders as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    if (preg_match('/Bearer\s(\S+)/', $value, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }
        
        error_log("No authorization header found in any location");
        return false;
    }
}