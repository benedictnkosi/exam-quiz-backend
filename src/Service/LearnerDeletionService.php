<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Result;
use App\Entity\LearnerBadge;
use App\Entity\LearnerNote;
use App\Entity\Todo;
use App\Entity\LearnerFollowing;
use App\Entity\Learnersubjects;
use App\Entity\SubjectPoints;
use App\Entity\Favorites;
use App\Entity\LearnerReading;
use App\Entity\LearnerStreak;
use App\Entity\LearnerAdTracking;
use App\Entity\ReportedMessages;
use Doctrine\ORM\EntityManagerInterface;

class LearnerDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function deleteLearnerData(Learner $learner): void
    {
        // Delete associated results
        $results = $this->em->getRepository(Result::class)->findBy(['learner' => $learner->getId()]);
        foreach ($results as $result) {
            $this->em->remove($result);
        }

        // Delete learner badges
        $learnerBadges = $this->em->getRepository(LearnerBadge::class)->findBy(['learner' => $learner->getId()]);
        foreach ($learnerBadges as $badge) {
            $this->em->remove($badge);
        }

        // Delete learner notes
        $notes = $this->em->getRepository(LearnerNote::class)->findBy(['learner' => $learner->getId()]);
        foreach ($notes as $note) {
            $this->em->remove($note);
        }

        // Delete todos
        $todos = $this->em->getRepository(Todo::class)->findBy(['learner' => $learner->getId()]);
        foreach ($todos as $todo) {
            $this->em->remove($todo);
        }

        // Delete learner following relationships
        $following = $this->em->getRepository(LearnerFollowing::class)->findBy(['follower' => $learner->getId()]);
        foreach ($following as $follow) {
            $this->em->remove($follow);
        }
        $followers = $this->em->getRepository(LearnerFollowing::class)->findBy(['following' => $learner->getId()]);
        foreach ($followers as $follower) {
            $this->em->remove($follower);
        }

        // Delete learner subjects
        $learnerSubjects = $this->em->getRepository(Learnersubjects::class)->findBy(['learner' => $learner->getId()]);
        foreach ($learnerSubjects as $subject) {
            $this->em->remove($subject);
        }

        // Delete subject points
        $subjectPoints = $this->em->getRepository(SubjectPoints::class)->findBy(['learner' => $learner->getId()]);
        foreach ($subjectPoints as $points) {
            $this->em->remove($points);
        }

        // Delete favorites
        $favorites = $this->em->getRepository(Favorites::class)->findBy(['learner' => $learner->getId()]);
        foreach ($favorites as $favorite) {
            $this->em->remove($favorite);
        }

        // Delete learner reading records
        $readings = $this->em->getRepository(LearnerReading::class)->findBy(['learner' => $learner->getId()]);
        foreach ($readings as $reading) {
            $this->em->remove($reading);
        }

        // Delete learner streak
        $streaks = $this->em->getRepository(LearnerStreak::class)->findBy(['learner' => $learner->getId()]);
        foreach ($streaks as $streak) {
            $this->em->remove($streak);
        }

        // Delete learner ad tracking
        $adTracking = $this->em->getRepository(LearnerAdTracking::class)->findBy(['learner' => $learner->getId()]);
        foreach ($adTracking as $tracking) {
            $this->em->remove($tracking);
        }

        // Delete reported messages
        $reportedMessages = $this->em->getRepository(ReportedMessages::class)->findBy(['author' => $learner->getId()]);
        foreach ($reportedMessages as $message) {
            $this->em->remove($message);
        }
        $reportedMessages = $this->em->getRepository(ReportedMessages::class)->findBy(['reporter' => $learner->getId()]);
        foreach ($reportedMessages as $message) {
            $this->em->remove($message);
        }

        // Delete learner
        $this->em->remove($learner);
    }
}