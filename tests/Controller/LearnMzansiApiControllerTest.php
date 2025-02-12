<?php

namespace App\Tests\Controller;

use App\Tests\TestCase\ApiTestCase;
use App\Entity\Learner;
use App\Entity\Grade;
use App\Entity\Question;
use App\Entity\Subject;
use App\Entity\Learnersubjects;

class LearnMzansiApiControllerTest extends ApiTestCase
{
    private Grade $grade;
    private Subject $subject;
    private Learner $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixtures();
    }

    private function loadFixtures(): void
    {
        // Create test grade
        $this->grade = new Grade();
        $this->grade->setNumber(10)
            ->setActive(1);
        $this->entityManager->persist($this->grade);

        // Create test subject
        $this->subject = new Subject();
        $this->subject->setName('Mathematics')
            ->setGrade($this->grade)
            ->setActive(true);
        $this->entityManager->persist($this->subject);

        // Create test admin user
        $this->admin = new Learner();
        $this->admin->setUid('admin_test_uid')
            ->setName('admin')
            ->setRole('admin')
            ->setOverideTerm(true)
            ->setCreated(new \DateTime())
            ->setLastSeen(new \DateTime());
        $this->entityManager->persist($this->admin);

        $this->entityManager->flush();
    }

    public function testCreateLearner(): void
    {
        $payload = [
            'uid' => 'test_user_123',
            'name' => 'Test User'
        ];

        $this->client->request(
            'POST',
            '/public/learn/learner/create',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $content['status']);
        $this->assertEquals('Successfully created learner', $content['message']);

        // Verify learner was created in database
        $learner = $this->entityManager->getRepository(Learner::class)
            ->findOneBy(['uid' => 'test_user_123']);
        $this->assertNotNull($learner);
        $this->assertEquals('Test User', $learner->getName());
    }

    public function testGetGrades(): void
    {
        $this->client->request('GET', '/public/learn/grades');

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $content['status']);
        $this->assertIsArray($content['grades']);
        $this->assertCount(1, $content['grades']);
        $this->assertEquals(10, $content['grades'][0]['number']);
    }

    public function testCreateQuestion(): void
    {
        $payload = [
            'uid' => 'admin_test_uid',
            'type' => 'multiple_choice',
            'subject' => 'Mathematics',
            'question' => 'What is 2 + 2?',
            'options' => [
                'option1' => '3',
                'option2' => '4',
                'option3' => '5',
                'option4' => '6'
            ],
            'answer' => '4',
            'year' => 2024,
            'term' => 1,
            'capturer' => 'admin_test_uid',
            'question_id' => 0
        ];

        $this->client->request(
            'POST',
            '/public/learn/question/create',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $content['status']);
        $this->assertArrayHasKey('question_id', $content);
    }

    public function testGetRandomQuestion(): void
    {
        // First create a learner and assign subject
        $learner = $this->createTestLearnerWithSubject();
        
        $this->client->request(
            'GET',
            '/public/learn/question/random',
            [
                'subject_id' => $this->subject->getId(),
                'uid' => $learner->getUid(),
                'question_id' => 0
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        if (empty($content['message'])) {
            $this->assertArrayHasKey('id', $content);
            $this->assertArrayHasKey('question', $content);
        } else {
            $this->assertEquals('No more questions available', $content['message']);
        }
    }

    private function createTestLearnerWithSubject(): Learner
    {
        $learner = new Learner();
        $learner->setUid('test_learner_123')
            ->setName('Test Learner')
            ->setCreated(new \DateTime())
            ->setLastSeen(new \DateTime());
        
        $learnerSubject = new Learnersubjects();
        $learnerSubject->setLearner($learner)
            ->setSubject($this->subject)
            ->setLastUpdated(new \DateTime());

        $this->entityManager->persist($learner);
        $this->entityManager->persist($learnerSubject);
        $this->entityManager->flush();

        return $learner;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up test database
        $this->entityManager->createQuery('DELETE FROM App\Entity\Result')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Learnersubjects')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Question')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Learner')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Subject')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Grade')->execute();
        
        $this->entityManager->close();
        $this->entityManager = null;
    }
} 