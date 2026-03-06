<?php



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
       
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        
       
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
       
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input: ' . json_last_error_msg());
        }
        
       
        $root = realpath(__DIR__ . '/../..');
        
       
        if (!file_exists($root . '/services/TTSService.php')) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'TTS Service not found']);
            exit();
        }
        
        require_once $root . '/services/TTSService.php';
        
       
        if (empty($data['text'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Text is required']);
            exit();
        }
        
        $text = $data['text'];
        $ttsService = new TTSService();
        
       
        $options = [
            'model' => $data['model'] ?? 'canopylabs/orpheus-v1-english',
            'voice' => $data['voice'] ?? 'autumn',
            'speed' => isset($data['speed']) ? floatval($data['speed']) : 1.0
        ];
        
       
        if (!empty($data['clean_text']) && $data['clean_text'] === true) {
            $text = $ttsService->cleanAIText($text);
        }
        
       
        $result = $ttsService->generateSpeech($text, $options);
        
       
        header('Content-Type: audio/wav');
        header('Content-Length: ' . $result['audio_size']);
        header('Cache-Control: public, max-age=3600');
        header('X-Cached: ' . ($result['cached'] ? 'true' : 'false'));
        
       
        echo $result['audio_data'];
        exit();
        
    } catch (Exception $e) {
        error_log('TTS Generate Error: ' . $e->getMessage());
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Failed to generate speech',
            'message' => $e->getMessage()
        ]);
        exit();
    }
} else {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}
?>