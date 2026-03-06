<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Chat.php';
require_once __DIR__ . '/../../models/Message.php';
require_once __DIR__ . '/../../models/Model.php';
require_once __DIR__ . '/../../services/GroqService.php';
require_once __DIR__ . '/../../services/ImageService.php';
require_once __DIR__ . '/../../services/GoogleSearchService.php';
require_once __DIR__ . '/../../services/WebScraperService.php';   
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$user = AuthMiddleware::verify();
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['content'])) {
    Response::error('Message content required');
}

$chatId = isset($data['chat_id']) ? intval($data['chat_id']) : null;
$modelId = isset($data['model']) ? $data['model'] : 'llama-3.1-70b-versatile';

$modelObj = new AIModel();
$modelInfo = $modelObj->getById($modelId);

if (!$modelInfo) {
    Response::error('Invalid model selected', 400);
}

$chatModel = new Chat();
$messageModel = new Message();

if (!$chatId) {
    $title = substr($data['content'], 0, 50) . '...';
    $chat = $chatModel->create($user['user_id'], $title, $modelId);
    $chatId = $chat['id'];
} else {
   
    $chat = $chatModel->getById($chatId, $user['user_id']);
    if (!$chat) {
        Response::error('Chat not found', 404);
    }
}

$userMsg = $messageModel->create($chatId, 'user', $data['content']);

$history = $messageModel->getByChat($chatId, 10);
$messages = [];

foreach ($history as $msg) {
    $messages[] = [
        'role' => $msg['role'],
        'content' => $msg['content']
    ];
}

if (empty($messages) || end($messages)['content'] !== $data['content']) {
    $messages[] = [
        'role' => 'user',
        'content' => $data['content']
    ];
}

