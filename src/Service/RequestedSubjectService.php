<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\RequestedSubject;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Repository\RequestedSubjectRepository;

class RequestedSubjectService
{
    private $requestedSubjectRepository;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        RequestedSubjectRepository $requestedSubjectRepository
    ) {
        $this->requestedSubjectRepository = $requestedSubjectRepository;
    }

    public function requestSubject(string $learnerUid, string $subjectName): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $learnerUid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $requestedSubject = new RequestedSubject();
            $requestedSubject->setRequester($learner);
            $requestedSubject->setSubjectName($subjectName);
            $requestedSubject->setRequestDate(new \DateTime());

            $this->entityManager->persist($requestedSubject);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => 'Subject request submitted successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error requesting subject: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error submitting subject request: ' . $e->getMessage()
            ];
        }
    }

    public function getSubjectRequestReport(): array
    {
        try {
            $subjectCounts = $this->entityManager->getRepository(RequestedSubject::class)
                ->getSubjectRequestCounts();

            return [
                'status' => 'OK',
                'report' => $subjectCounts
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error getting subject request report: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving subject request report: ' . $e->getMessage()
            ];
        }
    }

    public function getSubjectRequestCounts(): array
    {
        return $this->requestedSubjectRepository->getSubjectRequestCounts();
    }
}