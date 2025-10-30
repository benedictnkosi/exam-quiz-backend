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
            return new JsonResponse(['error' => 'Video not prepared yet'], 404);
        }

        $response = new BinaryFileResponse($outputFile);
        $response->headers->set('Content-Type', 'video/mp4');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($outputFile));
        $response->setPublic();
        $response->setMaxAge(3600);
        return $response;
    }

    // No download/burn logic here by design; controller only serves pre-rendered files.
}


