<?php

namespace App\Tests\Controller;

use App\Tests\TestCase\ApiTestCase;
use App\Entity\Learner;
use App\Entity\Grade;
use App\Entity\Subject;
use App\Entity\ReportedQuestion;

class ReportedQuestionControllerTest extends ApiTestCase
{
    private Grade $grade;
    private Subject $subject;

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

        $this->entityManager->flush();
    }

    public function testCreateReportedQuestion(): void
    {
        $payload = [
            'subject_id' => $this->subject->getId(),
            'question_text' => 'This is a test question that needs to be reported',
            'question_topic' => 'Algebra',
            'sub_topic' => 'Linear Equations'
        ];

        $this->client->request(
            'POST',
            '/api/reported-questions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(201, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $content['status']);
        $this->assertEquals('Reported question created successfully', $content['message']);
        $this->assertArrayHasKey('data', $content);
        $this->assertEquals('Mathematics', $content['data']['subject']);
        $this->assertEquals('This is a test question that needs to be reported', $content['data']['question_text']);
        $this->assertEquals('Algebra', $content['data']['question_topic']);
        $this->assertEquals('Linear Equations', $content['data']['sub_topic']);

        // Verify reported question was created in database
        $reportedQuestion = $this->entityManager->getRepository(ReportedQuestion::class)
            ->findOneBy(['questionText' => 'This is a test question that needs to be reported']);
        $this->assertNotNull($reportedQuestion);
        $this->assertEquals($this->subject->getId(), $reportedQuestion->getSubject()->getId());
    }

    public function testCreateReportedQuestionMissingSubjectId(): void
    {
        $payload = [
            'question_text' => 'This is a test question that needs to be reported',
            'question_topic' => 'Algebra'
        ];

        $this->client->request(
            'POST',
            '/api/reported-questions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('NOK', $content['status']);
        $this->assertEquals('Subject ID is required', $content['message']);
    }

    public function testCreateReportedQuestionMissingQuestionText(): void
    {
        $payload = [
            'subject_id' => $this->subject->getId(),
            'question_topic' => 'Algebra'
        ];

        $this->client->request(
            'POST',
            '/api/reported-questions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('NOK', $content['status']);
        $this->assertEquals('Question text is required', $content['message']);
    }

    public function testCreateReportedQuestionInvalidSubjectId(): void
    {
        $payload = [
            'subject_id' => 99999,
            'question_text' => 'This is a test question that needs to be reported'
        ];

        $this->client->request(
            'POST',
            '/api/reported-questions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('NOK', $content['status']);
        $this->assertEquals('Subject not found', $content['message']);
    }

    public function testGetAllReportedQuestions(): void
    {
        // Create a test reported question first
        $reportedQuestion = new ReportedQuestion();
        $reportedQuestion->setSubject($this->subject);
        $reportedQuestion->setQuestionText('Test question 1');
        $reportedQuestion->setQuestionTopic('Test Topic');
        $reportedQuestion->setSubTopic('Test Sub Topic');
        $this->entityManager->persist($reportedQuestion);

        $reportedQuestion2 = new ReportedQuestion();
        $reportedQuestion2->setSubject($this->subject);
        $reportedQuestion2->setQuestionText('Test question 2');
        $this->entityManager->persist($reportedQuestion2);

        $this->entityManager->flush();

        $this->client->request('GET', '/api/reported-questions');

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $content['status']);
        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('count', $content);
        $this->assertEquals(2, $content['count']);
        $this->assertCount(2, $content['data']);

        // Verify the data structure
        $firstItem = $content['data'][0];
        $this->assertArrayHasKey('id', $firstItem);
        $this->assertArrayHasKey('subject', $firstItem);
        $this->assertArrayHasKey('subject_id', $firstItem);
        $this->assertArrayHasKey('question_text', $firstItem);
        $this->assertArrayHasKey('question_topic', $firstItem);
        $this->assertArrayHasKey('sub_topic', $firstItem);
        $this->assertArrayHasKey('created_at', $firstItem);
        $this->assertArrayHasKey('updated_at', $firstItem);
    }

    public function testDeleteReportedQuestion(): void
    {
        // Create a test reported question first
        $reportedQuestion = new ReportedQuestion();
        $reportedQuestion->setSubject($this->subject);
        $reportedQuestion->setQuestionText('Test question to delete');
        $this->entityManager->persist($reportedQuestion);
        $this->entityManager->flush();

        $reportedQuestionId = $reportedQuestion->getId();

        $this->client->request('DELETE', '/api/reported-questions/' . $reportedQuestionId);

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $content['status']);
        $this->assertEquals('Reported question deleted successfully', $content['message']);

        // Verify the reported question was deleted from database
        $deletedQuestion = $this->entityManager->getRepository(ReportedQuestion::class)
            ->find($reportedQuestionId);
        $this->assertNull($deletedQuestion);
    }

    public function testDeleteReportedQuestionNotFound(): void
    {
        $this->client->request('DELETE', '/api/reported-questions/99999');

        $response = $this->client->getResponse();
        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('NOK', $content['status']);
        $this->assertEquals('Reported question not found', $content['message']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test database
        $this->entityManager->createQuery('DELETE FROM App\Entity\ReportedQuestion')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Subject')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Grade')->execute();

        $this->entityManager->close();
        $this->entityManager = null;
    }
} 