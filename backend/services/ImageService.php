<?php

class ImageService {
    private $baseUrl = 'https://apidl.asepharyana.tech/api/ai/flux-schnell';
    
    
    public function generateImage($prompt, $options = []) {
       
        $defaultOptions = [
            'negative_prompt' => 'ugly, blurry, low quality, distorted, bad anatomy',
            'width' => 512,
            'height' => 512,
            'steps' => 20,
            'cfg_scale' => 7.5
        ];
        
        $options = array_merge($defaultOptions, $options);
        
       
        $queryParams = [
            'prompt' => $prompt,
            'negative_prompt' => $options['negative_prompt'],
            'width' => $options['width'],
            'height' => $options['height'],
            'steps' => $options['steps'],
            'cfg_scale' => $options['cfg_scale']
        ];
        
        $url = $this->baseUrl . '?' . http_build_query($queryParams);
        
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
       
        curl_setopt($ch, CURLOPT_USERAGENT, 'Xenfuri-AI/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Curl error: ' . $error);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Image API Error: HTTP {$httpCode} - " . substr($response, 0, 100));
        }
        
       
        $mimeType = 'image/png';
        if (strpos($contentType, 'image/jpeg') !== false) {
            $mimeType = 'image/jpeg';
        } elseif (strpos($contentType, 'image/webp') !== false) {
            $mimeType = 'image/webp';
        }
        
       
        $filename = 'img_' . md5($prompt . time()) . 
                   ($mimeType === 'image/jpeg' ? '.jpg' : 
                   ($mimeType === 'image/webp' ? '.webp' : '.png'));
        
       
        return [
            'success' => true,
            'image_base64' => base64_encode($response),
            'image_url' => 'data:' . $mimeType . ';base64,' . base64_encode($response),
            'mime_type' => $mimeType,
            'filename' => $filename,
            'prompt' => $prompt,
            'options' => $options,
            'size' => strlen($response),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    
    public function generateMultiple($prompts, $options = []) {
        $results = [];
        
        foreach ($prompts as $index => $prompt) {
            try {
                $result = $this->generateImage($prompt, $options);
                $result['index'] = $index;
                $results[] = $result;
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'prompt' => $prompt,
                    'index' => $index
                ];
            }
            
           
            if ($index < count($prompts) - 1) {
                sleep(1);
            }
        }
        
        return $results;
    }
    
    
    public function validatePrompt($prompt) {
        $blacklist = [
            'nude', 'naked', 'explicit', 'porn', 'sex', 'hentai',
            'violence', 'gore', 'blood', 'kill', 'murder',
            'hate', 'racist', 'discriminatory'
        ];
        
        $promptLower = strtolower($prompt);
        
        foreach ($blacklist as $word) {
            if (strpos($promptLower, $word) !== false) {
                return [
                    'valid' => false,
                    'reason' => "Prompt mengandung konten yang tidak diizinkan: {$word}"
                ];
            }
        }
        
        return ['valid' => true];
    }
}