<?php
class Chat {
    private $conn;
    private $table = 'chats';

    public function __construct($db = null) {
        if ($db === null) {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $this->conn = $database->getConnection();
        } else {
            $this->conn = $db;
        }
    }

        public function create($userId, $title = 'New Chat', $model = null) {
        $query = "INSERT INTO {$this->table} 
                 (user_id, title, model) 
                 VALUES (:user_id, :title, :model)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':model', $model);
        
        if ($stmt->execute()) {
            $id = $this->conn->lastInsertId();
            return $this->getById($id, $userId);
        }
        
        return false;
    }

        public function getById($id, $userId = null) {
        if ($userId !== null) {
            $query = "SELECT * FROM {$this->table} 
                     WHERE id = :id AND user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
        } else {
            $query = "SELECT * FROM {$this->table} WHERE id = :id";
            $stmt = $this->conn->prepare($query);
        }
        
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

        public function getAllByUser($userId, $limit = 50) {
        $query = "SELECT * FROM {$this->table} 
                 WHERE user_id = :user_id 
                 ORDER BY updated_at DESC 
                 LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $chats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $chats[] = $row;
        }
        
        return $chats;
    }

        public function updateTitle($chatId, $title) {
        $query = "UPDATE {$this->table} 
                 SET title = :title, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :chat_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $chatId);
        $stmt->bindParam(':title', $title);
        
        return $stmt->execute();
    }

        public function updateTimestamp($chatId) {
        $query = "UPDATE {$this->table} 
                 SET updated_at = CURRENT_TIMESTAMP 
                 WHERE id = :chat_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $chatId);
        
        return $stmt->execute();
    }

        public function updateModel($chatId, $model) {
        $query = "UPDATE {$this->table} 
                 SET model = :model, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :chat_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $chatId);
        $stmt->bindParam(':model', $model);
        
        return $stmt->execute();
    }

        public function delete($chatId, $userId) {
        $query = "DELETE FROM {$this->table} 
                 WHERE id = :chat_id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $chatId);
        $stmt->bindParam(':user_id', $userId);
        
        return $stmt->execute();
    }

        public function getGroupedByDate($userId) {
        $chats = $this->getAllByUser($userId, 100);
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $grouped = [
            'today' => [],
            'yesterday' => [],
            'older' => []
        ];
        
        foreach ($chats as $chat) {
            $chatDate = date('Y-m-d', strtotime($chat['updated_at']));
            
            if ($chatDate === $today) {
                $grouped['today'][] = $chat;
            } elseif ($chatDate === $yesterday) {
                $grouped['yesterday'][] = $chat;
            } else {
                $grouped['older'][] = $chat;
            }
        }
        
        return $grouped;
    }
}