<?php

namespace App\Service;

use App\Repository\LearnerDailyUsageRepository;
use DateTimeImmutable;

class LearnerDailyUsageStatsService
{
    public function __construct(
        private readonly LearnerDailyUsageRepository $learnerDailyUsageRepository
    ) {
    }

    public function getFreeUsersWithHighActivity(): array
    {
        $today = new DateTimeImmutable();
        $twoWeeksAgo = $today->modify('-2 weeks');

        return $this->learnerDailyUsageRepository->findFreeUsersWithHighActivity($twoWeeksAgo, $today);
    }
}