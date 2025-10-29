<?php

namespace App\Controller;

use App\Entity\HeyGenVideo;
use App\Repository\HeyGenVideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class HeyGenVideoController extends AbstractController
{
    public function __construct(private HeyGenVideoRepository $repo, private EntityManagerInterface $em)
    {
    }

    #[Route('/api/heygen/pending', name: 'heygen_pending', methods: ['GET'])]
    public function listPending(): JsonResponse
    {
        $videos = $this->repo->findPendingUploads();
        $data = array_map(static function (HeyGenVideo $v) {
            return [
                'id' => $v->getId(),
                'title' => $v->getTitle(),
                'video_url' => $v->getVideoUrl(),
                'caption_url' => $v->getCaptionUrl(),
                'created_at' => $v->getCreatedAt()->format(DATE_ATOM),
                'uploaded' => $v->isUploaded(),
            ];
        }, $videos);
        return new JsonResponse($data);
    }

    #[Route('/api/heygen/{id}/mark-uploaded', name: 'heygen_mark_uploaded', methods: ['POST'])]
    public function markUploaded(int $id, Request $request): JsonResponse
    {
        $video = $this->repo->find($id);
        if (!$video) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }
        $video->setUploaded(true);
        $this->em->flush();
        return new JsonResponse(['status' => 'ok']);
    }
}


