<?php

$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php'
];

$autoloadLoaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloadLoaded = true;
        break;
    }
}

if (!$autoloadLoaded) {
    die('Error: Composer autoload not found. Run: composer require dompdf/dompdf');
}

use Dompdf\Dompdf;
use Dompdf\Options;

class DocumentService {
    private $documentPath;
    private $baseUrl;
    
    public function __construct($baseUrl = null) {
        $this->baseUrl = $baseUrl ?: $this->detectBaseUrl();
        $this->documentPath = __DIR__ . '/../storage/documents/';
        
       
        if (!is_dir($this->documentPath)) {
            mkdir($this->documentPath, 0755, true);
        }
    }
    
    
    private function detectBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
    
    
    public function generatePdf($html, $filename = null, $options = []) {
        try {
           
            if (empty($html)) {
                throw new Exception('HTML content is empty');
            }
            
           
            
           
            $html = preg_replace("/style='([^']*)'/", 'style="$1"', $html);
            
           
            $html = str_replace(
                'page-break-after: always;',
                'page-break-after: always !important;',
                $html
            );
            
           
            $cssFix = '<style>
                
                div[style*="page-break-after"] {
                    page-break-after: always !important;
                }
                .page-break {
                    page-break-after: always !important;
                }
                body {
                    font-family: ' . ($options['font'] ?? 'Arial') . ', sans-serif;
                    font-size: 12pt;
                    line-height: 1.5;
                    margin: 2.5cm;
                }
                h1 { color: #2E74B5; }
                h2 { color: #2E74B5; }
            </style>';
            
           
            if (stripos($html, '</head>') !== false) {
                $html = str_replace('</head>', $cssFix . '</head>', $html);
            } else {
                $html = str_replace('<html>', "<html>\n<head>\n<meta charset=\"UTF-8\">\n{$cssFix}\n</head>", $html);
            }
            
           
            if (stripos($html, '<!DOCTYPE') === false) {
                $html = '<!DOCTYPE html>' . "\n" . $html;
            }
            
           
            if (stripos($html, '<body') === false) {
                $html = str_replace('</head>', '</head><body>', $html);
                $html = str_replace('</html>', '</body></html>', $html);
            }
            
           
            
            if (!class_exists('Dompdf\\Dompdf')) {
                throw new Exception('Dompdf library not found');
            }
            
            $pdfOptions = new Options();
            $pdfOptions->set('isRemoteEnabled', true);
            $pdfOptions->set('isHtml5ParserEnabled', true);
            $pdfOptions->set('isPhpEnabled', false);
            $pdfOptions->set('defaultFont', $options['font'] ?? 'Arial');
            $pdfOptions->set('tempDir', sys_get_temp_dir());
            
            $dompdf = new Dompdf($pdfOptions);
            
           
            $dompdf->loadHtml($html, 'UTF-8');
            
           
            $paperSize = $options['paperSize'] ?? 'A4';
            $orientation = $options['orientation'] ?? 'portrait';
            $dompdf->setPaper($paperSize, $orientation);
            
           
            $dompdf->render();
            
           
            if (!$filename) {
                $filename = $options['filename'] ?? 'dokumen_' . date('Ymd_His');
            }
            
           
            $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filename);
            $filename = $filename ?: 'dokumen';
            $filename = $filename . '.pdf';
            $fullPath = $this->documentPath . $filename;
            
           
            file_put_contents($fullPath, $dompdf->output());
            
           
            $canvas = $dompdf->getCanvas();
            $pageCount = $canvas ? $canvas->get_page_count() : 1;
            $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $fullPath,
                'url' => $this->baseUrl . '/storage/documents/' . $filename,
                'size' => $fileSize,
                'pages' => $pageCount,
                'format' => 'pdf'
            ];
            
        } catch (Exception $e) {
            error_log('PDF Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    
    public function formatBytes($bytes, $precision = 2) {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}