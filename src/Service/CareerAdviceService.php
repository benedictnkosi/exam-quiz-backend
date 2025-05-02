<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CareerAdviceService
{
    private HttpClientInterface $client;
    private string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';
    private LearnerReportService $reportService;
    private const MIN_QUESTIONS_FOR_NEW_ADVICE = 200;

    public function __construct(
        string $openaiApiKey,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        LearnerReportService $reportService
    ) {
        $this->client = HttpClient::create();
        $this->apiKey = $openaiApiKey;
        $this->reportService = $reportService;
    }

    /**
     * Clean subject performance data to only include essential information
     */
    private function cleanSubjectPerformance(array $subjectPerformance): array
    {
        return array_map(function ($subject) {
            return [
                'subject' => $subject['subject'],
                'percentage' => $subject['percentage']
            ];
        }, $subjectPerformance);
    }

    public function getCareerAdvice(string $learnerUid): array
    {
        try {
            // Find the learner
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $learnerUid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Get learner's subject performance
            $subjectPerformance = $this->reportService->getSubjectPerformance($learner);

            // Calculate total questions answered
            $totalQuestionsAnswered = array_reduce($subjectPerformance, function ($carry, $subject) {
                return $carry + $subject['totalAnswers'];
            }, 0);

            // Get existing career advice
            $existingAdvice = $learner->getCareerAdvice();

            // If we have existing advice and not enough new questions, return the existing advice
            if (
                $existingAdvice &&
                isset($existingAdvice['totalQuestionsAnswered']) &&
                $totalQuestionsAnswered - $existingAdvice['totalQuestionsAnswered'] < self::MIN_QUESTIONS_FOR_NEW_ADVICE
            ) {
                return [
                    'status' => 'OK',
                    'data' => [
                        'advice' => $existingAdvice['advice'],
                        'subject_performance' => $subjectPerformance,
                        'last_updated' => $existingAdvice['lastUpdated']
                    ]
                ];
            }

            // Clean the subject performance data for the AI prompt
            $cleanedPerformance = $this->cleanSubjectPerformance($subjectPerformance);

            // Prepare the prompt for OpenAI
            $prompt = "You are a career guidance counselor helping a South African high school student choose their future career path. 
            Based on the student's academic performance in different subjects, provide personalized career advice.
            
            Here is the student's performance in different subjects (showing subject name and percentage score):
            " . json_encode($cleanedPerformance, JSON_PRETTY_PRINT) . "
            
            Please provide:
            🎯 A brief analysis of their strengths and areas of interest based on their academic performance
            
            🚀 3 suitable career options that align with their strengths. For each career option, explain:
               💡 Why it might be a good fit
               📚 Required qualifications and education path
               💼 Job market prospects in South Africa
               💰 Potential salary ranges
            
            💭 Any additional advice or considerations for their career planning

            Keep the tone encouraging and practical, focusing on realistic opportunities in the South African context.";

            $response = $this->client->request('POST', $this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a career guidance counselor helping South African high school students choose their future career paths.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $advice = $data['choices'][0]['message']['content'] ?? 'Failed to generate career advice.';

            // Store the new advice
            $careerAdviceData = [
                'advice' => $advice,
                'totalQuestionsAnswered' => $totalQuestionsAnswered,
                'lastUpdated' => (new \DateTime())->format('Y-m-d H:i:s'),
                'subjectPerformance' => $cleanedPerformance // Store the cleaned version
            ];

            $learner->setCareerAdvice($careerAdviceData);
            $this->entityManager->persist($learner);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'data' => [
                    'advice' => $advice,
                    'last_updated' => $careerAdviceData['lastUpdated']
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Career advice error: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error generating career advice: ' . $e->getMessage()
            ];
        }
    }
}