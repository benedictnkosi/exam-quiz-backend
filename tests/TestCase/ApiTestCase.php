<?php

namespace App\Tests\TestCase;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected $client;
    protected $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        
        // Ensure kernel is booted
        self::bootKernel();
        
        // Get entity manager from container
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        // Clear database before each test
        $this->truncateEntities([
            'App\Entity\Result',
            'App\Entity\Learnersubjects',
            'App\Entity\Question',
            'App\Entity\Learner',
            'App\Entity\Subject',
            'App\Entity\Grade'
        ]);
    }

    protected function truncateEntities(array $entities): void
    {
        if (!$this->entityManager) {
            return;
        }
        
        foreach ($entities as $entity) {
            $this->entityManager->createQuery('DELETE FROM ' . $entity)->execute();
        }
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager) {
            $this->truncateEntities([
                'App\Entity\Result',
                'App\Entity\Learnersubjects',
                'App\Entity\Question',
                'App\Entity\Learner',
                'App\Entity\Subject',
                'App\Entity\Grade'
            ]);
            $this->entityManager->close();
            $this->entityManager = null;
        }
        
        parent::tearDown();
    }
} 