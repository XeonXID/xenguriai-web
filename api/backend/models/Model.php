<?php

require_once __DIR__ . '/../config/database.php';

class AIModel {
    private $conn;
    private $table = 'ai_models';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAllActive() {
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table} 
            WHERE is_active = TRUE 
            ORDER BY provider, name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById($modelId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table} WHERE model_id = ?
        ");
        $stmt->execute([$modelId]);
        return $stmt->fetch();
    }

    public function getByProvider($provider) {
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table} 
            WHERE provider = ? AND is_active = TRUE
            ORDER BY name
        ");
        $stmt->execute([$provider]);
        return $stmt->fetchAll();
    }
}