<?php

namespace App\Service;

use App\Entity\LearnerReading;
use App\Repository\LearnerReadingRepository;
use DateTimeImmutable;

class LearnerReadingStatsService
{
    public function __construct(
        private readonly LearnerReadingRepository $learnerReadingRepository
    ) {
    }

    public function getCompletedChaptersCountByDay(): array
    {
        $twoWeeksAgo = (new DateTimeImmutable())->modify('-2 weeks');

        return $this->learnerReadingRepository->findCompletedChaptersCountByDay($twoWeeksAgo);
    }
}