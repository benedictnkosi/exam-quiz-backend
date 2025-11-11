<?php
/**
 * Upload a local video file directly to YouTube using the YouTube Data API.
 *
 * Usage:
 *   php scripts/upload-to-youtube.php \
 *     --file=/absolute/path/to/video.mp4 \
 *     --title="My Title" \
 *     --description="My Description" \
 *     --privacy=unlisted
 *
 * Env requirements:
 *   GOOGLE_CLIENT_ID
 *   GOOGLE_CLIENT_SECRET
 *   YOUTUBE_REFRESH_TOKEN
 *   GOOGLE_APP_NAME (optional, defaults to ExamQuizBackend)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client as GoogleClient;
use Google\Service\YouTube as GoogleYouTube;
use Google\Service\YouTube\Video as GoogleYouTubeVideo;
use Google\Service\YouTube\VideoSnippet as GoogleYouTubeVideoSnippet;
use Google\Service\YouTube\VideoStatus as GoogleYouTubeVideoStatus;
use Google\Http\MediaFileUpload;
use Symfony\Component\Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = new Dotenv();
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $dotenv->load($envFile);
}
// Also try .env.local if it exists
$envLocalFile = dirname(__DIR__) . '/.env.local';
if (file_exists($envLocalFile)) {
    $dotenv->load($envLocalFile);
}

function readEnv(string $name, ?string $default = null): ?string
{
    // Check $_ENV first (populated by Dotenv)
    if (isset($_ENV[$name]) && is_string($_ENV[$name]) && $_ENV[$name] !== '') {
        return (string)$_ENV[$name];
    }
    // Check $_SERVER
    if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return (string)$_SERVER[$name];
    }
    // Check getenv() as fallback
    $val = getenv($name);
    if ($val !== false && is_string($val) && $val !== '') {
        return $val;
    }
    return $default;
}

function fail(string $message, int $code = 1): void
{
    fwrite(STDERR, "[ERROR] " . $message . PHP_EOL);
    exit($code);
}

function info(string $message): void
{
    fwrite(STDOUT, "[INFO] " . $message . PHP_EOL);
}

// Parse CLI options
$opts = getopt('', ['file:', 'title:', 'description::', 'privacy::', 'category::', 'chunk::']);

$filePath = isset($opts['file']) ? (string)$opts['file'] : '';
$title = isset($opts['title']) ? (string)$opts['title'] : '';
$description = isset($opts['description']) ? (string)$opts['description'] : '';
$privacy = isset($opts['privacy']) ? (string)$opts['privacy'] : 'public';
$category = isset($opts['category']) ? (string)$opts['category'] : '25'; // News & Politics by default
$chunkMb = isset($opts['chunk']) ? (int)$opts['chunk'] : 8; // 8MB chunks by default

if ($filePath === '' || $title === '') {
    echo "Usage:\n";
    echo "  php scripts/upload-to-youtube.php --file=/abs/path/video.mp4 --title=\"My Title\" [--description=\"...\"] [--privacy=public|unlisted|private] [--category=25] [--chunk=8]\n";
    echo "  (Relative paths are resolved from project root)\n";
    exit(2);
}

// Convert relative paths to absolute (relative to project root)
if (!str_starts_with($filePath, '/')) {
    $filePath = dirname(__DIR__) . '/' . $filePath;
}
$filePath = realpath($filePath);
if ($filePath === false) {
    fail("File not found: " . $opts['file']);
}
if (!is_readable($filePath)) {
    fail("File not readable: {$filePath}");
}
if (!in_array($privacy, ['public', 'unlisted', 'private'], true)) {
    fail("Invalid privacy: {$privacy}. Use one of: public, unlisted, private");
}
if ($chunkMb < 1) {
    fail("Chunk size must be >= 1 MB");
}

$clientId = (string)(readEnv('GOOGLE_CLIENT_ID') ?? '');
$clientSecret = (string)(readEnv('GOOGLE_CLIENT_SECRET') ?? '');
$refreshToken = (string)(readEnv('YOUTUBE_REFRESH_TOKEN') ?? '');
$appName = (string)(readEnv('GOOGLE_APP_NAME', 'ExamQuizBackend') ?? 'ExamQuizBackend');

if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
    fail('Missing env vars. Required: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, YOUTUBE_REFRESH_TOKEN');
}

info("Initializing Google client...");
$client = new GoogleClient();
$client->setApplicationName($appName);
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setAccessType('offline');
$client->setScopes(['https://www.googleapis.com/auth/youtube.upload']);
$client->setPrompt('none');
$client->setAccessToken([
    'access_token' => '',
    'expires_in' => 0,
    'created' => 0,
    'refresh_token' => $refreshToken,
]);

// Fetch a valid access token using the refresh token
info("Fetching access token...");
$client->fetchAccessTokenWithRefreshToken($refreshToken);
$accessToken = $client->getAccessToken();
if ($accessToken === null || !is_array($accessToken) || empty($accessToken['access_token'])) {
    fail('Failed to fetch access token with provided refresh token.');
}

$youtube = new GoogleYouTube($client);

// Build video metadata
$snippet = new GoogleYouTubeVideoSnippet();
$snippet->setTitle($title);
if ($description !== '') {
    $snippet->setDescription($description);
}
$snippet->setCategoryId($category);

$status = new GoogleYouTubeVideoStatus();
$status->setPrivacyStatus($privacy);

$video = new GoogleYouTubeVideo();
$video->setSnippet($snippet);
$video->setStatus($status);

$chunkSizeBytes = $chunkMb * 1024 * 1024;
$client->setDefer(true);

info("Preparing upload (chunk size: {$chunkMb} MB)...");
$insert = $youtube->videos->insert('snippet,status', $video);
$media = new MediaFileUpload(
    $client,
    $insert,
    'video/*',
    null,
    true,
    $chunkSizeBytes
);
$fileSize = filesize($filePath);
if ($fileSize === false) {
    fail('Could not determine file size.');
}
$media->setFileSize($fileSize);

$handle = fopen($filePath, 'rb');
if ($handle === false) {
    $client->setDefer(false);
    fail('Failed to open file for reading.');
}

info("Uploading file: {$filePath}");
$statusResponse = false;
$uploadedBytes = 0;
while (!$statusResponse && !feof($handle)) {
    $chunk = fread($handle, $chunkSizeBytes);
    if ($chunk === false) {
        fclose($handle);
        $client->setDefer(false);
        fail('Failed reading file chunk.');
    }
    $statusResponse = $media->nextChunk($chunk);
    $uploadedBytes += strlen($chunk);
    $percent = $fileSize > 0 ? round(($uploadedBytes / $fileSize) * 100, 2) : 0.0;
    info("Progress: {$percent}%");
}
fclose($handle);
$client->setDefer(false);

if ($statusResponse instanceof GoogleYouTubeVideo) {
    $videoId = $statusResponse->getId();
    if (is_string($videoId) && $videoId !== '') {
        info("Upload complete. YouTube Video ID: {$videoId}");
        echo $videoId . PHP_EOL;
        exit(0);
    }
}

fail('Upload finished but no video ID was returned.');


