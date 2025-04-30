<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\LearnerAdTracking;
use App\Repository\LearnerAdTrackingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LearnerAdTrackingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LearnerAdTrackingRepository $adTrackingRepository,
        private LoggerInterface $logger
    ) {
    }

    public function getOrCreateTracking(Learner $learner): LearnerAdTracking
    {
        $tracking = $this->adTrackingRepository->findByLearnerId($learner->getId());

        if (!$tracking) {
            $tracking = new LearnerAdTracking();
            $tracking->setLearner($learner);
            $this->entityManager->persist($tracking);
            $this->entityManager->flush();
        }

        return $tracking;
    }

    public function incrementQuestionsAnswered(Learner $learner): LearnerAdTracking
    {
        $tracking = $this->getOrCreateTracking($learner);
        $tracking->incrementQuestionsAnswered();
        $this->entityManager->flush();
        return $tracking;
    }

    public function incrementAdsShown(Learner $learner): LearnerAdTracking
    {
        $tracking = $this->getOrCreateTracking($learner);
        $tracking->incrementAdsShown();
        $this->entityManager->flush();
        return $tracking;
    }

    public function getTrackingStats(Learner $learner): array
    {
        $tracking = $this->getOrCreateTracking($learner);

        return [
            'questions_answered' => $tracking->getQuestionsAnswered(),
            'ads_shown' => $tracking->getAdsShown(),
            'last_ad_shown_at' => $tracking->getLastAdShownAt()?->format('Y-m-d H:i:s')
        ];
    }

    public function shouldShowAd(Learner $learner): array
    {
        $tracking = $this->getOrCreateTracking($learner);
        $questionsAnswered = $tracking->getQuestionsAnswered();

        // Check if we've answered a multiple of 20 questions
        $shouldShowAd = $questionsAnswered > 0 && $questionsAnswered % 20 === 0;

        return [
            'should_show_ad' => $shouldShowAd,
            'questions_answered' => $questionsAnswered,
            'next_ad_at' => $shouldShowAd ? 0 : (20 - ($questionsAnswered % 20))
        ];
    }
}