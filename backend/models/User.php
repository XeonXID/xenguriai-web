<?php

require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table = 'users';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function register($username, $email, $password) {
       
        $check = $this->conn->prepare("SELECT id FROM {$this->table} WHERE email = ? OR username = ?");
        $check->execute([$email, $username]);
        
        if ($check->rowCount() > 0) {
            return ['success' => false, 'message' => 'User already exists'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table} (username, email, password_hash) 
            VALUES (?, ?, ?)
        ");
        
        if ($stmt->execute([$username, $email, $hash])) {
            $userId = $this->conn->lastInsertId();
            return [
                'success' => true,
                'user' => [
                    'id' => $userId,
                    'username' => $username,
                    'email' => $email
                ]
            ];
        }
        
        return ['success' => false, 'message' => 'Registration failed'];
    }

    public function login($email, $password) {
        $stmt = $this->conn->prepare("
            SELECT id, username, email, password_hash, avatar_url 
            FROM {$this->table} 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        unset($user['password_hash']);
        
        $token = JWT::encode([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]);

        return [
            'success' => true,
            'user' => $user,
            'token' => $token
        ];
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("
            SELECT id, username, email, avatar_url, created_at 
            FROM {$this->table} 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateAvatar($userId, $avatarUrl) {
        $stmt = $this->conn->prepare("
            UPDATE {$this->table} SET avatar_url = ? WHERE id = ?
        ");
        return $stmt->execute([$avatarUrl, $userId]);
    }
}