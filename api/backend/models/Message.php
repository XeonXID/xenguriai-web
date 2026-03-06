<?php
class Message {
    private $conn;
    private $table = 'messages';

    public function __construct($db = null) {
        if ($db === null) {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $this->conn = $database->getConnection();
        } else {
            $this->conn = $db;
        }
    }

        public function create($chatId, $role, $content, $model = null, $metadata = []) {
        $query = "INSERT INTO {$this->table} 
                 (chat_id, role, content, model, metadata) 
                 VALUES (:chat_id, :role, :content, :model, :metadata)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':chat_id', $chatId);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':model', $model);
        
       
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
        $stmt->bindParam(':metadata', $metadataJson);
        
        if ($stmt->execute()) {
            $id = $this->conn->lastInsertId();
            return $this->getById($id);
        }
        
        return false;
    }

        public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
           
            $row['metadata'] = json_decode($row['metadata'] ?? '{}', true);
            $row['reactions'] = $row['metadata']['reactions'] ?? ['like' => false, 'dislike' => false];
        }
        
        return $row;
    }

        public function getByChat($chatId, $limit = 50) {
        $query = "SELECT * FROM {$this->table} 
                 WHERE chat_id = :chat_id 
                 ORDER BY created_at ASC 
                 LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $chatId);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $messages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
           
            $row['metadata'] = json_decode($row['metadata'] ?? '{}', true);
            $row['reactions'] = $row['metadata']['reactions'] ?? ['like' => false, 'dislike' => false];
            $messages[] = $row;
        }
        
        return $messages;
    }

        public function updateMetadata($id, $metadata) {
        $current = $this->getById($id);
        if (!$current) return false;
        
        $currentMetadata = $current['metadata'] ?? [];
        $updatedMetadata = array_merge($currentMetadata, $metadata);
        
        $query = "UPDATE {$this->table} 
                 SET metadata = :metadata 
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $metadataJson = json_encode($updatedMetadata, JSON_UNESCAPED_UNICODE);
        $stmt->bindParam(':metadata', $metadataJson);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

        public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

        public function deleteByChat($chatId) {
        $query = "DELETE FROM {$this->table} WHERE chat_id = :chat_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $chatId);
        
        return $stmt->execute();
    }
}