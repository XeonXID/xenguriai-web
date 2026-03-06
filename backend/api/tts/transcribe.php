<?php


require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;


$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
   
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        throw new Exception('Unauthorized - Invalid token format');
    }
    
    $token = $matches[1];
    
   
   
    
   
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (max ' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $errorCode = $_FILES['audio']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errorMsg = $errorMessages[$errorCode] ?? 'Unknown upload error';
        
        throw new Exception('Upload error: ' . $errorMsg);
    }

   
    $allowedTypes = ['audio/webm', 'audio/mp4', 'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp3'];
    $fileType = $_FILES['audio']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed: webm, mp4, mpeg, wav, ogg, mp3');
    }

   
    $maxSize = 25 * 1024 * 1024;
    if ($_FILES['audio']['size'] > $maxSize) {
        throw new Exception('File terlalu besar. Maksimal 25MB');
    }

    $audioFile = $_FILES['audio']['tmp_name'];
    $model = $_POST['model'] ?? 'whisper-large-v3';
    $language = $_POST['language'] ?? 'id';
    
   
    $apiKey = $_ENV['GROQ_API_KEY'] ?? '';
    
    if (empty($apiKey)) {
        throw new Exception('GROQ_API_KEY not found in environment variables');
    }
    
    $apiUrl = 'https://api.groq.com/openai/v1/audio/transcriptions';
    
   
    $postFields = [
        'file' => new CURLFile($audioFile, $_FILES['audio']['type'], $_FILES['audio']['name']),
        'model' => $model,
        'response_format' => 'json'
    ];
    
   
    if (!empty($language)) {
        $postFields['language'] = $language;
    }

   
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);

    if ($curlError) {
        throw new Exception('CURL Error: ' . $curlError);
    }

    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        $errorMsg = $error['error']['message'] ?? "HTTP Error: {$httpCode}";
        
       
        error_log("Groq API Error: {$errorMsg} | Response: " . substr($response, 0, 500));
        
        throw new Exception('Transcription failed: ' . $errorMsg);
    }

    $result = json_decode($response, true);
    
    if (!isset($result['text'])) {
        throw new Exception('Invalid response from API');
    }

    echo json_encode([
        'success' => true,
        'text' => trim($result['text']),
        'model' => $model,
        'language' => $language,
        'duration' => $result['duration'] ?? null
    ]);

} catch (Exception $e) {
    error_log('STT Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>