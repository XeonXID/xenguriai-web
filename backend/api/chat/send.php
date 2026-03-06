<?php

set_time_limit(0);


ini_set('max_execution_time', 300);


if (function_exists('ignore_user_abort')) {
    ignore_user_abort(true);
}
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Chat.php';
require_once __DIR__ . '/../../models/Message.php';
require_once __DIR__ . '/../../models/Model.php';
require_once __DIR__ . '/../../services/GroqService.php';
require_once __DIR__ . '/../../services/ImageService.php';
require_once __DIR__ . '/../../services/DocumentService.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

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
    $database = new Database();
    $db = $database->getConnection();
    
   
    $groq = new GroqService($chatId, $user['user_id'], $db);
    $aiResponse = $groq->chat($messages, $modelId, 0.7, $modelInfo['max_tokens']);
    
    $additionalData = [];
    $imageResults = [];
    $documentResults = [];
    
   
    if (!empty($aiResponse['actions'])) {
        
       
        $imageService = new ImageService();
        $docService = new DocumentService();
        
        foreach ($aiResponse['actions'] as $action) {
            
           
            if ($action['type'] === 'img' && isset($action['params']['prompt'])) {
                
               
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
                    
                   
                    $aiResponse['content'] .= "\n\n🖼️ **Gambar berhasil dibuat!**\n";
                    $aiResponse['content'] .= "Prompt: " . $action['params']['prompt'];
                    if (isset($action['params']['width']) && isset($action['params']['height'])) {
                        $aiResponse['content'] .= "\n📐 Ukuran: " . $action['params']['width'] . "x" . $action['params']['height'] . "px";
                    }
                    $aiResponse['content'] .= "\n📁 File: " . $imageResult['filename'];
                    
                } catch (Exception $e) {
                    $aiResponse['content'] .= "\n\n❌ **Gagal membuat gambar:** " . $e->getMessage();
                    error_log('Image Generation Error: ' . $e->getMessage());
                }
            }
                        
           
            elseif ($action['type'] === 'document') {
                try {
                    $html = '';
                    
                   
                    if (isset($action['params']['html'])) {
                        $html = $action['params']['html'];
                        
                       
                        $html = stripslashes($html);

                        $html = str_replace("'", '"', $html);
                        
                       
                        $html = trim($html);
                    }
                    
                   
                    if (!preg_match('/<!DOCTYPE html>/i', $html)) {
                        if (!preg_match('/<html>/i', $html)) {
                           
                            $title = $action['params']['title'] ?? 'Dokumen';
                            $html = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n<title>" . htmlspecialchars($title) . "</title>\n<style>
                                body { font-family: Arial; margin: 2.5cm; line-height: 1.5; }
                                h1 { color: #2E74B5; }
                                h2 { color: #2E74B5; }
                                p { margin-bottom: 12px; }
                                ul, ol { margin: 10px 0 10px 30px; }
                            </style>\n</head>\n<body>\n" . $html . "\n</body>\n</html>";
                        }
                    }
                    
                    if (empty($html)) {
                        throw new Exception('Tidak dapat menemukan konten HTML');
                    }
                    
                   
                    $options = [
                        'paperSize' => $action['params']['pageSize'] ?? 'A4',
                        'orientation' => $action['params']['orientation'] ?? 'portrait',
                        'font' => $action['params']['font'] ?? 'Arial'
                    ];
                    
                   
                    $docResult = $docService->generatePdf(
                        $html,
                        $action['params']['filename'] ?? 'dokumen_' . date('Ymd_His'),
                        $options
                    );
                    
                    if ($docResult['success']) {
                        $documentResults[] = $docResult;
                        $sizeFormatted = $docService->formatBytes($docResult['size']);
                        
                       
                        $aiResponse['content'] .= "\n\n📄 **PDF Berhasil Dibuat!**\n";
                        $aiResponse['content'] .= "📁 Nama File: `{$docResult['filename']}`\n";
                        $aiResponse['content'] .= "📊 Ukuran: {$sizeFormatted}\n";
                        $aiResponse['content'] .= "📄 Jumlah Halaman: {$docResult['pages']}\n";
                        $aiResponse['content'] .= "\n⬇️ [Download PDF]({$docResult['url']})";
                        
                        $additionalData['documents'][] = [
                            'filename' => $docResult['filename'],
                            'url' => $docResult['url'],
                            'size' => $docResult['size'],
                            'pages' => $docResult['pages'],
                            'format' => 'pdf'
                        ];
                        
                    } else {
                        throw new Exception($docResult['error'] ?? 'Gagal membuat PDF');
                    }
                    
                } catch (Exception $e) {
                    $aiResponse['content'] .= "\n\n❌ **Gagal membuat PDF:** " . $e->getMessage();
                    error_log('PDF Generation Error: ' . $e->getMessage());
                }
            }
            
           
            elseif ($action['type'] === 'youtube_search') {
               
               
                if (isset($action['executed']) && $action['executed']) {
                    $additionalData['youtube_searches'][] = [
                        'query' => $action['params']['query'] ?? '',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }
    }
    
   
    $metadata = [
        'like' => false,
        'dislike' => false,
        'model_used' => $modelId,
        'actions' => $aiResponse['actions'] ?? [],
        'image_results' => $imageResults,
        'document_results' => $documentResults,
        'additional_data' => $additionalData
    ];
    
   
    $aiMsg = $messageModel->create($chatId, 'assistant', $aiResponse['content'], $modelId, $metadata);
    
   
    $chatModel->updateTimestamp($chatId);
    
   
    $currentChat = $chatModel->getById($chatId, $user['user_id']);
    $titleNeedsUpdate = strpos($currentChat['title'], '...') !== false || strlen($currentChat['title']) < 10;
    
    if ($titleNeedsUpdate) {
        $newTitle = substr($data['content'], 0, 30);
        $lowerContent = strtolower($data['content']);
        
       
        if (strpos($lowerContent, 'gambar') !== false || 
            strpos($lowerContent, 'foto') !== false ||
            strpos($lowerContent, 'image') !== false) {
            $newTitle = "🖼️ " . $newTitle;
        } elseif (strpos($lowerContent, 'dokumen') !== false || 
                  strpos($lowerContent, 'surat') !== false ||
                  strpos($lowerContent, 'laporan') !== false ||
                  strpos($lowerContent, 'word') !== false ||
                  strpos($lowerContent, 'docx') !== false) {
            $newTitle = "📄 " . $newTitle;
        } elseif (strpos($lowerContent, 'youtube') !== false ||
                  strpos($lowerContent, 'video') !== false) {
            $newTitle = "📺 " . $newTitle;
        }
        
        $chatModel->updateTitle($chatId, $newTitle . '...');
    }
    
   
    Response::success([
        'chat_id' => $chatId,
        'user_message' => $userMsg,
        'ai_message' => $aiMsg,
        'model_used' => $aiResponse['model'],
        'usage' => $aiResponse['usage'] ?? null,
        'actions' => $aiResponse['actions'] ?? [],
        'image_results' => $imageResults,
        'document_results' => $documentResults,
        'additional_data' => $additionalData
    ]);

} catch (ArgumentCountError $e) {
    error_log('ArgumentCountError in send.php: ' . $e->getMessage());
    
    $fallbackResponse = "Maaf, terjadi kesalahan internal. Silakan coba lagi.";
    
    $aiMsg = $messageModel->create($chatId, 'assistant', $fallbackResponse, $modelId, [
        'like' => false,
        'dislike' => false,
        'error' => true,
        'error_message' => 'Internal server error: ' . $e->getMessage()
    ]);

    Response::success([
        'chat_id' => $chatId,
        'user_message' => $userMsg,
        'ai_message' => $aiMsg,
        'error' => true,
        'error_message' => 'Internal server error'
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
        'error_message' => $e->getMessage()
    ]);
}

/**
 * Helper function to process document elements recursively
 */
function processDocumentElement($docService, $element) {
    $type = $element['type'] ?? 'text';
    
    switch ($type) {
        case 'heading':
            $docService->addHeading(
                $element['text'] ?? '',
                $element['level'] ?? 1,
                $element['style'] ?? []
            );
            break;
            
        case 'text':
            $docService->addText(
                $element['text'] ?? '',
                $element['font'] ?? [],
                $element['paragraph'] ?? []
            );
            break;
            
        case 'paragraph':
            $docService->addParagraph(
                $element['text'] ?? '',
                $element['options'] ?? []
            );
            break;
            
        case 'list':
            foreach ($element['items'] as $item) {
                $docService->addListItem(
                    $item['text'],
                    $item['listType'] ?? 'bullet',
                    $item['font'] ?? [],
                    $item['paragraph'] ?? [],
                    $item['depth'] ?? 0
                );
            }
            break;
            
        case 'table':
            $docService->addTable(
                $element['rows'] ?? [],
                $element['options'] ?? []
            );
            break;
            
        case 'image':
            $docService->addImage(
                $element['source'] ?? '',
                $element['options'] ?? []
            );
            break;
            
        case 'pagebreak':
            $docService->addPageBreak();
            break;
            
        case 'linebreak':
            $docService->addLineBreak($element['count'] ?? 1);
            break;
            
        case 'line':
            $docService->addHorizontalLine($element['options'] ?? []);
            break;
            
        case 'textrun':
            $textRun = $docService->addTextRun($element['paragraph'] ?? []);
            foreach ($element['items'] as $runItem) {
                if ($runItem['type'] === 'text') {
                    $textRun->addText($runItem['text'], $runItem['font'] ?? []);
                } elseif ($runItem['type'] === 'link') {
                    $textRun->addLink($runItem['url'], $runItem['text'], $runItem['font'] ?? []);
                }
            }
            break;
    }
}