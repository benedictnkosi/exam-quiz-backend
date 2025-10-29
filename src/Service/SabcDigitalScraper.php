<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SabcDigitalScraper
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private YouTubeTranscriptService $transcriptService;
    private OpenAIService $openAIService;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger, YouTubeTranscriptService $transcriptService, OpenAIService $openAIService)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->transcriptService = $transcriptService;
        $this->openAIService = $openAIService;
    }

    /**
     * Fetch the channel videos page and attempt to find a "Prime News" video
     * published within the past $daysBack days (0 = today only, 1 = yesterday, etc.).
     * Returns [id, title] if found, otherwise [null, null].
     */
    public function findPrimeNewsVideoWithinDays(int $daysBack = 0): array
    {
        $url = 'https://www.youtube.com/@sabcdigitalnews/videos';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
                'timeout' => 20,
            ]);
            $html = $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch SABC Digital News page', [
                'error' => $e->getMessage(),
            ]);
            return [null, null];
        }

        [$videoId, $title] = $this->extractPrimeNewsFromHtml($html, $daysBack);

        return [$videoId, $title];
    }

    /**
     * Generate a satirical news script for a given YouTube video using its transcript.
     * Returns the script string on success, or null on failure.
     */
    public function createNewsScriptForVideo(string $videoId, ?string $title = null): ?string
    {
        $videoUrl = strlen($videoId) === 11 ? 'https://www.youtube.com/watch?v=' . $videoId : $videoId;
        $transcriptText = $this->transcriptService->fetchTranscript($videoUrl, 'text', false, false);
        if (!is_string($transcriptText) || trim($transcriptText) === '') {
            // Fallback to JSON and join segments
            $transcriptJson = $this->transcriptService->fetchTranscript($videoUrl, 'json', false, false);
            if (is_array($transcriptJson) && isset($transcriptJson['segments']) && is_array($transcriptJson['segments'])) {
                $transcriptText = trim(implode("\n", array_map(static function ($seg) {
                    return is_array($seg) && isset($seg['text']) ? (string)$seg['text'] : '';
                }, $transcriptJson['segments'])));
            }
        }
        if (!is_string($transcriptText) || trim($transcriptText) === '') {
            $this->logger->warning('Unable to fetch transcript for video', ['videoId' => $videoId]);
            return null;
        }
        $script = $this->openAIService->generateVideoScriptFromTranscript($transcriptText, 3, 60);
        if (!is_string($script) || trim($script) === '' || str_starts_with($script, 'Failed to generate')) {
            $this->logger->warning('Script generation failed', ['videoId' => $videoId, 'title' => $title]);
            return null;
        }
        $this->logger->info('Generated news script', [
            'videoId' => $videoId,
            'title' => $title,
            'script_preview' => substr($script, 0, 160)
        ]);
        return $script;
    }

    /**
     * Find a recent video by title fragment on a given channel and return [id,title].
     */
    public function findRecentVideoByTitleContains(string $channelVideosUrl, string $titleContains, int $daysBack = 0): array
    {
        try {
            $response = $this->httpClient->request('GET', $channelVideosUrl, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
                'timeout' => 20,
            ]);
            $html = $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch channel page', [ 'url' => $channelVideosUrl, 'error' => $e->getMessage() ]);
            return [null, null];
        }

        $json = $this->extractJsonBetween($html, 'var ytInitialData =', '</script>');
        if ($json === null) {
            $json = $this->extractJsonBetween($html, 'ytInitialData =', '</script>');
        }
        if ($json !== null) {
            try {
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $data = null;
            }
            if (is_array($data)) {
                $queue = [$data];
                $needle = strtolower($titleContains);
                while ($queue) {
                    $node = array_shift($queue);
                    if (!is_array($node)) { continue; }
                    $candidates = [];
                    if (isset($node['videoRenderer']) && is_array($node['videoRenderer'])) { $candidates[] = $node['videoRenderer']; }
                    if (isset($node['gridVideoRenderer']) && is_array($node['gridVideoRenderer'])) { $candidates[] = $node['gridVideoRenderer']; }
                    if (isset($node['richItemRenderer']['content']['videoRenderer']) && is_array($node['richItemRenderer']['content']['videoRenderer'])) { $candidates[] = $node['richItemRenderer']['content']['videoRenderer']; }
                    foreach ($candidates as $vr) {
                        $titleText = $this->extractTextFromRuns($vr['title']['runs'] ?? null) ?? ($vr['title']['simpleText'] ?? null);
                        if ($titleText !== null && str_contains(strtolower($titleText), $needle)) {
                            $published = $this->extractTextFromRuns($vr['publishedTimeText']['runs'] ?? null) ?? ($vr['publishedTimeText']['simpleText'] ?? null);
                            if ($this->looksLikeWithinDays($published, $daysBack)) {
                                return [$this->sanitizeVideoId($vr['videoId'] ?? ''), $titleText];
                            }
                        }
                    }
                    foreach ($node as $child) { if (is_array($child)) { $queue[] = $child; } }
                }
            }
        }
        return [null, null];
    }

    /**
     * Create a script by combining transcripts from SABC Prime News and EWN "The day that was".
     */
    public function createCombinedNewsScript(int $daysBack = 0): ?string
    {
        // Find SABC Prime News
        [$primeId, $primeTitle] = $this->findPrimeNewsVideoWithinDays($daysBack);
        // Find EWN "The day that was"
        [$ewnId, $ewnTitle] = $this->findRecentVideoByTitleContains('https://www.youtube.com/@EyewitnessNewsZA/videos', 'the day that was', $daysBack);

        $parts = [];
        foreach ([[$primeId, $primeTitle], [$ewnId, $ewnTitle]] as [$vid, $title]) {
            if (!$vid) { continue; }
            $videoUrl = strlen($vid) === 11 ? 'https://www.youtube.com/watch?v=' . $vid : $vid;
            $text = $this->transcriptService->fetchTranscript($videoUrl, 'text', false, false);
            if (!is_string($text) || trim($text) === '') {
                $json = $this->transcriptService->fetchTranscript($videoUrl, 'json', false, false);
                if (is_array($json) && isset($json['segments']) && is_array($json['segments'])) {
                    $text = trim(implode("\n", array_map(static function ($seg) { return is_array($seg) && isset($seg['text']) ? (string)$seg['text'] : ''; }, $json['segments'])));
                }
            }
            if (is_string($text) && trim($text) !== '') {
                $parts[] = $text;
            }
        }
        if (empty($parts)) {
            $this->logger->warning('Combined transcript empty');
            return null;
        }
        $combined = implode("\n\n", $parts);
        return $this->openAIService->generateVideoScriptFromTranscript($combined, 3, 60);
    }

    /**
     * Same as findPrimeNewsVideoWithinDays, but accepts pre-fetched HTML (for offline testing).
     */
    public function findPrimeNewsVideoWithinDaysFromHtml(string $html, int $daysBack = 0): array
    {
        return $this->extractPrimeNewsFromHtml($html, $daysBack);
    }

    private function extractPrimeNewsFromHtml(string $html, int $daysBack): array
    {
        // Try to locate ytInitialData JSON which contains rich metadata
        $json = $this->extractJsonBetween($html, 'var ytInitialData =', '</script>');
        if ($json === null) {
            $json = $this->extractJsonBetween($html, 'ytInitialData =', '</script>');
        }

        if ($json !== null) {
            [$id, $title] = $this->searchPrimeNewsInInitialData($json, $daysBack);
            if ($id !== null) {
                return [$id, $title];
            }
        }

        // Fallback: look for watch URLs combined with visible/attribute titles containing Prime News
        if (preg_match_all('/<a[^>]+href="\/watch\?v=([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $idCandidate = $m[1];
                $anchorHtml = $m[2];
                $text = trim(strip_tags($anchorHtml));
                // If inner text is empty, try title or aria-label attributes on the anchor tag
                if ($text === '') {
                    if (preg_match('/<a[^>]+title="([^"]+)"[^>]*>/i', $m[0], $mm)) {
                        $text = trim(html_entity_decode($mm[1]));
                    } elseif (preg_match('/<a[^>]+aria-label="([^"]+)"[^>]*>/i', $m[0], $mm2)) {
                        $text = trim(html_entity_decode($mm2[1]));
                    }
                }
                if (stripos($text, 'prime news') !== false) {
                    $cleanId = $this->sanitizeVideoId($idCandidate);
                    if ($cleanId !== null) {
                        // We do not have the published text here; accept as best-effort fallback
                        return [$cleanId, $text !== '' ? $text : null];
                    }
                }
            }
        }

        // Last resort: generic videoId occurrences, but only accept if title includes "prime news" and seems within window
        if (preg_match('/\"videoId\"\s*:\s*\"([a-zA-Z0-9_-]{6,})\"/', $html, $m2, PREG_OFFSET_CAPTURE)) {
            $id = $this->sanitizeVideoId($m2[1][0] ?? '');
            $offset = $m2[0][1] ?? 0;
            $nearTitle = $this->extractNearbyTitle($html, $offset);
            $nearPublished = $this->extractNearbyPublishedText($html, $offset);
            if ($nearTitle !== null && preg_match('/prime\s*news/i', $nearTitle) && $this->looksLikeWithinDays($nearPublished, $daysBack)) {
                return [$id, $nearTitle];
            }
            return [null, null];
        }

        return [null, null];
    }

    private function searchPrimeNewsInInitialData(string $jsonString, int $daysBack): array
    {
        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return [null, null];
        }

        $queue = [$data];
        while ($queue) {
            $node = array_shift($queue);
            if (is_array($node)) {
                // Normalize various renderer shapes used by YouTube for channel videos
                $candidates = [];
                if (isset($node['videoRenderer']) && is_array($node['videoRenderer'])) {
                    $candidates[] = $node['videoRenderer'];
                }
                if (isset($node['gridVideoRenderer']) && is_array($node['gridVideoRenderer'])) {
                    $candidates[] = $node['gridVideoRenderer'];
                }
                if (isset($node['richItemRenderer']['content']['videoRenderer']) && is_array($node['richItemRenderer']['content']['videoRenderer'])) {
                    $candidates[] = $node['richItemRenderer']['content']['videoRenderer'];
                }
                if (isset($node['richGridMedia']) && is_array($node['richGridMedia'])) {
                    // Some layouts embed data differently; try to map to expected fields
                    $rgm = $node['richGridMedia'];
                    $mapped = [
                        'videoId' => $rgm['videoId'] ?? ($rgm['hoverOverlay']['thumbnailOverlayTimeStatusRenderer']['videoId'] ?? null),
                        'title' => [
                            'runs' => $rgm['title']['runs'] ?? null,
                            'simpleText' => $rgm['title']['simpleText'] ?? null,
                        ],
                        'publishedTimeText' => [
                            'runs' => $rgm['publishedTimeText']['runs'] ?? null,
                            'simpleText' => $rgm['publishedTimeText']['simpleText'] ?? null,
                        ],
                    ];
                    $candidates[] = $mapped;
                }

                foreach ($candidates as $vr) {
                    $titleText = $this->extractTextFromRuns($vr['title']['runs'] ?? null) ?? ($vr['title']['simpleText'] ?? null);
                    if ($titleText !== null && stripos($titleText, 'prime news') !== false) {
                        $published = $this->extractTextFromRuns($vr['publishedTimeText']['runs'] ?? null) ?? ($vr['publishedTimeText']['simpleText'] ?? null);
                        if ($this->looksLikeWithinDays($published, $daysBack)) {
                            return [$this->sanitizeVideoId($vr['videoId'] ?? ''), $titleText];
                        }
                    }
                }
                foreach ($node as $child) {
                    if (is_array($child)) {
                        $queue[] = $child;
                    }
                }
            }
        }
        return [null, null];
    }

    private function extractTextFromRuns($runs): ?string
    {
        if (!is_array($runs)) {
            return null;
        }
        $parts = [];
        foreach ($runs as $r) {
            if (is_array($r) && isset($r['text'])) {
                $parts[] = $r['text'];
            }
        }
        $text = trim(implode('', $parts));
        return $text !== '' ? $text : null;
    }

    private function looksLikeWithinDays(?string $publishedText, int $daysBack): bool
    {
        if ($publishedText === null) {
            return false;
        }
        $lower = strtolower(trim($publishedText));
        // Hours/minutes ago => 0 days
        if (str_contains($lower, 'hour ago') || str_contains($lower, 'hours ago') || str_contains($lower, 'minute ago') || str_contains($lower, 'minutes ago')) {
            return 0 <= $daysBack;
        }
        if ($lower === 'today') {
            return 0 <= $daysBack;
        }
        if ($lower === 'yesterday') {
            return 1 <= $daysBack;
        }
        if (preg_match('/^(\d+)\s+day(s)?\s+ago$/', $lower, $m)) {
            $days = (int)($m[1] ?? 999);
            return $days <= $daysBack;
        }
        // Unknown wording => be conservative
        return false;
    }

    private function sanitizeVideoId(string $raw): ?string
    {
        $id = trim($raw);
        if ($id === '') {
            return null;
        }
        // Remove trailing params if any
        $id = preg_replace('/[&\"\'].*$/', '', $id);
        // Keep only valid characters
        if (!preg_match('/^[a-zA-Z0-9_-]{6,}$/', $id)) {
            return null;
        }
        return $id;
    }

    private function extractJsonBetween(string $html, string $startMarker, string $endMarker): ?string
    {
        $startPos = strpos($html, $startMarker);
        if ($startPos === false) {
            return null;
        }
        $startPos += strlen($startMarker);

        $endPos = strpos($html, $endMarker, $startPos);
        if ($endPos === false) {
            return null;
        }

        $snippet = trim(substr($html, $startPos, $endPos - $startPos));
        // Remove optional trailing semicolon
        $snippet = rtrim($snippet, " ;\n\r\t");
        // Some pages wrap JSON in window[...] = {...}; ensure we get the {...}
        $firstBrace = strpos($snippet, '{');
        $lastBrace = strrpos($snippet, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $snippet = substr($snippet, $firstBrace, $lastBrace - $firstBrace + 1);
        }
        return $snippet !== '' ? $snippet : null;
    }

    private function extractNearbyTitle(string $html, int $offset): ?string
    {
        // Search within a window around the found videoId for a title field
        $window = 4000;
        $start = max(0, $offset - $window);
        $end = min(strlen($html), $offset + $window);
        $chunk = substr($html, $start, $end - $start);

        // Try JSON-style title fields
        if (preg_match('/\"title\"\s*:\s*\{\s*\"runs\"\s*:\s*\[\s*\{\s*\"text\"\s*:\s*\"([^\"]+)\"/s', $chunk, $m)) {
            return trim(html_entity_decode($m[1]));
        }
        if (preg_match('/\"title\"\s*:\s*\{\s*\"simpleText\"\s*:\s*\"([^\"]+)\"/s', $chunk, $m2)) {
            return trim(html_entity_decode($m2[1]));
        }

        // Try nearby anchor titles
        if (preg_match('/<a[^>]+title=\"([^\"]+)\"[^>]*>/', $chunk, $m3)) {
            return trim(html_entity_decode($m3[1]));
        }
        if (preg_match('/<a[^>]+aria-label=\"([^\"]+)\"[^>]*>/', $chunk, $m4)) {
            return trim(html_entity_decode($m4[1]));
        }

        // Try meta/title tags as last resort
        if (preg_match('/<title>([^<]+)<\/title>/', $chunk, $m5)) {
            return trim(html_entity_decode($m5[1]));
        }

        return null;
    }

    private function extractNearbyPublishedText(string $html, int $offset): ?string
    {
        $window = 4000;
        $start = max(0, $offset - $window);
        $end = min(strlen($html), $offset + $window);
        $chunk = substr($html, $start, $end - $start);

        // Try JSON published time texts
        if (preg_match('/\"publishedTimeText\"\s*:\s*\{\s*\"simpleText\"\s*:\s*\"([^\"]+)\"/s', $chunk, $m)) {
            return trim(html_entity_decode($m[1]));
        }
        if (preg_match('/\"publishedTimeText\"\s*:\s*\{\s*\"runs\"\s*:\s*\[\s*\{\s*\"text\"\s*:\s*\"([^\"]+)\"/s', $chunk, $m2)) {
            return trim(html_entity_decode($m2[1]));
        }

        return null;
    }
}


