<?php

namespace App\Service;

class TwitterService
{
    private string $apiKey;
    private string $apiSecret;
    private string $token;
    private string $tokenSecret;

    public function __construct()
    {
        $this->apiKey = $_ENV['X_API_KEY'] ?? getenv('X_API_KEY');
        $this->apiSecret = $_ENV['X_API_SECRET'] ?? getenv('X_API_SECRET');
        $this->token = $_ENV['X_TOKEN'] ?? getenv('X_TOKEN');
        $this->tokenSecret = $_ENV['X_TOKEN_SECRET'] ?? getenv('X_TOKEN_SECRET');
    }

    public function postTweet(string $message): array
    {
        $url = 'https://api.twitter.com/2/tweets';
        
        // Prepare the tweet data
        $postData = json_encode([
            'text' => $message
        ]);

        // Set up curl with OAuth 1.0 headers
        $ch = curl_init();
        
        // OAuth 1.0 parameters
        $oauth = [
            'oauth_consumer_key' => $this->apiKey,
            'oauth_token' => $this->token,
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_timestamp' => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_version' => '1.0',
        ];

        // Build signature
        $baseParams = $oauth;
        ksort($baseParams);
        $baseInfo = $this->buildBaseString($url, 'POST', $baseParams);
        $compositeKey = rawurlencode($this->apiSecret) . '&' . rawurlencode($this->tokenSecret);
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $baseInfo, $compositeKey, true));

        // Build authorization header
        $authHeader = 'OAuth ' . $this->buildAuthorizationHeader($oauth);

        // Set curl options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        // Execute the request
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle response
        if ($error) {
            return ['error' => 'cURL error', 'details' => $error];
        }
        
        if ($httpCode >= 400) {
            return ['error' => 'Twitter API error', 'details' => $response, 'http_code' => $httpCode];
        }
        
        return json_decode($response, true);
    }

    function postTweetV2($message) {
        $bearerToken = 'AAAAAAAAAAAAAAAAAAAAAEED3QEAAAAA2Y0R%2BqgJZkdT2v%2BgltAI5ewtVgU%3Dxe16J9JVog4GxQkImQREZKg5dfPaiyJiyNCdJzVJRS2ouNcgEp';
        $url = 'https://api.twitter.com/2/tweets';
    
        $headers = [
            "Authorization: Bearer {$bearerToken}",
            "Content-Type: application/json"
        ];
    
        $postData = json_encode([
            'text' => $message
        ]);
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        return [
            'status' => $httpCode,
            'response' => json_decode($result, true)
        ];
    }

    private function buildBaseString(string $baseURI, string $method, array $params): string
    {
        $r = [];
        ksort($params);
        foreach ($params as $key => $value) {
            $r[] = "$key=" . rawurlencode($value);
        }
        return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r));
    }

    private function buildAuthorizationHeader(array $oauth): string
    {
        $r = '';
        foreach ($oauth as $key => $value) {
            $r .= "$key=\"" . rawurlencode($value) . "\", ";
        }
        return rtrim($r, ', ');
    }
} 