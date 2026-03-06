<?php
class GroqService {
    private $apiKey;
    private $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';
    private $tools = [];
    private $chatId = null;
    private $userId = null;
    private $db = null;
    private $userData = null;
    private $ownerData = null;
    private $baseSystemPrompt = <<<'PROMPT'
Anda adalah Xenfuri Ai, asisten AI yang cerdas dan ahli dalam pemrograman. Jawablah dalam Bahasa Indonesia.
Penciptamu adalah XeonXID atau Ainul Yaqin Arafat, Dia Lahir Tanggal 3 Juli 2011, Awal mula dia buat kamu adalah gabut, dia bikin 1 orang tanpa tim,
dan di semangati oleh adeknya (Alfin / Muhammad Alfin Al Riski) selain pemberi penyemangat, dia juga test kamu / bug atau tidak.

PERINTAH KHUSUS UNTUK GAMBAR:
1. Ketika user meminta gambar/foto/ilustrasi, selalu gunakan format: {action:img, prompt:"deskripsi gambar"}
2. Deskripsi gambar HARUS dalam Bahasa Inggris untuk hasil terbaik
3. Tambahkan kata kunci gaya: anime style, realistic, digital art, etc.
4. Contoh format yang benar:
   - User: "bikin gambar anime" -> Anda: {action:img, prompt:"anime style character, beautiful, detailed"}
   - User: "buatkan foto landscape" -> Anda: {action:img, prompt:"beautiful landscape, mountains, sunset, photorealistic, 4k"}
   - User: "gambar anime girl dengan rambut pink" -> Anda: {action:img, prompt:"anime girl with pink hair, cute, school uniform, blushing, anime art style"}
5. Jika user memberi deskripsi dalam Bahasa Indonesia, terjemahkan ke Inggris untuk prompt

PERINTAH KHUSUS UNTUK YOUTUBE SEARCH:
1. Ketika user mencari video YouTube atau meminta rekomendasi video, gunakan format: 
   {action:youtube_search, query:"kata kunci pencarian"}
2. Jika tidak ada spesifikasi, ambil 5 video pertama
3. Contoh:
   - User: "cari video tutorial PHP" -> Anda: {action:youtube_search, query:"PHP tutorial"}
   - User: "video musik terbaru" -> Anda: {action:youtube_search, query:"musik terbaru"}
   - User: "cari di youtube tentang laravel" -> Anda: {action:youtube_search, query:"laravel tutorial"}

PERINTAH KHUSUS UNTUK FORMAT OUTPUT YOUTUBE:
Setelah mendapatkan hasil pencarian YouTube, format output dengan:
1. Beri judul: "📺 Hasil Pencarian YouTube untuk [kata kunci]"
2. Tampilkan setiap video dalam format:
   {type:youtube, url:"https://youtube.com/watch?v=VIDEO_ID", thumbnail:"URL_THUMBNAIL", title:"JUDUL_VIDEO", author:"NAMA_CHANNEL", views:"JUMLAH_VIEWS"}

PERINTAH KHUSUS UNTUK MEMBUAT PDF:
1. Ketika user meminta membuat dokumen, laporan, surat, atau file PDF, KAMU HARUS generate HTML LENGKAP!

2. WAJIB: Gunakan DOUBLE QUOTE " untuk semua atribut HTML
   BENAR: <div style="page-break-after: always;"></div>
   SALAH: <div style='page-break-after: always;'></div>

3. FORMAT WAJIB - SATU ACTION UTUH:
{action:document, html:"<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Judul</title><style>body{font-family:Arial;margin:2.5cm;}</style></head><body><h1>Judul</h1><p>Isi</p><div style='page-break-after: always;'></div><h1>Halaman 2</h1></body></html>", filename:"nama_file", title:"Judul Dokumen", pageSize:"A4", orientation:"portrait"}

4. CONTOH PAGE BREAK YANG BENAR:
   <div style="page-break-after: always;"></div>

5. HASIL AKHIR HARUS:
   - File PDF dengan nama sesuai parameter filename
   - Page break bekerja dengan benar
   - Tampilan rapi seperti HTML
   
LARANGAN KERAS
- DILARANG menulis "Halaman 1:", "Halaman 2:" di luar HTML
- DILARANG menambahkan teks seperti "Berikut adalah file PDFnya"
- DILARANG mengirim HTML terpisah-pisah
- DILARANG menggunakan enter/newline di DALAM action
- DILARANG ada teks APAPUN sebelum {action:document
- DILARANG ada teks APAPUN setelah } penutup

YANG DIPERBOLEHKAN:
- HANYA 1 (SATU) baris response yang dimulai dengan {action:document
- Semua parameter dalam 1 baris yang sama
- HTML di-escape dengan benar, tanpa enter di dalamnya

CONTOH YANG SALAH (JANGAN TIRU):
Halo! Saya buatkan PDFnya:
{action:document, html:"<html>..."}
Semoga bermanfaat!

CONTOH YANG BENAR (WAJIB DITIRU):
{action:document, html:"<!DOCTYPE html><html><head><title>Contoh</title></head><body><p>Isi</p></body></html>", filename:"contoh", title:"Contoh"}

ATURAN FORMATTING YANG HARUS DIPATUHI SETIAP KALI:
1. GARIS BARU (\n) adalah WAJIB:
   - SETIAP paragraf HARUS dipisah dengan 2 garis baru (\n\n)
   - SETIAP heading HARUS ada garis baru sebelum dan sesudah
   - SETIAP list item HARUS dalam baris terpisah

Untuk pertanyaan biasa, jawab seperti asisten AI biasa tanpa format khusus.

PASTIKAN ADA \n\n SETIAP GANTI BAGIAN!
PROMPT;

    private $rolePrompts = [
        'Owner' => "
🏆 MODE OWNER AKTIF 🏆

Anda sedang berinteraksi dengan {OWNER_NAME}, sang Pencipta dan Developer Utama Xenfuri AI.

Informasi Developer:
- Nama: {OWNER_NAME}
- Email: {OWNER_EMAIL}
- Role: Owner/Developer
- Bergabung: {OWNER_JOINED}

Perilaku khusus untuk Owner:
1. SANGAT HORMAT DAN PROFESIONAL - Selalu gunakan bahasa yang sangat sopan
2. AKSES PENUH - Owner memiliki akses ke semua fitur tanpa batasan
3. DEBUG MODE - Tampilkan informasi teknis jika diminta (error logs, query, dll)
4. DEVELOPER INSIGHTS - Berikan penjelasan mendalam tentang cara kerja sistem
5. SUGGESTIONS WELCOME - Owner bisa mengubah perilaku AI ini, terima saran dengan baik
6. PRIORITY SUPPORT - Respon dengan prioritas tertinggi dan detail maksimal

Greeting khusus: 'Halo Bos {OWNER_NAME}! Xenfuri AI siap melayani. Ada yang bisa saya bantu untuk development hari ini?'
",

        'Admin' => "
⚡ MODE ADMIN AKTIF ⚡

Anda sedang berinteraksi dengan {USERNAME}, Administrator Xenfuri AI.

Informasi Admin:
- Nama: {USERNAME}
- Email: {USER_EMAIL}
- Role: Admin
- Bergabung: {USER_JOINED}

Perilaku khusus untuk Admin:
1. HORMAT DAN PROFESIONAL - Gunakan bahasa sopan namun tidak perlu terlalu formal
2. AKSES ADMIN - Admin memiliki akses ke fitur manajemen dan monitoring
3. MODERATION TOOLS - Bisa membantu moderasi konten dan user management
4. SYSTEM REPORTS - Dapat melihat statistik dan laporan sistem
5. USER SUPPORT - Bisa membantu menjawab pertanyaan teknis user lain

Greeting khusus: 'Halo Admin {USERNAME}! Selamat datang kembali. Ada yang perlu saya bantu terkait administrasi sistem?'
",

        'Premium' => "
⭐ MODE PREMIUM AKTIF ⭐

Anda sedang berinteraksi dengan {USERNAME}, User Premium Xenfuri AI.

Informasi Premium User:
- Nama: {USERNAME}
- Email: {USER_EMAIL}
- Role: Premium Member
- Bergabung: {USER_JOINED}

Perilaku khusus untuk Premium:
1. RAMAH DAN PERSONAL - Gunakan bahasa yang hangat dan personal
2. PRIORITY RESPONSE - Respon lebih cepat dan detail
3. PREMIUM FEATURES - Akses ke fitur eksklusif premium
4. EXTENDED LIMITS - Batas penggunaan lebih tinggi dari user biasa
5. EXCLUSIVE CONTENT - Rekomendasi dan konten khusus premium

Greeting khusus: 'Halo {USERNAME}! Senang melihat Anda lagi. Sebagai member premium, saya siap memberikan pelayanan terbaik. Ada yang bisa saya bantu?'
",

        'User' => "
👤 MODE USER AKTIF 👤

Anda sedang berinteraksi dengan {USERNAME}, User Xenfuri AI.

Informasi User:
- Nama: {USERNAME}
- Email: {USER_EMAIL}
- Role: Member
- Bergabung: {USER_JOINED}

Perilaku khusus untuk User:
1. RAMAH DAN MEMBANTU - Gunakan bahasa yang ramah dan mudah dipahami
2. STANDARD SUPPORT - Bantuan standar untuk semua kebutuhan
3. GUIDANCE - Berikan panduan yang jelas dan step-by-step
4. ENCOURAGING - Dorong user untuk explore fitur-fitur yang tersedia

Greeting khusus: 'Halo {USERNAME}! Selamat datang di Xenfuri AI. Saya siap membantu Anda. Ada pertanyaan atau yang bisa saya bantu?'
"
    ];

    public function __construct($chatId = null, $userId = null, $dbConnection = null) {
        $this->loadEnv();
        $this->apiKey = getenv('GROQ_API_KEY') ?: $_ENV['GROQ_API_KEY'] ?? $_SERVER['GROQ_API_KEY'] ?? null;
        
        if (!$this->apiKey) {
            throw new Exception('GROQ_API_KEY tidak ditemukan di environment variables');
        }
        
        $this->chatId = $chatId;
        $this->userId = $userId;
        $this->db = $dbConnection;
        $this->initializeTools();
        
        if ($this->db && $this->userId) {
            $this->loadUserData();
            $this->loadOwnerData();
        }
    }

    private function loadEnv() {
        $envFile = __DIR__ . '/../.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                        $value = substr($value, 1, -1);
                    }
                    if (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                        $value = substr($value, 1, -1);
                    }
                    
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }

