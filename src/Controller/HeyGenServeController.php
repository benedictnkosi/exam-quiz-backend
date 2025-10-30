<?php

namespace App\Controller;

use App\Entity\HeyGenVideo;
use App\Repository\HeyGenVideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Process\Process;

class HeyGenServeController extends AbstractController
{
    private HeyGenVideoRepository $repo;
    private HttpClientInterface $http;

    public function __construct(HeyGenVideoRepository $repo, ?HttpClientInterface $http = null)
    {
        $this->repo = $repo;
        $this->http = $http ?? HttpClient::create();
    }

    #[Route('/api/heygen/{id}/video', name: 'heygen_video_with_captions', methods: ['GET'])]
    public function getVideoWithCaptions(int $id)
    {
        $entity = $this->repo->find($id);
        if (!$entity instanceof HeyGenVideo) {
            return new JsonResponse(['error' => 'Video not found'], 404);
        }

        $publicDir = dirname(__DIR__, 2) . '/public/uploads/documents/heygen/rendered';
        if (!is_dir($publicDir)) {
            @mkdir($publicDir, 0755, true);
        }
        $outputFile = $publicDir . '/' . $id . '.mp4';

        if (!is_file($outputFile)) {
            $tmpDir = sys_get_temp_dir() . '/heygen_' . $id;
            if (!is_dir($tmpDir)) {
                @mkdir($tmpDir, 0700, true);
            }

            $videoTmp = $tmpDir . '/video.mp4';
            $subsTmp = $tmpDir . '/subs.ass';

            // Download source video
            $this->downloadToFile($entity->getVideoUrl(), $videoTmp);

            // Download captions if available; prefer .ass, otherwise convert later on-demand
            $captionUrl = $entity->getCaptionUrl();
            if ($captionUrl) {
                // Keep original extension, but default to .ass
                $cleanUrl = preg_replace('/[?#].*$/', '', $captionUrl);
                $ext = strtolower(pathinfo($cleanUrl ?? '', PATHINFO_EXTENSION));
                $subsPath = $tmpDir . '/subs.' . ($ext ?: 'ass');
                $this->downloadToFile($captionUrl, $subsPath);
                // If not .ass, let ffmpeg handle via subtitles filter; rename for filter clarity
                @rename($subsPath, $subsTmp);
            }

            // Build ffmpeg command to burn subtitles if present
            $filter = null;
            if (is_file($subsTmp) && filesize($subsTmp) > 0) {
                $escaped = str_replace(':', '\\:', $subsTmp);
                $force = "Alignment=5,MarginV=150,MarginL=40,MarginR=5,Outline=1,FontSize=20,LineSpacing=2,WrapStyle=0";
                $filter = "subtitles='" . $escaped . "':force_style='" . $force . "'";
            }

            if ($filter !== null) {
                $proc = new Process([
                    'bash', '-lc',
                    'ffmpeg -y -loglevel error -i ' . escapeshellarg($videoTmp) . ' -vf ' . escapeshellarg($filter) . ' -c:a copy ' . escapeshellarg($outputFile)
                ]);
            } else {
                // No captions available; just move/copy the video
                $proc = new Process(['bash', '-lc', 'cp ' . escapeshellarg($videoTmp) . ' ' . escapeshellarg($outputFile)]);
            }

            $proc->setTimeout(600);
            $proc->run();
            if (!$proc->isSuccessful() || !is_file($outputFile)) {
                return new JsonResponse([
                    'error' => 'Failed to prepare video',
                    'details' => $proc->getErrorOutput(),
                ], 500);
            }
        }

        $response = new BinaryFileResponse($outputFile);
        $response->headers->set('Content-Type', 'video/mp4');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($outputFile));
        $response->setPublic();
        $response->setMaxAge(3600);
        return $response;
    }

    private function downloadToFile(string $url, string $dest): void
    {
        $response = $this->http->request('GET', $url, ['timeout' => 120, 'max_redirects' => 5]);
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to download: ' . $url);
        }
        $content = $response->getContent();
        if (@file_put_contents($dest, $content) === false) {
            throw new \RuntimeException('Failed to write file: ' . $dest);
        }
    }
}


