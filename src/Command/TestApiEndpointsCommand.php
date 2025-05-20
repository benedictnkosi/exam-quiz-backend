<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TestApiEndpointsCommand extends Command
{
    protected static $defaultName = 'app:test-api-endpoints';
    private $httpClient;
    private $baseUrl;

    public function __construct(HttpClientInterface $httpClient)
    {
        parent::__construct();
        $this->httpClient = $httpClient;
        $this->baseUrl = gethostname() && strpos(gethostname(), 'dedicated') !== false
            ? 'https://examquiz.dedicated.co.za'  // Replace with your actual dedicated URL
            : 'http://127.0.0.1:8000';
    }

    protected function configure()
    {
        $this
            ->setDescription('Test API endpoints for learner creation, retrieval, and quiz generation');
    }

    private function generateRandomEmail(): string
    {
        $randomString = bin2hex(random_bytes(8));
        return "test.user.{$randomString}@example.com";
    }

    private function generateRandomUid(): string
    {
        $randomString = bin2hex(random_bytes(8));
        return $randomString;
    }

    private function generateUidWithTimestamp(): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        return sprintf('%x%s', $timestamp, $random);
    }

    private function generateUidWithUniqid(): string
    {
        return uniqid('', true);
    }

    private function generateUidWithCombined(): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        $uniq = substr(uniqid('', true), -6);
        return sprintf('%x%s%s', $timestamp, $random, $uniq);
    }

    private function cleanupLearner(SymfonyStyle $io, string $uid): void
    {
        try {
            // Delete learner results
            $io->section('Cleaning up: Deleting learner results');
            $response = $this->httpClient->request('DELETE', $this->baseUrl . '/public/learn/learner/delete?uid=' . $uid);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to delete learner results: ' . $response->getContent(false));
            }

            $deleteResultsResponse = json_decode($response->getContent(), true);
            $io->success('Learner results deleted successfully');
            $io->text('Response: ' . json_encode($deleteResultsResponse, JSON_PRETTY_PRINT));



        } catch (\Exception $e) {
            $io->error('Cleanup Error: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Testing API Endpoints');

        try {
            // Step 1: Create a learner
            $io->section('Step 1: Creating a learner');
            $learnerData = [
                "uid" => $this->generateUidWithCombined(),
                'name' => 'Automation',
                'grade' => '12',
                'school_name' => 'Parkhill',
                'school_address' => '123 station road',
                'school_latitude' => 1234,
                'school_longitude' => 123,
                'terms' => '1,2,4',
                'curriculum' => 'IEB,CAPS',
                'email' => $this->generateRandomEmail(),
                'avatar' => '5.png'
            ];

            $io->text('Creating learner with email: ' . $learnerData['email']);

            $response = $this->httpClient->request('POST', $this->baseUrl . '/public/learn/learner/create', [
                'json' => $learnerData
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to create learner: ' . $response->getContent(false));
            }

            $createResponse = json_decode($response->getContent(), true);

            // Debug the response
            $io->text('Response from learner creation:');
            $io->text(json_encode($createResponse, JSON_PRETTY_PRINT));

            if ($createResponse['status'] !== 'OK') {
                throw new \Exception('Failed to create learner: ' . $createResponse['message']);
            }

            $io->success('Learner created successfully');
            $io->text('Message: ' . $createResponse['message']);

            // Step 2: Get learner details
            $io->section('Step 2: Getting learner details');
            $response = $this->httpClient->request('GET', $this->baseUrl . '/public/learn/learner', [
                'query' => [
                    'uid' => $learnerData['uid']
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to get learner: ' . $response->getContent(false));
            }

            $learnerDetails = json_decode($response->getContent(), true);
            $io->success('Learner details retrieved successfully');
            $io->text('Learner details:');
            $io->text(json_encode($learnerDetails, JSON_PRETTY_PRINT));

            if (!isset($learnerDetails['uid'])) {
                throw new \Exception('Learner UID not found in response');
            }

            // Step 3: Get random quiz
            $io->section('Step 3: Getting random quiz');
            $response = $this->httpClient->request('GET', $this->baseUrl . '/public/learn/question/byname', [
                'query' => [
                    'paper_name' => 'P1',
                    'question_id' => 0,
                    'subject_name' => 'Agricultural Sciences',
                    'uid' => $learnerDetails['uid']
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to get quiz: ' . $response->getContent(false));
            }

            $quiz = json_decode($response->getContent(), true);

            // Validate quiz response structure
            $requiredFields = ['id', 'type', 'answer', 'options', 'term', 'active', 'year'];
            foreach ($requiredFields as $field) {
                if (!isset($quiz[$field])) {
                    throw new \Exception("Missing required field in quiz response: {$field}");
                }
            }

            $io->success('Quiz retrieved successfully');

            // Step 4: Get random quiz by topic
            $io->section('Step 4: Getting random quiz by topic');
            $response = $this->httpClient->request('GET', $this->baseUrl . '/public/learn/question/byname', [
                'query' => [
                    'paper_name' => 'P1',
                    'question_id' => 0,
                    'subject_name' => 'Agricultural Sciences',
                    'uid' => $learnerDetails['uid'],
                    'topic' => 'Animal Nutrition'
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to get quiz: ' . $response->getContent(false));
            }

            $quiz = json_decode($response->getContent(), true);

            // Validate quiz response structure
            $requiredFields = ['id', 'type', 'answer', 'options', 'term', 'active', 'year'];
            foreach ($requiredFields as $field) {
                if (!isset($quiz[$field])) {
                    throw new \Exception("Missing required field in quiz response: {$field}");
                }
            }

            $io->success('Quiz retrieved successfully');

            // Step 5: Check answer
            $io->section('Step 5: Checking answer');
            $checkAnswerData = [
                'uid' => $learnerDetails['uid'],
                'question_id' => $quiz['id'],
                'answer' => $quiz['answer'], // Using the correct answer from the quiz
                'answers' => [],
                'requesting_type' => 'real',
                'duration' => 60
            ];

            $response = $this->httpClient->request('POST', $this->baseUrl . '/public/learn/learner/check-answer', [
                'json' => $checkAnswerData
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to check answer: ' . $response->getContent(false));
            }

            $checkAnswerResponse = json_decode($response->getContent(), true);

            // Validate check answer response structure
            $requiredFields = ['status', 'correct', 'correctAnswer'];
            foreach ($requiredFields as $field) {
                if (!isset($checkAnswerResponse[$field])) {
                    throw new \Exception("Missing required field in check answer response: {$field}");
                }
            }

            if ($checkAnswerResponse['status'] !== 'OK') {
                throw new \Exception('Check answer failed: ' . ($checkAnswerResponse['message'] ?? 'Unknown error'));
            }

            $io->success('Answer checked successfully');
            $io->text('Response:');
            $io->text('Status: ' . $checkAnswerResponse['status']);
            $io->text('Correct: ' . ($checkAnswerResponse['correct'] ? 'Yes' : 'No'));
            $io->text('Correct Answer: ' . $checkAnswerResponse['correctAnswer']);

            // Cleanup
            $this->cleanupLearner($io, $learnerDetails['uid']);

            $io->text('Base URL: ' . $this->baseUrl);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}