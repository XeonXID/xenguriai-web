<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

class TTSService {
    private $apiKey;
    private $apiUrl = 'https://api.groq.com/openai/v1/audio/speech';
    private $cacheDir;
    
    public function __construct($apiKey = null) {
       
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        
       
        $this->apiKey = $apiKey ?: ($_ENV['GROQ_API_KEY'] ?? '');
        
        if (empty($this->apiKey)) {
            throw new Exception('GROQ_API_KEY not found in environment variables');
        }
        
       
        $this->cacheDir = __DIR__ . '/../storage/tts_cache/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    
    public function generateSpeech($text, $options = []) {
        if (empty($text)) {
            throw new Exception('Text cannot be empty');
        }

        $model = $options['model'] ?? 'canopylabs/orpheus-v1-english';
        $voice = $options['voice'] ?? 'autumn';
        $format = 'wav'; 
        $speed = $options['speed'] ?? 1.0;

       
        $cleanText = $this->cleanAIText($text);
        
        if (empty($cleanText)) {
            throw new Exception('Text is empty after cleaning');
        }

       
        $cacheKey = md5($cleanText . $voice . $model . $speed);
        $cacheFile = $this->cacheDir . $cacheKey . '.' . $format;

       
        if (file_exists($cacheFile)) {
            $audioData = file_get_contents($cacheFile);
            return [
                'success' => true,
                'audio_data' => base64_encode($audioData),
                'content_type' => 'audio/wav',
                'format' => 'wav',
                'audio_size' => strlen($audioData),
                'cached' => true,
                'text' => $cleanText,
                'voice' => $voice,
                'model' => $model
            ];
        }

       
        $payload = [
            'model' => $model,
            'voice' => $voice,
            'input' => $cleanText,
            'response_format' => $format,
            'speed' => (float)$speed
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Curl error: ' . $error);
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            $err = json_decode($audioData, true);
            $errorMsg = $err['error']['message'] ?? "API Error: HTTP {$httpCode}";
            throw new Exception($errorMsg);
        }

       
        if (empty($audioData)) {
            throw new Exception('Empty audio data received from API');
        }

       
        file_put_contents($cacheFile, $audioData);

        return [
            'success' => true,
            'audio_data' => base64_encode($audioData),
            'content_type' => 'audio/wav',
            'format' => 'wav',
            'audio_size' => strlen($audioData),
            'cached' => false,
            'text' => $cleanText,
            'voice' => $voice,
            'model' => $model
        ];
    }

    
    public function cleanAIText($text) {
        if (empty($text)) {
            return '';
        }

       
        $text = preg_replace('/```[\s\S]*?```/', '', $text);
        
       
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        
       
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.*?)\*/', '$1', $text);
        $text = preg_replace('/_(.*?)_/', '$1', $text);
        $text = preg_replace('/~~(.*?)~~/', '$1', $text);
        
       
        $text = preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $text);
        
       
        $text = strip_tags($text);
        
       
        $text = preg_replace('/\{action:[^}]+\}/', '', $text);
        $text = preg_replace('/\{type:[^}]+\}/', '', $text);
        
       
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n+/', '. ', $text);
        
       
        $text = preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}]/u', '', $text);
        
       
        $text = trim($text);
        if (!empty($text) && !in_array(substr($text, -1), ['.', '!', '?'])) {
            $text .= '.';
        }
        
        return $text;
    }

    
    public function getAvailableVoices() {
        return [
            'autumn' => 'Autumn - Female, Warm',
            'nova' => 'Nova - Female, Energetic',
            'alloy' => 'Alloy - Neutral',
            'echo' => 'Echo - Male, Deep',
            'fable' => 'Fable - Male, British',
            'onyx' => 'Onyx - Male, Authoritative',
            'shimmer' => 'Shimmer - Female, Clear'
        ];
    }

    
    public function clearCache($days = 7) {
        $count = 0;
        $files = glob($this->cacheDir . '*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($days === 0 || ($now - filemtime($file)) > ($days * 86400)) {
                    if (unlink($file)) {
                        $count++;
                    }
                }
            }
        }
        
        return $count;
    }

    
    public function getCacheStats() {
        $files = glob($this->cacheDir . '*');
        $totalSize = 0;
        $fileCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                $fileCount++;
            }
        }
        
        return [
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'cache_dir' => $this->cacheDir
        ];
    }

    private function formatBytes($bytes) {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}