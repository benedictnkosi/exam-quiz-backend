<?php
/**
 * Example script to demonstrate the Politician Connections API
 * 
 * This script shows how to use the GET /api/politicians/connections endpoint
 * to retrieve connections between politicians based on their involvement in scandals.
 * 
 * Usage: php example_politician_connections.php
 */

// Configuration
$baseUrl = 'http://localhost:3000'; // Change this to your API base URL
$politicianId = 123; // Change this to test with different politician IDs (optional, set to null for all)

echo "🔗 Politician Connections API Example\n";
echo "====================================\n\n";

/**
 * Make HTTP request to the API
 */
function makeRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    
    if ($data && $method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("cURL error: " . $error);
    }
    
    return [
        'status' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

try {
    // Build the URL based on whether politician ID filter is provided
    if (!empty($politicianId)) {
        echo "📊 Analyzing politician connections for ID: {$politicianId}\n";
        echo "Requesting: GET {$baseUrl}/politicians/connections?politician_id={$politicianId}\n\n";
        $url = $baseUrl . '/politicians/connections?politician_id=' . $politicianId;
    } else {
        echo "📊 Analyzing all politician connections\n";
        echo "Requesting: GET {$baseUrl}/politicians/connections\n\n";
        $url = $baseUrl . '/politicians/connections';
    }
    
    // Make the API request
    $result = makeRequest($url);
    
    if ($result['status'] === 200) {
        $data = $result['data'];
        
        echo "✅ Success! Found {$data['total_connections']} connections from {$data['total_scandals_analyzed']} scandals\n";
        echo "Message: {$data['message']}\n\n";
        
        if (!empty($data['politician_name_filter'])) {
            echo "🎯 Filtered by politician: {$data['politician_name_filter']} (ID: {$data['politician_id_filter']})\n\n";
        }
        
        if (empty($data['connections'])) {
            echo "📝 No connections found. This could mean:\n";
            echo "   - No scandals exist in the database\n";
            echo "   - Scandals don't have involved persons data\n";
            echo "   - Politicians are not connected through scandals\n";
            if (!empty($politicianId)) {
                echo "   - The specified politician ID is not involved in any scandals\n";
                echo "   - The politician ID does not exist in the database\n";
            }
            echo "\n";
        } else {
            echo "🔗 Politician Connections:\n";
            echo "========================\n\n";
            
            foreach ($data['connections'] as $index => $connection) {
                $strength = $connection['connection_strength'];
                $scandalCount = count($connection['scandals']);
                
                echo ($index + 1) . ". {$connection['politician1']} ↔ {$connection['politician2']}\n";
                echo "   Connection Strength: {$strength} (shared scandals)\n";
                echo "   Scandals: {$scandalCount}\n\n";
                
                foreach ($connection['scandals'] as $scandal) {
                    echo "   📰 {$scandal['title']} ({$scandal['year']})\n";
                    echo "      Description: {$scandal['description']}\n";
                    echo "      {$scandal['involved_person']} - {$scandal['role']} ({$scandal['position']})\n";
                    echo "      Country: {$scandal['country']}\n\n";
                }
                
                echo "   " . str_repeat("-", 50) . "\n\n";
            }
            
            // Show some statistics
            echo "📈 Connection Statistics:\n";
            echo "=======================\n";
            
            $strengths = array_column($data['connections'], 'connection_strength');
            $avgStrength = array_sum($strengths) / count($strengths);
            $maxStrength = max($strengths);
            $minStrength = min($strengths);
            
            echo "Average connection strength: " . round($avgStrength, 2) . "\n";
            echo "Strongest connection: {$maxStrength} shared scandals\n";
            echo "Weakest connection: {$minStrength} shared scandal(s)\n\n";
            
            // Find the strongest connections
            $strongestConnections = array_filter($data['connections'], function($conn) use ($maxStrength) {
                return $conn['connection_strength'] === $maxStrength;
            });
            
            if (count($strongestConnections) > 0) {
                echo "🔗 Strongest Connections:\n";
                foreach ($strongestConnections as $connection) {
                    echo "   • {$connection['politician1']} ↔ {$connection['politician2']} ({$connection['connection_strength']} scandals)\n";
                }
                echo "\n";
            }
        }
        
    } else {
        echo "❌ Error: HTTP {$result['status']}\n";
        if (isset($result['data']['error'])) {
            echo "Error: {$result['data']['error']}\n";
            if (isset($result['data']['details'])) {
                echo "Details: {$result['data']['details']}\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n💡 Usage Tips:\n";
echo "=============\n";
echo "• Change the \$politicianId variable to test with different politician IDs (or set to null for all)\n";
echo "• The API requires scandals to have 'involved_persons' data to find connections\n";
echo "• Connection strength indicates how many scandals connect two politicians\n";
echo "• Results are sorted by connection strength (highest first)\n";
echo "• Each connection includes detailed scandal information and involvement roles\n";
echo "• Country information is included in each scandal\n";
echo "• If a politician ID is not found, the API will return an appropriate error message\n\n";

if (!empty($politicianId)) {
    echo "🔗 API Endpoint: GET {$baseUrl}/politicians/connections?politician_id={politician_id}\n";
} else {
    echo "🔗 API Endpoint: GET {$baseUrl}/politicians/connections\n";
}
echo "📚 Full Documentation: POLITICIAN_CONNECTIONS_API.md\n"; 