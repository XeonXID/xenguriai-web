<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../models/Model.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}


$user = AuthMiddleware::optional();

$modelObj = new AIModel();
$models = $modelObj->getAllActive();


$grouped = [];
foreach ($models as $model) {
    $provider = $model['provider'];
    if (!isset($grouped[$provider])) {
        $grouped[$provider] = [];
    }
    $grouped[$provider][] = $model;
}

Response::success([
    'models' => $models,
    'grouped' => $grouped,
    'providers' => array_keys($grouped)
]);