try {
   
    $groq = new GroqService();
    $aiResponse = $groq->chat($messages, $modelId, 0.7, $modelInfo['max_tokens']);
    
    $additionalData = [];
    $imageResults = [];
    $webResults = [];
    
   
    if (!empty($aiResponse['actions'])) {
        $imageService = new ImageService();
        
        foreach ($aiResponse['actions'] as $action) {
            switch ($action['type']) {
                case 'img':
                    if (isset($action['params']['prompt'])) {
                        
                       
                        $validation = $imageService->validatePrompt($action['params']['prompt']);
                        if (!$validation['valid']) {
                            $aiResponse['content'] .= "\n\n⚠️ " . $validation['reason'];
                            continue;
                        }
                        
                       
                        $options = [];
                        if (isset($action['params']['negative_prompt'])) {
                            $options['negative_prompt'] = $action['params']['negative_prompt'];
                        }
                        if (isset($action['params']['width'])) {
                            $options['width'] = $action['params']['width'];
                        }
                        if (isset($action['params']['height'])) {
                            $options['height'] = $action['params']['height'];
                        }
                        
                       
                        try {
                            $imageResult = $imageService->generateImage($action['params']['prompt'], $options);
                            $imageResults[] = $imageResult;
                            
                           
                            $additionalData['images'][] = [
                                'prompt' => $action['params']['prompt'],
                                'image_url' => $imageResult['image_url'],
                                'filename' => $imageResult['filename'],
                                'size' => $imageResult['size']
                            ];
                            
                           
                            $aiResponse['content'] .= "\n\n🖼️ **Gambar berhasil dibuat!**\n📝 Prompt: " . $action['params']['prompt'];
                            if (isset($action['params']['width']) && isset($action['params']['height'])) {
                                $aiResponse['content'] .= "\n📐 Ukuran: " . $action['params']['width'] . "x" . $action['params']['height'] . "px";
                            }
                            
                        } catch (Exception $e) {
                            $aiResponse['content'] .= "\n\n❌ **Gagal membuat gambar:** " . $e->getMessage();
                            error_log('Image Generation Error: ' . $e->getMessage());
                        }
                    }
                    break;
                    
                case 'web_search':
                   
                    if (isset($aiResponse['web_results'])) {
                        foreach ($aiResponse['web_results'] as $webResult) {
                            if ($webResult['type'] === 'web_search') {
                                $webResults[] = [
                                    'type' => 'web_search',
                                    'query' => $webResult['query'] ?? '',
                                    'summary' => $webResult['data']['summary'] ?? '',
                                    'success' => $webResult['data']['success'] ?? false
                                ];
                            }
                        }
                    }
                    break;
                    
                case 'scrape':
                   
                    if (isset($aiResponse['web_results'])) {
                        foreach ($aiResponse['web_results'] as $webResult) {
                            if ($webResult['type'] === 'scrape') {
                                $webResults[] = [
                                    'type' => 'scrape',
                                    'url' => $webResult['url'] ?? '',
                                    'title' => $webResult['data']['title'] ?? '',
                                    'summary' => $webResult['data']['text_summary'] ?? '',
                                    'success' => true
                                ];
                            }
                        }
                    }
                    break;
            }
        }
    }
    
   
    if (!empty($aiResponse['web_results'])) {
        foreach ($aiResponse['web_results'] as $webResult) {
           
            $additionalData['web_results'][] = [
                'type' => $webResult['type'],
                'query' => $webResult['query'] ?? null,
                'url' => $webResult['url'] ?? null,
                'success' => $webResult['data']['success'] ?? false
            ];
        }
    }
    
   
    $metadata = [
        'like' => false,
        'dislike' => false,
        'model_used' => $modelId,
        'actions' => $aiResponse['actions'] ?? [],
        'image_results' => $imageResults,
        'additional_data' => $additionalData,
        'has_web_search' => !empty($webResults) || !empty($aiResponse['web_results'])
    ];
    
   
    if (!empty($webResults)) {
        $metadata['web_results'] = $webResults;
    }
    
    $aiMsg = $messageModel->create($chatId, 'assistant', $aiResponse['content'], $modelId, $metadata);

   
    $chatModel->updateTimestamp($chatId);
    
   
    $currentChat = $chatModel->getById($chatId, $user['user_id']);
    if ($currentChat && (strpos($currentChat['title'], '...') !== false || strlen($currentChat['title']) < 10)) {
       
        $newTitle = substr($data['content'], 0, 30);
        
       
        $contentLower = strtolower($data['content']);
        if (strpos($contentLower, 'gambar') !== false || 
            strpos($contentLower, 'foto') !== false ||
            strpos($contentLower, 'image') !== false) {
            $newTitle = "🖼️ " . $newTitle;
        } elseif (strpos($contentLower, 'cari') !== false || 
                 strpos($contentLower, 'search') !== false ||
                 strpos($contentLower, 'berita') !== false ||
                 strpos($contentLower, 'informasi') !== false) {
            $newTitle = "🔍 " . $newTitle;
        } elseif (strpos($contentLower, 'baca') !== false || 
                 strpos($contentLower, 'website') !== false ||
                 strpos($contentLower, 'artikel') !== false) {
            $newTitle = "🌐 " . $newTitle;
        }
        
        $chatModel->updateTitle($chatId, $newTitle . '...');
    }

   
    $responseData = [
        'chat_id' => $chatId,
        'user_message' => $userMsg,
        'ai_message' => $aiMsg,
        'model_used' => $aiResponse['model'],
        'usage' => $aiResponse['usage'],
        'actions' => $aiResponse['actions'] ?? [],
        'image_results' => $imageResults,
        'additional_data' => $additionalData
    ];
    
   
    if (!empty($webResults)) {
        $responseData['web_results'] = $webResults;
    }
    
   
    $responseData['web_search_available'] = $groq->isWebSearchAvailable();
    $responseData['web_scraping_available'] = $groq->isWebScrapingAvailable();

    Response::success($responseData);

} catch (ArgumentCountError $e) {
   
    error_log('ArgumentCountError in send.php: ' . $e->getMessage());
    
    $fallbackResponse = "Maaf, terjadi kesalahan internal. Silakan coba lagi.";
    
    $aiMsg = $messageModel->create($chatId, 'assistant', $fallbackResponse, $modelId, [
        'like' => false,
        'dislike' => false,
        'error' => true,
        'error_message' => 'Internal server error'
    ]);

    Response::success([
        'chat_id' => $chatId,
        'user_message' => $userMsg,
        'ai_message' => $aiMsg,
        'error' => true,
        'error_message' => 'Internal server error',
        'web_search_available' => false,
        'web_scraping_available' => false
    ]);
} catch (Exception $e) {
   
    error_log('Chat Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    
   
    $fallbackResponse = "Maaf, terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi dalam beberapa saat.";
    
    $aiMsg = $messageModel->create($chatId, 'assistant', $fallbackResponse, $modelId, [
        'like' => false,
        'dislike' => false,
        'error' => true,
        'error_message' => $e->getMessage()
    ]);

    Response::success([
        'chat_id' => $chatId,
        'user_message' => $userMsg,
        'ai_message' => $aiMsg,
        'error' => true,
        'error_message' => $e->getMessage(),
        'web_search_available' => false,
        'web_scraping_available' => false
    ]);
}