<?php

namespace App\Tests\Controller;

use App\Tests\TestCase\ApiTestCase;
use App\Entity\LearnerCompletedChapter;

class LearnerCompletedChapterControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testAddCompletedChapter(): void
    {
        $payload = [
            'learnerUid' => 'test-learner-123',
            'profileUid' => 'test-profile-456',
            'chapterName' => 'Chapter 1: Introduction',
            'bookTitle' => 'The Adventure Begins',
            'duration' => 1200,
            'score' => 85
        ];

        $this->client->request(
            'POST',
            '/api/learner-completed-chapters',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(201, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('test-learner-123', $content['learnerUid']);
        $this->assertEquals('test-profile-456', $content['profileUid']);
        $this->assertEquals('Chapter 1: Introduction', $content['chapterName']);
        $this->assertEquals('The Adventure Begins', $content['bookTitle']);
        $this->assertEquals(1200, $content['duration']);
        $this->assertEquals(85, $content['score']);
        $this->assertEquals('Chapter completed successfully', $content['message']);
        $this->assertArrayHasKey('id', $content);
        $this->assertArrayHasKey('completedAt', $content);

        // Verify completed chapter was created in database
        $completedChapter = $this->entityManager->getRepository(LearnerCompletedChapter::class)
            ->findOneBy(['learnerUid' => 'test-learner-123', 'chapterName' => 'Chapter 1: Introduction']);
        $this->assertNotNull($completedChapter);
        $this->assertEquals('test-profile-456', $completedChapter->getProfileUid());
        $this->assertEquals('The Adventure Begins', $completedChapter->getBookTitle());
        $this->assertEquals(1200, $completedChapter->getDuration());
        $this->assertEquals(85, $completedChapter->getScore());
    }

    public function testAddCompletedChapterMissingLearnerUid(): void
    {
        $payload = [
            'chapterName' => 'Chapter 1: Introduction',
            'bookTitle' => 'The Adventure Begins'
        ];

        $this->client->request(
            'POST',
            '/api/learner-completed-chapters',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Learner UID is required', $content['error']);
    }

    public function testAddCompletedChapterMissingChapterName(): void
    {
        $payload = [
            'learnerUid' => 'test-learner-123',
            'bookTitle' => 'The Adventure Begins'
        ];

        $this->client->request(
            'POST',
            '/api/learner-completed-chapters',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Chapter name is required', $content['error']);
    }

    public function testAddCompletedChapterMissingBookTitle(): void
    {
        $payload = [
            'learnerUid' => 'test-learner-123',
            'chapterName' => 'Chapter 1: Introduction'
        ];

        $this->client->request(
            'POST',
            '/api/learner-completed-chapters',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Book title is required', $content['error']);
    }

    public function testAddCompletedChapterMissingProfileUid(): void
    {
        $payload = [
            'learnerUid' => 'test-learner-123',
            'chapterName' => 'Chapter 1: Introduction',
            'bookTitle' => 'The Adventure Begins'
        ];

        $this->client->request(
            'POST',
            '/api/learner-completed-chapters',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Profile UID is required', $content['error']);
    }

    public function testGetCompletedChaptersByLearner(): void
    {
        // Create test completed chapters first
        $completedChapter1 = new LearnerCompletedChapter();
        $completedChapter1->setLearnerUid('test-learner-123');
        $completedChapter1->setProfileUid('test-profile-456');
        $completedChapter1->setChapterName('Chapter 1: Introduction');
        $completedChapter1->setBookTitle('The Adventure Begins');
        $completedChapter1->setDuration(1200);
        $completedChapter1->setScore(85);
        $this->entityManager->persist($completedChapter1);

        $completedChapter2 = new LearnerCompletedChapter();
        $completedChapter2->setLearnerUid('test-learner-123');
        $completedChapter2->setProfileUid('test-profile-456');
        $completedChapter2->setChapterName('Chapter 2: The Journey');
        $completedChapter2->setBookTitle('The Adventure Begins');
        $completedChapter2->setDuration(1500);
        $completedChapter2->setScore(92);
        $this->entityManager->persist($completedChapter2);

        $this->entityManager->flush();

        $this->client->request('GET', '/api/learner-completed-chapters/learner/test-learner-123');

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('test-learner-123', $content['learnerUid']);
        $this->assertEquals(2, $content['totalCount']);
        $this->assertCount(2, $content['completedChapters']);

        // Verify the data structure
        $firstChapter = $content['completedChapters'][0];
        $this->assertArrayHasKey('id', $firstChapter);
        $this->assertArrayHasKey('learnerUid', $firstChapter);
        $this->assertArrayHasKey('profileUid', $firstChapter);
        $this->assertArrayHasKey('chapterName', $firstChapter);
        $this->assertArrayHasKey('bookTitle', $firstChapter);
        $this->assertArrayHasKey('completedAt', $firstChapter);
        $this->assertArrayHasKey('duration', $firstChapter);
        $this->assertArrayHasKey('score', $firstChapter);
    }

    public function testGetCompletedChaptersByLearnerEmpty(): void
    {
        $this->client->request('GET', '/api/learner-completed-chapters/learner/non-existent-learner');

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('non-existent-learner', $content['learnerUid']);
        $this->assertEquals(0, $content['totalCount']);
        $this->assertCount(0, $content['completedChapters']);
    }

    public function testGetCompletedChaptersCountByLearner(): void
    {
        // Create test completed chapters first
        $completedChapter1 = new LearnerCompletedChapter();
        $completedChapter1->setLearnerUid('test-learner-123');
        $completedChapter1->setProfileUid('test-profile-456');
        $completedChapter1->setChapterName('Chapter 1: Introduction');
        $completedChapter1->setBookTitle('The Adventure Begins');
        $completedChapter1->setDuration(1200);
        $completedChapter1->setScore(85);
        $this->entityManager->persist($completedChapter1);

        $completedChapter2 = new LearnerCompletedChapter();
        $completedChapter2->setLearnerUid('test-learner-123');
        $completedChapter2->setProfileUid('test-profile-456');
        $completedChapter2->setChapterName('Chapter 2: The Journey');
        $completedChapter2->setBookTitle('The Adventure Begins');
        $completedChapter2->setDuration(1500);
        $completedChapter2->setScore(92);
        $this->entityManager->persist($completedChapter2);

        $this->entityManager->flush();

        $this->client->request('GET', '/api/learner-completed-chapters/learner/test-learner-123/count');

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('test-learner-123', $content['learnerUid']);
        $this->assertEquals(2, $content['totalCompletedChapters']);
    }

    public function testCheckChapterCompletion(): void
    {
        // Create a test completed chapter first
        $completedChapter = new LearnerCompletedChapter();
        $completedChapter->setLearnerUid('test-learner-123');
        $completedChapter->setProfileUid('test-profile-456');
        $completedChapter->setChapterName('Chapter 1: Introduction');
        $completedChapter->setBookTitle('The Adventure Begins');
        $completedChapter->setDuration(1200);
        $completedChapter->setScore(85);
        $this->entityManager->persist($completedChapter);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/learner-completed-chapters/learner/test-learner-123/chapter/Chapter 1: Introduction/check');

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('test-learner-123', $content['learnerUid']);
        $this->assertEquals('Chapter 1: Introduction', $content['chapterName']);
        $this->assertTrue($content['isCompleted']);
    }

    public function testCheckChapterCompletionNotCompleted(): void
    {
        $this->client->request('GET', '/api/learner-completed-chapters/learner/test-learner-123/chapter/Non-existent Chapter/check');

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('test-learner-123', $content['learnerUid']);
        $this->assertEquals('Non-existent Chapter', $content['chapterName']);
        $this->assertFalse($content['isCompleted']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
} 