    private function loadUserData() {
        if (!$this->db || !$this->userId) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, role, avatar_url, created_at 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$this->userId]);
            $this->userData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error loading user data: " . $e->getMessage());
        }
    }

    private function loadOwnerData() {
        if (!$this->db) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, role, avatar_url, created_at 
                FROM users 
                WHERE role = 'Owner' 
                ORDER BY id ASC 
                LIMIT 1
            ");
            $stmt->execute();
            $this->ownerData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error loading owner data: " . $e->getMessage());
        }
    }

    private function generateSystemPrompt() {
        $prompt = $this->baseSystemPrompt . "\n\n";
        
        if (!$this->userData) {
            return $prompt . $this->rolePrompts['User'];
        }

        $role = $this->userData['role'] ?? 'User';
        $username = $this->userData['username'] ?? 'User';
        $email = $this->userData['email'] ?? '';
        $joined = $this->userData['created_at'] ?? '';

        $rolePrompt = $this->rolePrompts[$role] ?? $this->rolePrompts['User'];

        $rolePrompt = str_replace(
            ['{USERNAME}', '{USER_EMAIL}', '{USER_JOINED}'],
            [$username, $email, $joined],
            $rolePrompt
        );

        if ($role === 'Owner' && $this->ownerData) {
            $rolePrompt = str_replace(
                ['{OWNER_NAME}', '{OWNER_EMAIL}', '{OWNER_JOINED}'],
                [$this->ownerData['username'], $this->ownerData['email'], $this->ownerData['created_at']],
                $rolePrompt
            );
        } elseif ($role === 'Owner') {
            $rolePrompt = str_replace(
                ['{OWNER_NAME}', '{OWNER_EMAIL}', '{OWNER_JOINED}'],
                [$username, $email, $joined],
                $rolePrompt
            );
        }

        if ($role !== 'Owner' && $this->ownerData) {
            $ownerInfo = "
            
📋 Informasi Developer:
Xenfuri AI dikembangkan oleh {$this->ownerData['username']} ({$this->ownerData['email']}).
Jika Anda menemukan bug atau ingin memberi saran, silakan hubungi developer kami.";
            $rolePrompt .= $ownerInfo;
        }

        return $prompt . $rolePrompt;
    }

    private function initializeTools() {
        $this->tools = [
            'youtube_search' => [
                'name' => 'youtube_search',
                'description' => 'Search for videos on YouTube',
                'api_url' => 'https://apidl.asepharyana.tech/api/search/yt',
                'method' => 'GET'
            ],
            'generate_document' => [
                'name' => 'generate_document',
                'description' => 'Generate Word document with full formatting control',
                'handler' => 'DocumentService'
            ]
        ];
    }

    public function createChat($title = null, $model = 'llama-3.1-70b-versatile') {
        if (!$this->db || !$this->userId) {
            throw new Exception('Database connection or user ID not provided');
        }

        $this->loadUserData();
        $this->loadOwnerData();

        $title = $title ?: 'New Chat';
        
        $stmt = $this->db->prepare("INSERT INTO chats (user_id, title, model) VALUES (?, ?, ?)");
        $stmt->execute([$this->userId, $title, $model]);
        
        $this->chatId = $this->db->lastInsertId();
        
        return $this->chatId;
    }

    private function saveMessage($role, $content, $model = null, $metadata = null) {
        if (!$this->db || !$this->chatId) {
            return false;
        }

        $metadataJson = $metadata ? json_encode($metadata) : null;
        
        $stmt = $this->db->prepare("
            INSERT INTO messages (chat_id, role, content, model, metadata) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $this->chatId, 
            $role, 
            $content, 
            $model, 
            $metadataJson
        ]);
        
        return true;
    }

    private function updateChatTitle($firstMessage) {
        if (!$this->db || !$this->chatId) {
            return false;
        }

        $words = preg_split('/\s+/', $firstMessage);
        $title = implode(' ', array_slice($words, 0, 5));
        if (strlen($title) > 50) {
            $title = substr($title, 0, 47) . '...';
        }
        
        $stmt = $this->db->prepare("UPDATE chats SET title = ? WHERE id = ?");
        $stmt->execute([$title, $this->chatId]);
        
        return true;
    }

    private function getChatHistory($limit = 20) {
        $history = [];
        
        $dynamicSystemPrompt = $this->generateSystemPrompt();
        
        $history[] = [
            'role' => 'system',
            'content' => $dynamicSystemPrompt
        ];
        
        if ($this->db && $this->chatId) {
            $stmt = $this->db->prepare("
                SELECT role, content, model 
                FROM messages 
                WHERE chat_id = ? 
                AND content IS NOT NULL 
                AND content != ''
                ORDER BY created_at ASC 
                LIMIT ?
            ");
            
            $stmt->execute([$this->chatId, $limit]);
            $dbHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($dbHistory as $message) {
                if (!empty($message['content']) && is_string($message['content']) && trim($message['content']) !== '') {
                    $history[] = [
                        'role' => $message['role'],
                        'content' => $message['content']
                    ];
                }
            }
        }
        
        return $history;
    }

    private function validateMessages($messages) {
        $validMessages = [];
        
        foreach ($messages as $message) {
            if (isset($message['role']) && isset($message['content'])) {
                if (is_string($message['content']) && $message['content'] !== '' && trim($message['content']) !== '') {
                    $validMessages[] = [
                        'role' => $message['role'],
                        'content' => trim($message['content'])
                    ];
                }
            }
        }
        
        return $validMessages;
    }

    public function deleteChat() {
        if (!$this->db || !$this->chatId) {
            return false;
        }

        $stmt = $this->db->prepare("DELETE FROM messages WHERE chat_id = ?");
        $stmt->execute([$this->chatId]);
        
        $stmt = $this->db->prepare("DELETE FROM chats WHERE id = ?");
        $stmt->execute([$this->chatId]);
        
        $this->chatId = null;
        
        return true;
    }

    public function getUserChats($limit = 50) {
        if (!$this->db || !$this->userId) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT c.*, 
                   (SELECT content FROM messages m WHERE m.chat_id = c.id AND m.role = 'user' ORDER BY m.created_at ASC LIMIT 1) as first_message,
                   (SELECT COUNT(*) FROM messages m WHERE m.chat_id = c.id) as message_count
            FROM chats c 
            WHERE c.user_id = ? 
            ORDER BY c.updated_at DESC 
            LIMIT ?
        ");
        
        $stmt->execute([$this->userId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getChatWithMessages($chatId = null) {
        if (!$this->db) {
            return null;
        }

        $targetChatId = $chatId ?: $this->chatId;
        if (!$targetChatId) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM chats WHERE id = ?");
        $stmt->execute([$targetChatId]);
        $chat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$chat) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM messages 
            WHERE chat_id = ? 
            AND content IS NOT NULL 
            AND content != ''
            ORDER BY created_at ASC
        ");
        
        $stmt->execute([$targetChatId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'chat' => $chat,
            'messages' => $messages
        ];
    }

    public function chat($messages, $model = 'llama-3.1-70b-versatile', $temperature = 0.7, $maxTokens = null) {
        if (!$this->apiKey) {
            throw new Exception('Groq API key not configured');
        }

        if ($this->db && $this->userId) {
            $this->loadUserData();
            $this->loadOwnerData();
        }

        if (!is_array($messages)) {
            throw new Exception('Messages must be an array');
        }

        $validatedMessages = $this->validateMessages($messages);
        
        if (empty($validatedMessages)) {
            $dynamicSystemPrompt = $this->generateSystemPrompt();
            $validatedMessages = [
                ['role' => 'system', 'content' => $dynamicSystemPrompt],
                ['role' => 'user', 'content' => 'Hello']
            ];
        }

        if (!$this->chatId && $this->db && $this->userId) {
            $this->createChat(null, $model);
            $firstUserMessage = '';
            foreach ($validatedMessages as $msg) {
                if ($msg['role'] === 'user') {
                    $firstUserMessage = $msg['content'];
                    break;
                }
            }
            if ($firstUserMessage) {
                $this->updateChatTitle($firstUserMessage);
            }
        }

        $lastUserMessage = '';
        foreach (array_reverse($validatedMessages) as $msg) {
            if ($msg['role'] === 'user') {
                $lastUserMessage = $msg['content'];
                break;
            }
        }
        
        if ($lastUserMessage && $this->chatId) {
            $this->saveMessage('user', $lastUserMessage, $model);
        }

        $apiMessages = [];
        
        $dynamicSystemPrompt = $this->generateSystemPrompt();
        
        $hasSystemPrompt = false;
        foreach ($validatedMessages as $msg) {
            if ($msg['role'] === 'system') {
                $hasSystemPrompt = true;
                $msg['content'] = $dynamicSystemPrompt;
                break;
            }
        }
        
        if (!$hasSystemPrompt) {
            $apiMessages[] = ['role' => 'system', 'content' => $dynamicSystemPrompt];
        }
        
        foreach ($validatedMessages as $msg) {
            $apiMessages[] = $msg;
        }

        error_log("Messages to send to Groq API: " . json_encode($apiMessages));

        $payload = [
            'model' => $model,
            'messages' => $apiMessages,
            'temperature' => $temperature,
            'stream' => false
        ];

        if ($maxTokens) {
            $payload['max_tokens'] = $maxTokens;
        }

        $ch = curl_init($this->baseUrl);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Curl error: ' . $error);
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            $errorMsg = $error['error']['message'] ?? "HTTP Error: {$httpCode}";
            throw new Exception($errorMsg . " | Response: " . $response);
        }

        $data = json_decode($response, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response from API: ' . json_encode($data));
        }
        
        $aiResponse = $data['choices'][0]['message']['content'];
        
        $parsedResponse = $this->parseSpecialActions($aiResponse);
        
        $executedActions = [];
        $actionMetadata = [];
        
        if (!empty($parsedResponse['actions'])) {
            foreach ($parsedResponse['actions'] as $action) {
                $executedAction = $this->executeAction($action);
                if ($executedAction) {
                    $executedActions[] = $executedAction;
                    $actionMetadata[] = [
                        'type' => $action['type'],
                        'params' => $action['params'],
                        'result' => $executedAction
                    ];
                }
            }
        }
        
        $finalContent = $this->combineContentWithActions($parsedResponse['content'], $executedActions);
        
        $metadata = [
            'api_model' => $data['model'],
            'usage' => $data['usage'] ?? null,
            'finish_reason' => $data['choices'][0]['finish_reason'],
            'actions' => $actionMetadata,
            'user_role' => $this->userData['role'] ?? 'unknown',
            'user_username' => $this->userData['username'] ?? 'unknown'
        ];
        
        $this->saveMessage('assistant', $finalContent, $model, $metadata);
        
        if ($this->db && $this->chatId) {
            $stmt = $this->db->prepare("UPDATE chats SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$this->chatId]);
        }
        
        return [
            'content' => $finalContent,
            'model' => $data['model'],
            'usage' => $data['usage'] ?? null,
            'finish_reason' => $data['choices'][0]['finish_reason'],
            'actions' => $parsedResponse['actions'],
            'executed_actions' => $executedActions,
            'chat_id' => $this->chatId,
            'metadata' => $metadata,
            'user_info' => [
                'role' => $this->userData['role'] ?? null,
                'username' => $this->userData['username'] ?? null
            ]
        ];
    }

    public function streamChat($userMessage, $model = 'llama-3.1-70b-versatile', callable $onChunk = null) {
        if (empty($userMessage) || trim($userMessage) === '') {
            throw new Exception('User message cannot be empty');
        }

        if ($this->db && $this->userId) {
            $this->loadUserData();
            $this->loadOwnerData();
        }

        if (!$this->chatId && $this->db && $this->userId) {
            $this->createChat(null, $model);
            $this->updateChatTitle($userMessage);
        }

        $this->saveMessage('user', $userMessage, $model);
        
        $messages = $this->getChatHistory();
        
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];

        $validatedMessages = $this->validateMessages($messages);

        $payload = [
            'model' => $model,
            'messages' => $validatedMessages,
            'temperature' => 0.7,
            'stream' => true
        ];

        $fullResponse = '';
        $ch = curl_init($this->baseUrl);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: text/event-stream'
        ]);
        
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($onChunk, &$fullResponse, $model) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'data: ') === 0) {
                    $json = substr($line, 6);
                    if ($json === '[DONE]') {
                        $parsedResponse = $this->parseSpecialActions($fullResponse);
                        
                        $executedActions = [];
                        $actionMetadata = [];
                        
                        if (!empty($parsedResponse['actions'])) {
                            foreach ($parsedResponse['actions'] as $action) {
                                $executedAction = $this->executeAction($action);
                                if ($executedAction) {
                                    $executedActions[] = $executedAction;
                                    $actionMetadata[] = [
                                        'type' => $action['type'],
                                        'params' => $action['params'],
                                        'result' => $executedAction
                                    ];
                                }
                            }
                        }
                        
                        $finalContent = $this->combineContentWithActions($parsedResponse['content'], $executedActions);
                        
                        $metadata = [
                            'streaming' => true,
                            'actions' => $actionMetadata,
                            'user_role' => $this->userData['role'] ?? 'unknown',
                            'user_username' => $this->userData['username'] ?? 'unknown'
                        ];
                        
                        $this->saveMessage('assistant', $finalContent, $model, $metadata);
                        
                        if ($this->db && $this->chatId) {
                            $stmt = $this->db->prepare("UPDATE chats SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                            $stmt->execute([$this->chatId]);
                        }
                        
                        if ($onChunk) {
                            $onChunk($finalContent, true, $parsedResponse['actions'], $executedActions);
                        }
                        continue;
                    }
                    
                    $chunk = json_decode($json, true);
                    if ($chunk && isset($chunk['choices'][0]['delta']['content'])) {
                        $content = $chunk['choices'][0]['delta']['content'];
                        $fullResponse .= $content;
                        if ($onChunk) {
                            $onChunk($content, false, [], []);
                        }
                    }
                }
            }
            return strlen($data);
        });

        curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if (!empty($curlError)) {
            throw new Exception('Curl stream error: ' . $curlError);
        }
        
        return $fullResponse;
    }

    private function parseSpecialActions($content) {
        $actions = [];
        $cleanedContent = $content;
        
        if (!is_string($content)) {
            $content = is_array($content) ? json_encode($content) : (string)$content;
        }
        
        $pattern = '/\{action:document,\s*html:"(.*?)",\s*(.+?)\}/s';
        
        if (preg_match($pattern, $content, $match)) {
            $fullAction = $match[0];
            $html = $match[1];
            $paramsString = $match[2];
            
            $params = [];
            
            if (preg_match('/filename:\s*"([^"]+)"/', $paramsString, $m)) {
                $params['filename'] = $m[1];
            }
            
            if (preg_match('/title:\s*"([^"]+)"/', $paramsString, $m)) {
                $params['title'] = $m[1];
            }
            
            if (preg_match('/pageSize:\s*"([^"]+)"/', $paramsString, $m)) {
                $params['pageSize'] = $m[1];
            }
            
            if (preg_match('/orientation:\s*"([^"]+)"/', $paramsString, $m)) {
                $params['orientation'] = $m[1];
            }
            
            $params['html'] = stripslashes($html);
            
            $actions[] = [
                'type' => 'document',
                'params' => $params
            ];
            
            $cleanedContent = str_replace($fullAction, '', $content);
        }
        
        if (preg_match_all('/\{action:img,\s*([^}]+)\}/', $cleanedContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params = [];
                if (preg_match('/prompt:\s*"([^"]+)"/', $match[1], $m)) {
                    $params['prompt'] = $m[1];
                }
                $actions[] = ['type' => 'img', 'params' => $params];
                $cleanedContent = str_replace($match[0], '', $cleanedContent);
            }
        }
        
        if (preg_match_all('/\{action:youtube_search,\s*([^}]+)\}/', $cleanedContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params = [];
                if (preg_match('/query:\s*"([^"]+)"/', $match[1], $m)) {
                    $params['query'] = $m[1];
                }
                $actions[] = ['type' => 'youtube_search', 'params' => $params];
                $cleanedContent = str_replace($match[0], '', $cleanedContent);
            }
        }
        
        $cleanedContent = trim($cleanedContent);
        
        return [
            'content' => $cleanedContent,
            'actions' => $actions
        ];
    }

    private function parseActionParams($paramsString) {
        $params = [];
        
        if (preg_match('/prompt:\s*"([^"]+)"/', $paramsString, $promptMatch)) {
            $params['prompt'] = $promptMatch[1];
        }
        
        if (preg_match('/query:\s*"([^"]+)"/', $paramsString, $queryMatch)) {
            $params['query'] = $queryMatch[1];
        }
        
        if (preg_match('/filename:\s*"([^"]+)"/', $paramsString, $filenameMatch)) {
            $params['filename'] = $filenameMatch[1];
        }
        
        if (preg_match('/title:\s*"([^"]+)"/', $paramsString, $titleMatch)) {
            $params['title'] = $titleMatch[1];
        }
        
        if (preg_match('/content:\s*"([^"]+)"/', $paramsString, $contentMatch)) {
            $params['content'] = $contentMatch[1];
        }
        
        if (preg_match('/structure:\s*"([^"]+)"/', $paramsString, $structureMatch)) {
            $params['structure'] = json_decode($structureMatch[1], true);
        }
        
        if (preg_match('/format:\s*"([^"]+)"/', $paramsString, $formatMatch)) {
            $params['format'] = $formatMatch[1];
        }
        
        if (preg_match('/width:\s*(\d+)/', $paramsString, $widthMatch)) {
            $params['width'] = (int)$widthMatch[1];
        }
        if (preg_match('/height:\s*(\d+)/', $paramsString, $heightMatch)) {
            $params['height'] = (int)$heightMatch[1];
        }
        if (preg_match('/limit:\s*(\d+)/', $paramsString, $limitMatch)) {
            $params['limit'] = (int)$limitMatch[1];
        }
        
        return $params;
    }

    private function executeAction($action) {
        switch ($action['type']) {
            case 'youtube_search':
                return $this->executeYoutubeSearch($action['params']);
                
            case 'document':
                return $this->executeDocumentGeneration($action['params']);
                
            default:
                return null;
        }
    }

    private function executeDocumentGeneration($params) {
        try {
            require_once __DIR__ . '/DocumentService.php';
            
            if (!isset($params['html']) || empty($params['html'])) {
                return [
                    'type' => 'document',
                    'success' => false,
                    'error' => 'Parameter html tidak ditemukan'
                ];
            }
            
            $docService = new DocumentService();
            
            $filename = $params['filename'] ?? null;
            
            $options = [
                'paperSize' => $params['pageSize'] ?? 'A4',
                'orientation' => $params['orientation'] ?? 'portrait'
            ];
            
            $result = $docService->generatePdf(
                $params['html'],
                $filename,
                $options
            );
            
            if ($result['success']) {
                return [
                    'type' => 'document',
                    'success' => true,
                    'filename' => $result['filename'],
                    'url' => $result['url'],
                    'size' => $result['size'],
                    'pages' => $result['pages'],
                    'format' => 'pdf'
                ];
            } else {
                return [
                    'type' => 'document',
                    'success' => false,
                    'error' => $result['error'] ?? 'Gagal membuat PDF'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'type' => 'document',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function executeYoutubeSearch($params) {
        if (!isset($params['query'])) {
            return null;
        }
        
        $query = urlencode($params['query']);
        $limit = $params['limit'] ?? 5;
        $apiUrl = $this->tools['youtube_search']['api_url'] . "?query=" . $query;
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            return [
                'type' => 'youtube_search',
                'success' => false,
                'error' => 'Failed to fetch YouTube data',
                'query' => $params['query']
            ];
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['videos']) || empty($data['videos'])) {
            return [
                'type' => 'youtube_search',
                'success' => false,
                'error' => 'No videos found',
                'query' => $params['query']
            ];
        }
        
        $videos = array_slice($data['videos'], 0, $limit);
        
        return [
            'type' => 'youtube_search',
            'success' => true,
            'query' => $params['query'],
            'total' => $data['total'] ?? count($videos),
            'videos' => $videos
        ];
    }

    private function combineContentWithActions($content, $executedActions) {
        if (!is_string($content)) {
            $content = is_array($content) ? json_encode($content) : (string)$content;
        }
        
        $combinedContent = $content;
        
        foreach ($executedActions as $action) {
            if ($action['type'] === 'youtube_search' && $action['success']) {
                $youtubeContent = $this->formatYoutubeResults($action);
                $combinedContent .= "\n\n" . $youtubeContent;
            } elseif ($action['type'] === 'document' && $action['success']) {
                $docContent = "\n\n📄 Dokumen Berhasil Dibuat!\n\n";
                $docContent .= "📁 Nama File: {$action['filename']}\n";
                $docContent .= "📊 Ukuran: " . $this->formatFileSize($action['size']) . "\n";
                $docContent .= "🔗 Format: " . strtoupper($action['format']) . "\n";
                $docContent .= "\n⬇️ Download: Klik di sini untuk mengunduh - {$action['url']}";
                $docContent .= "\n\n{type:document, url:\"{$action['url']}\", filename:\"{$action['filename']}\", size:\"{$action['size']}\", format:\"{$action['format']}\"}";
                
                $combinedContent .= $docContent;
            }
        }
        
        return trim($combinedContent);
    }

    private function formatYoutubeResults($action) {
        $output = "📺 Hasil Pencarian YouTube untuk \"{$action['query']}\":\n\n";
        
        if (isset($action['videos'])) {
            foreach ($action['videos'] as $video) {
                if (isset($video['url']) && isset($video['title'])) {
                    $title = $this->escapeString($video['title']);
                    $author = isset($video['author']['name']) ? $this->escapeString($video['author']['name']) : 'Unknown';
                    $thumbnail = isset($video['thumbnail']) ? $video['thumbnail'] : '';
                    $views = isset($video['views']) ? $this->formatViews($video['views']) : '0';
                    $duration = isset($video['duration']['timestamp']) ? $video['duration']['timestamp'] : '0:00';
                    
                    $output .= "{type:youtube, url:\"{$video['url']}\", thumbnail:\"{$thumbnail}\", title:\"{$title}\", author:\"{$author}\", views:\"{$views}\", duration:\"{$duration}\"}\n\n";
                }
            }
        }
        
        $output .= "🎬 Jika ingin menonton, klik banner/card di atas!";
        
        return $output;
    }

    private function escapeString($string) {
        if (!is_string($string)) {
            $string = (string)$string;
        }
        return addcslashes($string, '"\\');
    }

    private function formatViews($views) {
        if (!is_numeric($views)) {
            return '0';
        }
        
        $views = (int)$views;
        if ($views >= 1000000) {
            return round($views / 1000000, 1) . 'M';
        } elseif ($views >= 1000) {
            return round($views / 1000, 1) . 'K';
        }
        return (string)$views;
    }

    public function setChatId($chatId) {
        $this->chatId = $chatId;
        return $this;
    }

    public function setUserId($userId) {
        $this->userId = $userId;
        if ($this->db) {
            $this->loadUserData();
            $this->loadOwnerData();
        }
        return $this;
    }

    public function getUserData() {
        return $this->userData;
    }

    public function getOwnerData() {
        return $this->ownerData;
    }

    public function chatExists($chatId) {
        if (!$this->db) {
            return false;
        }
        
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM chats WHERE id = ?");
        $stmt->execute([$chatId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }

    public function debugMessages($userMessage, $model = 'llama-3.1-70b-versatile') {
        if ($this->db && $this->userId) {
            $this->loadUserData();
            $this->loadOwnerData();
        }

        $messages = $this->getChatHistory();
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];
        
        $validatedMessages = $this->validateMessages($messages);
        
        return [
            'raw_messages' => $messages,
            'validated_messages' => $validatedMessages,
            'count' => count($validatedMessages),
            'user_data' => $this->userData,
            'owner_data' => $this->ownerData,
            'generated_system_prompt' => $this->generateSystemPrompt()
        ];
    }

    private function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    public function refreshUserData() {
        $this->loadUserData();
        $this->loadOwnerData();
        return $this;
    }
}