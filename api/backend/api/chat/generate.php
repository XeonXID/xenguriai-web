<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['text'])) throw new Exception('Text is required');

    require_once __DIR__ . '/../../services/TTSService.php';
    $tts = new TTSService();

    $cleanText = $tts->cleanAIText($input['text']);
    $result = $tts->generateSpeech($cleanText, $input);

   
    header('Content-Type: audio/wav');
    header('Content-Length: ' . strlen($result['audio_data']));
    header('X-Cache-Status: ' . ($result['cached'] ? 'HIT' : 'MISS'));
    
    echo $result['audio_data'];

} catch (Exception $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode(['error' => $e->getMessage()]);
}