<?php

namespace App\Service;

use App\Entity\EarlyAccess;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class EarlyAccessService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function registerEmail(string $email): array
    {
        try {
            // Check if email already exists
            $existingEmail = $this->entityManager
                ->getRepository(EarlyAccess::class)
                ->findOneBy(['email' => $email]);

            if ($existingEmail) {
                return [
                    'status' => 'NOK',
                    'message' => 'Email already registered'
                ];
            }

            // Create new early access entry
            $earlyAccess = new EarlyAccess();
            $earlyAccess->setEmail($email);

            $this->entityManager->persist($earlyAccess);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => 'Email registered successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in early access registration: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Internal server error'
            ];
        }
    }
}