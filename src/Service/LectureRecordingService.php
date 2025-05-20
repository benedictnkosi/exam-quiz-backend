<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;

class LectureRecordingService
{
    private string $lecturesDirectory;

    public function __construct(
        ParameterBagInterface $params,
        private readonly LearnerDailyUsageService $learnerDailyUsageService,
        private readonly EntityManagerInterface $em
    ) {
        $this->lecturesDirectory = $params->get('kernel.project_dir') . '/public/assets/lectures';
    }

    public function getRecordingResponse(string $filename, ?string $uid = null, ?string $subscriptionCheck = null): Response
    {
        // Check remaining podcast usage if uid is provided
        if ($uid) {
            if ($subscriptionCheck) {
                $usageCheck = $this->learnerDailyUsageService->hasRemainingPodcastUsage($uid);
                if ($usageCheck['status'] === 'NOK') {
                    return new Response($usageCheck['message'], Response::HTTP_BAD_REQUEST);
                }
                if (!$usageCheck['hasRemaining']) {
                    return new Response('Daily podcast limit reached', Response::HTTP_FORBIDDEN);
                }
            }
        }

        $filePath = $this->lecturesDirectory . '/' . $filename;

        if (!file_exists($filePath)) {
            return new Response('Recording not found', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', 'audio/ogg'); // Opus files use audio/ogg MIME type
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $filename
        );

        //if uid is provided, increment the podcast usage
        if ($uid) {
            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if ($learner) {
                $this->learnerDailyUsageService->incrementPodcastUsage($learner);
            }
        }
        return $response;
    }

    public function hasRemainingPodcastUsage(string $learnerUid): array
    {
        return $this->learnerDailyUsageService->hasRemainingPodcastUsage($learnerUid);
    }
}