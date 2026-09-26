<?php

require_once __DIR__ . '/../src/autoload.php';

use App\Services\OllamaClient;

echo "=== Testing Ollama AI Integration ===\n";

// 1. Check availability
echo "1. Checking Ollama health status... ";
$available = OllamaClient::isAvailable();
echo ($available ? "[PASS] Online" : "[FAIL] Offline") . "\n";

if (!$available) {
    echo "Ollama is not available on http://localhost:11434. Halting test.\n";
    exit(1);
}

// 2. Test suggestCategory
echo "2. Testing suggestCategory('The kitchen sink is clogged and overflowing water')...\n";
$catResult = OllamaClient::suggestCategory('The kitchen sink is clogged and overflowing water');
echo "   Category detected: " . json_encode($catResult) . "\n";
if ($catResult['ok'] && in_array($catResult['reply'], ['plumbing', 'electrical', 'structural', 'appliance', 'other'])) {
    echo "   [PASS] Valid category returned: " . $catResult['reply'] . "\n";
} else {
    echo "   [WARN/FAIL] Unexpected category output: " . json_encode($catResult) . "\n";
}

// 3. Test suggestDescription
echo "3. Testing suggestDescription('sparking outlet near bed', 'electrical')...\n";
$descResult = OllamaClient::suggestDescription('sparking outlet near bed', 'electrical');
echo "   Improved text: " . ($descResult['reply'] ?? 'none') . "\n";
if ($descResult['ok'] && strlen($descResult['reply']) > 15) {
    echo "   [PASS] Description successfully expanded by AI\n";
} else {
    echo "   [WARN/FAIL] Failed description expansion\n";
}

// 4. Test chat with boarder context
echo "4. Testing chat('What room am I in?', ['name' => 'Kierb', 'room' => '204-A'])...\n";
$chatResult = OllamaClient::chat('What room am I in?', ['name' => 'Kierb', 'room' => '204-A']);
echo "   Chat response: " . ($chatResult['reply'] ?? 'none') . "\n";
if ($chatResult['ok'] && str_contains($chatResult['reply'], '204')) {
    echo "   [PASS] AI accurately referenced boarder context\n";
} else {
    echo "   [INFO] AI response received: " . ($chatResult['reply'] ?? '') . "\n";
}

// 5. Test queue summary
echo "5. Testing summarizeQueue()...\n";
$mockQueue = [
    [
        'id' => 1,
        'category' => 'electrical',
        'description' => 'Power outlet is sparking and smells like burning plastic',
        'priority_tier' => 'critical',
        'boarder_name' => 'Juan Dela Cruz',
        'room_number' => '102'
    ],
    [
        'id' => 2,
        'category' => 'plumbing',
        'description' => 'Faucet drip in bathroom sink',
        'priority_tier' => 'low',
        'boarder_name' => 'Maria Santos',
        'room_number' => '205'
    ]
];
$summaryResult = OllamaClient::summarizeQueue($mockQueue);
echo "   Summary: " . ($summaryResult['reply'] ?? 'none') . "\n";
if ($summaryResult['ok']) {
    echo "   [PASS] Queue summary generated successfully\n";
} else {
    echo "   [FAIL] Queue summary failed: " . ($summaryResult['error'] ?? '') . "\n";
}

// 6. Test analyzeTicket
echo "6. Testing analyzeTicket()...\n";
$mockTicket = [
    'id' => 101,
    'category' => 'electrical',
    'description' => 'Breaker keeps tripping when microwave and electric kettle run together in pantry',
    'priority_tier' => 'high',
    'room_number' => 'Building A Pantry',
    'boarder_name' => 'Staff Test'
];
$analysisResult = OllamaClient::analyzeTicket($mockTicket);
echo "   Analysis: " . ($analysisResult['reply'] ?? 'none') . "\n";
if ($analysisResult['ok']) {
    echo "   [PASS] Ticket analysis generated successfully\n";
} else {
    echo "   [FAIL] Ticket analysis failed: " . ($analysisResult['error'] ?? '') . "\n";
}

echo "=== All Direct Ollama Tests Finished ===\n";
