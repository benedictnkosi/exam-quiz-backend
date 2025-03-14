<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Grade;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class LearnerImportService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Import learners from a JSON file
     * 
     * @param string|UploadedFile $jsonData JSON string or uploaded file
     * @return array Array with success count and errors
     */
    public function importFromJson($jsonData): array
    {
        $result = [
            'success' => 0,
            'errors' => [],
        ];

        // Handle both string and uploaded file
        if ($jsonData instanceof UploadedFile) {
            $jsonContent = file_get_contents($jsonData->getPathname());
        } else {
            $jsonContent = $jsonData;
        }

        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['errors'][] = 'Invalid JSON format: ' . json_last_error_msg();
            return $result;
        }

        if (!is_array($data)) {
            $result['errors'][] = 'JSON data must be an array of learners';
            return $result;
        }

        foreach ($data as $index => $learnerData) {
            try {
                $this->importLearner($learnerData);
                $result['success']++;
            } catch (\Exception $e) {
                $result['errors'][] = "Error importing learner at index $index: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Import a single learner from array data
     * 
     * @param array $data Learner data
     * @return Learner The imported learner entity
     */
    private function importLearner(array $data): Learner
    {
        // Check if learner already exists by uid
        $existingLearner = null;
        if (!empty($data['uid'])) {
            $existingLearner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $data['uid']]);
        }

        // If not found by uid, try by id
        if (!$existingLearner && isset($data['id'])) {
            $existingLearner = $this->entityManager->getRepository(Learner::class)->find($data['id']);
        }

        $learner = $existingLearner ?: new Learner();

        // Set basic properties
        if (isset($data['uid'])) {
            $learner->setUid($data['uid']);
        }

        if (isset($data['grade'])) {
            $grade = $this->entityManager->getRepository(Grade::class)->find($data['grade']);
            if ($grade) {
                $learner->setGrade($grade);
            }
        }

        if (isset($data['points'])) {
            $learner->setPoints((int) $data['points']);
        }

        if (isset($data['name'])) {
            $learner->setName($data['name']);
        }

        if (isset($data['notification_hour'])) {
            $learner->setNotificationHour((int) $data['notification_hour']);
        }

        if (isset($data['role'])) {
            $learner->setRole($data['role']);
        }

        if (isset($data['created'])) {
            $learner->setCreated(new \DateTime($data['created']));
        }

        if (isset($data['lastSeen'])) {
            $learner->setLastSeen(new \DateTime($data['lastSeen']));
        }

        if (isset($data['school_address'])) {
            $learner->setSchoolAddress($data['school_address']);
        }

        if (isset($data['school_name'])) {
            $learner->setSchoolName($data['school_name']);
        }

        if (isset($data['school_latitude'])) {
            $learner->setSchoolLatitude((float) $data['school_latitude']);
        }

        if (isset($data['school_longitude'])) {
            $learner->setSchoolLongitude((float) $data['school_longitude']);
        }

        if (isset($data['terms'])) {
            $learner->setTerms($data['terms']);
        }

        if (isset($data['curriculum'])) {
            $learner->setCurriculum($data['curriculum']);
        }

        if (isset($data['private_school'])) {
            $learner->setPrivateSchool((bool) $data['private_school']);
        }

        if (isset($data['email']) && $data['email'] !== 'NULL') {
            $learner->setEmail($data['email']);
        }

        if (isset($data['rating'])) {
            $learner->setRating((float) $data['rating']);
        }

        if (isset($data['rating_cancelled'])) {
            $learner->setRatingCancelled(new \DateTime($data['rating_cancelled']));
        }

        if (isset($data['streak'])) {
            $learner->setStreak((int) $data['streak']);
        }

        if (isset($data['streak_last_updated'])) {
            $learner->setStreakLastUpdated(new \DateTime($data['streak_last_updated']));
        }

        if (isset($data['avatar'])) {
            $learner->setAvatar($data['avatar']);
        }

        // Persist the entity
        $this->entityManager->persist($learner);
        $this->entityManager->flush();

        return $learner;
    }
}