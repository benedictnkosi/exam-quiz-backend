<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load('.env');

echo "🤖 AI Accounting Question Generator Test\n";
echo "========================================\n\n";

// Test 1: Generate a single tap-to-select question
echo "Test 1: Generating a single tap-to-select question...\n";
$data = [
    'topic' => 'Financial Statements - Income Statement',
    'level' => 'Level 1: Basics',
    'questionType' => 'tap-to-select'
];

$jsonData = json_encode($data);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/accounting-questions/generate/single');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    echo "✅ Success!\n";
    echo "Question ID: " . $result['data']['id'] . "\n";
    echo "Prompt: " . $result['data']['prompt'] . "\n";
    echo "Answer: " . $result['data']['answer'] . "\n";
    echo "Token Usage: " . $result['token_usage']['total_tokens'] . "\n\n";
} else {
    echo "❌ Failed with HTTP code: $httpCode\n";
    echo "Response: $response\n\n";
}

// Test 2: Generate multiple questions
echo "Test 2: Generating multiple questions...\n";
$data = [
    'topic' => 'Financial Statements - Income Statement',
    'level' => 'Level 1: Basics',
    'count' => 2,
    'questionTypes' => ['tap-to-select', 'categorise']
];

$jsonData = json_encode($data);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/accounting-questions/generate/multiple');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    echo "✅ Success!\n";
    echo "Total Requested: " . $result['data']['total_requested'] . "\n";
    echo "Successful: " . $result['data']['successful'] . "\n";
    echo "Failed: " . $result['data']['failed'] . "\n\n";
    
    foreach ($result['data']['results'] as $index => $questionResult) {
        if ($questionResult['success']) {
            $question = $questionResult['question'];
            echo "Question " . ($index + 1) . ":\n";
            echo "  ID: " . $question['id'] . "\n";
            echo "  Type: " . $question['type'] . "\n";
            echo "  Prompt: " . $question['prompt'] . "\n";
            echo "  Tokens: " . $questionResult['token_usage']['total_tokens'] . "\n\n";
        } else {
            echo "Question " . ($index + 1) . " failed: " . $questionResult['error'] . "\n\n";
        }
    }
} else {
    echo "❌ Failed with HTTP code: $httpCode\n";
    echo "Response: $response\n\n";
}

echo "🎉 Test completed!\n"; 