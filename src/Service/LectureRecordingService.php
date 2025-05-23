<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LectureRecordingService
{
    private string $lecturesDirectory;

    public function __construct(
        ParameterBagInterface $params,
        private readonly LearnerDailyUsageService $learnerDailyUsageService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {
        $this->lecturesDirectory = $params->get('kernel.project_dir') . '/public/assets/lectures';
    }

    public function getRecordingResponse(string $filename, ?string $uid = null, ?string $subscriptionCheck = null): Response
    {
        $this->logger->info('Getting recording response', [
            'filename' => $filename,
            'uid' => $uid,
            'subscriptionCheck' => $subscriptionCheck
        ]);

        // Check remaining podcast usage if uid is provided
        if ($uid) {
            if ($subscriptionCheck) {
                $usageCheck = $this->learnerDailyUsageService->hasRemainingPodcastUsage($uid);
                if ($usageCheck['status'] === 'NOK') {
                    $this->logger->warning('Podcast usage check failed', [
                        'uid' => $uid,
                        'message' => $usageCheck['message']
                    ]);
                    return new Response($usageCheck['message'], Response::HTTP_BAD_REQUEST);
                }
                if (!$usageCheck['hasRemaining']) {
                    $this->logger->info('Daily podcast limit reached', ['uid' => $uid]);
                    return new Response('Daily podcast limit reached', Response::HTTP_FORBIDDEN);
                }
            }
        }

        $filePath = $this->lecturesDirectory . '/' . $filename;

        if (!file_exists($filePath)) {
            $this->logger->warning('Recording file not found', [
                'filename' => $filename,
                'filePath' => $filePath
            ]);
            return new Response('Recording not found', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', 'audio/ogg'); // Opus files use audio/ogg MIME type
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $filename
        );

        $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
        if ($learner) {
            $this->logger->info('Incrementing podcast usage for learner', [
                'uid' => $uid,
                'filename' => $filename
            ]);
            $this->learnerDailyUsageService->incrementPodcastUsage($learner, $filename);
        } else {
            $this->logger->warning('Learner not found for podcast usage increment', ['uid' => $uid]);
        }

        $this->logger->info('Successfully returning recording response', [
            'filename' => $filename,
            'uid' => $uid
        ]);
        return $response;
    }

    public function hasRemainingPodcastUsage(string $learnerUid): array
    {
        return $this->learnerDailyUsageService->hasRemainingPodcastUsage($learnerUid);
    }
}