<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/autoload.php';

use App\Config\Database;
use App\Controllers\ChatController;

$db = (new Database())->getConnection();
$chat = new ChatController($db);

$reflection = new ReflectionClass($chat);
$apiKeyProp = $reflection->getProperty('apiKey');
$apiKeyProp->setAccessible(true);
$apiKey = $apiKeyProp->getValue($chat);

$modelProp = $reflection->getProperty('model');
$modelProp->setAccessible(true);
$model = $modelProp->getValue($chat);

echo "Testing OpenRouter API...\n";
echo "Model: $model\n";
echo "API Key (first 10 chars): " . substr($apiKey, 0, 10) . "...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://openrouter.ai/api/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Debug: Disable SSL verification temporarily

$data = [
    "model" => $model,
    "messages" => [
        ["role" => "user", "content" => "Oi, teste rápido."]
    ]
];

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$headers = [
    "Authorization: Bearer " . $apiKey,
    "Content-Type: application/json",
    "HTTP-Referer: http://localhost",
    "X-Title: Library Vision System Test"
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$info = curl_getinfo($ch);

if (curl_errno($ch)) {
    echo "CURL ERROR: " . curl_error($ch) . "\n";
} else {
    echo "HTTP CODE: " . $info['http_code'] . "\n";
    echo "RESPONSE: " . $response . "\n";
}

curl_close($ch);
