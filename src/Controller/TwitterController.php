<?php

namespace App\Controller;

use App\Service\TwitterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/twitter')]
class TwitterController extends AbstractController
{
    public function __construct(private readonly TwitterService $twitterService) {}

    #[Route('/post', name: 'twitter_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['message']) || empty($data['message'])) {
            return $this->json([
                'error' => 'Missing or empty message',
                'details' => 'The "message" field is required.'
            ], 400);
        }
        $result = $this->twitterService->postTweet($data['message']);
        if (isset($result['error'])) {
            return $this->json($result, 502);
        }
        return $this->json($result);
    }
} 