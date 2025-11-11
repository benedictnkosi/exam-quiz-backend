<?php
/**
 * Script to generate YouTube OAuth 2.0 Refresh Token
 * 
 * Usage:
 *   php scripts/generate-youtube-refresh-token.php
 * 
 * This will:
 * 1. Open a browser for you to authorize the app
 * 2. Exchange the authorization code for tokens
 * 3. Display your refresh token to save in environment variables
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load credentials from the client secret file
$clientSecretFile = __DIR__ . '/../client_secret_405909751933-17fpjg02ef74skut90phai5jq216od5s.apps.googleusercontent.com.json';
if (!file_exists($clientSecretFile)) {
    die("Error: Client secret file not found at: $clientSecretFile\n");
}

$credentials = json_decode(file_get_contents($clientSecretFile), true);
$clientId = $credentials['web']['client_id'] ?? null;
$clientSecret = $credentials['web']['client_secret'] ?? null;
$redirectUri = $credentials['web']['redirect_uris'][0] ?? 'http://localhost:8080/oauth2callback';

if (!$clientId || !$clientSecret) {
    die("Error: Could not extract client_id or client_secret from credentials file\n");
}

echo "YouTube OAuth 2.0 Refresh Token Generator\n";
echo "=========================================\n\n";
echo "Client ID: $clientId\n";
echo "Redirect URI: $redirectUri\n\n";

// Step 1: Generate authorization URL
$scopes = [
    'https://www.googleapis.com/auth/youtube.upload',
    'https://www.googleapis.com/auth/youtube'
];
$scopeString = implode(' ', $scopes); // do NOT urlencode scopes; http_build_query will handle encoding

// Use v2 auth endpoint
$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => $scopeString,
    'access_type' => 'offline', // Required to get refresh token
    'prompt' => 'consent', // Force consent screen to ensure refresh token
]);

echo "Step 1: Authorize the application\n";
echo "-----------------------------------\n";
echo "Open this URL in your browser:\n\n";
echo $authUrl . "\n\n";
echo "After authorizing, you'll be redirected to a URL like:\n";
echo "$redirectUri?code=AUTHORIZATION_CODE\n\n";
echo "Copy the 'code' parameter value from that URL and paste it here:\n";
echo "Authorization code: ";

$authCode = trim(fgets(STDIN));

if (empty($authCode)) {
    die("Error: No authorization code provided\n");
}

// Step 2: Exchange authorization code for tokens
echo "\nStep 2: Exchanging authorization code for tokens...\n";
echo "----------------------------------------------------\n";

$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code' => $authCode,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code',
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("Error: Failed to exchange code for tokens. HTTP $httpCode\nResponse: $response\n");
}

$tokens = json_decode($response, true);

if (!isset($tokens['refresh_token'])) {
    echo "Warning: No refresh_token in response. This might happen if you've already authorized before.\n";
    echo "Response: " . json_encode($tokens, JSON_PRETTY_PRINT) . "\n\n";
    echo "To get a refresh token:\n";
    echo "1. Revoke access at: https://myaccount.google.com/permissions\n";
    echo "2. Run this script again\n";
    exit(1);
}

// Success!
echo "\n✅ Success! Tokens received.\n\n";
echo "Step 3: Save these to your environment variables\n";
echo "==================================================\n\n";
echo "Add these to your .env file or environment:\n\n";
echo "GOOGLE_CLIENT_ID=$clientId\n";
echo "GOOGLE_CLIENT_SECRET=$clientSecret\n";
echo "YOUTUBE_REFRESH_TOKEN={$tokens['refresh_token']}\n";
echo "GOOGLE_APP_NAME=ExamQuizBackend\n\n";
echo "Refresh Token: {$tokens['refresh_token']}\n\n";
echo "Access Token (expires in 1 hour, refresh token is permanent):\n";
echo "  {$tokens['access_token']}\n\n";
echo "Token expires in: " . ($tokens['expires_in'] ?? 'unknown') . " seconds\n";

