<?php


require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
       
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        
       
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'xenfuriai';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
           
            error_log("Database connection error: " . $e->getMessage());
            
           
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
        return $this->conn;
    }

    public function testConnection() {
        try {
            $this->getConnection();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}