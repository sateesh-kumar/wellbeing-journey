<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require auth (returns 401 JSON instead of redirecting)
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

// Read and validate input
$input  = json_decode(file_get_contents('php://input'), true);
$prompt = trim($input['prompt'] ?? '');

if (!$prompt) {
    echo json_encode(['error' => 'No prompt provided']);
    exit;
}

// Get API key from .env — never hardcoded
$apiKey = env('ANTHROPIC_API_KEY');

if (!$apiKey) {
    error_log('ANTHROPIC_API_KEY is not set in .env');
    echo json_encode(['error' => 'API key not configured']);
    exit;
}

// Call Anthropic API
$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 75,
    'messages'   => [['role' => 'user', 'content' => $prompt]],
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 20,
    // Only disable SSL in development
    CURLOPT_SSL_VERIFYPEER => env('APP_ENV') !== 'development',
]);

$response  = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    error_log('Anthropic API curl error: ' . $curlError);
    echo json_encode(['error' => 'Request failed']);
    exit;
}

if ($httpCode !== 200) {
    error_log('Anthropic API returned HTTP ' . $httpCode . ': ' . $response);
    echo json_encode(['error' => 'API error']);
    exit;
}

$decoded = json_decode($response, true);
$summary = $decoded['content'][0]['text'] ?? '';

if (!$summary) {
    echo json_encode(['error' => 'Unable to generate summary']);
    exit;
}

echo json_encode(['summary' => $summary]);
