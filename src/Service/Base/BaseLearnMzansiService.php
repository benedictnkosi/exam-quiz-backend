<?php

namespace App\Service\Base;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Learner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BaseLearnMzansiService extends AbstractController
{
    protected $em;
    protected $logger;

    public function __construct(
        EntityManagerInterface $entityManager, 
        LoggerInterface $apiLogger
    ) {
        $this->em = $entityManager;
        $this->logger = $apiLogger;
    }

    /**
     * Helper method to check if a user has admin role
     */
    protected function isAdmin(string $uid): bool
    {
        $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
        return $learner && $learner->getRole() === 'admin';
    }

    /**
     * Helper method to validate admin access using uid from request body
     */
    protected function validateAdminAccess(array $requestData): array
    {
        $uid = $requestData['uid'] ?? null;

        if (empty($uid)) {
            return array(
                'status' => 'NOK',
                'message' => 'User ID is required'
            );
        }

        if (!$this->isAdmin($uid)) {
            return array(
                'status' => 'NOK',
                'message' => 'Unauthorized: Admin access required'
            );
        }
        return array('status' => 'OK');
    }
} 