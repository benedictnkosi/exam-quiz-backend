<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;
use App\Entity\Subscription;

class PaymentProofService
{
    public function __construct(
        private readonly OpenAIService $openAIService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function processPaymentProof(?Learner $learner, UploadedFile $file): array
    {
        try {
            $allowedMimeTypes = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/jpg'
            ];

            if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                throw new \Exception('File must be a PDF or image (JPEG, PNG). Received: ' . $file->getMimeType());
            }

            // Upload the file to OpenAI
            $fileResponse = $this->openAIService->uploadFile($file);

            if (!isset($fileResponse['id'])) {
                throw new \Exception('Failed to upload file to OpenAI');
            }

            // Create a prompt for GPT-4 Vision to analyze the payment proof
            $prompt = "Analyze this payment proof document and extract the following information:
            1. Payment date (in YYYY-MM-DD format)
            2. Amount paid (in decimal format)
            3. Reference number (if available)

            Return the information in JSON format:
            {
                \"payment_date\": \"YYYY-MM-DD\",
                \"amount\": 0.00,
                \"reference\": \"string\"
            }

            If any field cannot be determined, use null as its value.";

            // Prepare request body
            $requestBody = [
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a data extraction assistant. Your job is to extract payment information from documents. Respond only with a JSON object containing payment_date, amount, and reference fields. Do not include any explanations or additional formatting.'
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'file',
                                'file' => [
                                    'file_id' => $fileResponse['id']
                                ]
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Extract the following information from the payment proof document:
                                1. Payment date (in YYYY-MM-DD format)
                                2. Amount paid (in decimal format)
                                3. Reference number (if available)

                                Return the information in JSON format:
                                {
                                    "payment_date": "YYYY-MM-DD",
                                    "amount": 0.00,
                                    "reference": "string"
                                }

                                If any field cannot be determined, use null as its value.'
                            ]
                        ]
                    ]
                ]
            ];

            // Log the request
            $this->logger->info('OpenAI API Request', [
                'file_id' => $fileResponse['id'],
                'request_body' => $requestBody
            ]);

            // Make the API call to analyze the document
            $response = $this->openAIService->getClient()->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAIService->getApiKey(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestBody
            ]);

            $data = json_decode($response->getContent(), true);

            // Log the raw response
            $this->logger->info('OpenAI API Raw Response', [
                'response' => $data
            ]);

            // Extract JSON from markdown code block if present
            $content = $data['choices'][0]['message']['content'];
            if (strpos($content, '```json') !== false) {
                $content = preg_replace('/^```json\n|\n```$/', '', $content);
            }
            $extractedData = json_decode($content, true);

            // Log the parsed data
            $this->logger->info('OpenAI API Parsed Data', [
                'extracted_data' => $extractedData
            ]);

            // Find learner by follow_me_code if reference is provided
            if (isset($extractedData['reference']) && !$learner) {
                $learner = $this->entityManager->getRepository(Learner::class)
                    ->findOneBy(['followMeCode' => $extractedData['reference']]);

                $this->logger->info('Learner lookup by follow_me_code', [
                    'follow_me_code' => $extractedData['reference'],
                    'learner_found' => $learner !== null,
                    'learner_id' => $learner?->getId()
                ]);

                if (!$learner) {
                    return [
                        'status' => 'NOK',
                        'message' => 'No learner found with the provided reference code: ' . $extractedData['reference']
                    ];
                }
            }

            // Check for duplicate payment
            if (isset($extractedData['amount']) && isset($extractedData['payment_date']) && isset($extractedData['reference'])) {
                $existingSubscription = $this->entityManager->getRepository(Subscription::class)
                    ->createQueryBuilder('s')
                    ->where('s.amount = :amount')
                    ->andWhere('s.paymentDate = :payment_date')
                    ->andWhere('s.learner = :learner')
                    ->setParameter('amount', $extractedData['amount'])
                    ->setParameter('payment_date', new \DateTime($extractedData['payment_date']))
                    ->setParameter('learner', $learner)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($existingSubscription) {
                    $this->logger->warning('Duplicate payment detected', [
                        'amount' => $extractedData['amount'],
                        'payment_date' => $extractedData['payment_date'],
                        'learner_id' => $learner->getId()
                    ]);

                    return [
                        'status' => 'NOK',
                        'message' => 'This payment has already been processed. Duplicate payment detected.'
                    ];
                }
            }

            // Create or update subscription
            if (isset($extractedData['amount']) && $extractedData['amount'] > 0) {
                $subscription = new Subscription();

                // Set payment date
                $subscription->setPaymentDate(new \DateTime($extractedData['payment_date']));

                // Check for active subscription
                $activeSubscription = $this->entityManager->getRepository(Subscription::class)
                    ->createQueryBuilder('s')
                    ->where('s.learner = :learner')
                    ->andWhere('s.endDate > :now')
                    ->setParameter('learner', $learner)
                    ->setParameter('now', new \DateTime())
                    ->orderBy('s.endDate', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($activeSubscription) {
                    // Set new subscription start date to the end date of current subscription
                    $subscription->setCreated(new \DateTime($activeSubscription->getEndDate()->format('Y-m-d')));
                    $this->logger->info('Active subscription found, setting new subscription start date', [
                        'current_end_date' => $activeSubscription->getEndDate()->format('Y-m-d'),
                        'learner_id' => $learner->getId()
                    ]);
                } else {
                    $subscription->setCreated(new \DateTime());
                }

                $subscription->setAmount($extractedData['amount']);

                // Calculate end date based on amount
                $endDate = new \DateTime($subscription->getCreated()->format('Y-m-d'));
                if ($extractedData['amount'] >= 198) {
                    $endDate->modify('+1 year');
                    if ($learner) {
                        $learner->setSubscription('gold_annual');
                    }
                } else if ($extractedData['amount'] >= 28) {
                    // Calculate days based on amount (R1 per day)
                    $days = floor($extractedData['amount']);
                    $endDate->modify("+{$days} days");
                    if ($learner) {
                        $learner->setSubscription('gold_monthly');
                    }
                }
                $subscription->setEndDate($endDate);

                if ($learner) {
                    $subscription->setLearner($learner);
                    $this->entityManager->persist($learner);
                }

                $this->entityManager->persist($subscription);
                $this->entityManager->flush();

                $this->logger->info('Subscription created/updated', [
                    'subscription_id' => $subscription->getId(),
                    'amount' => $extractedData['amount'],
                    'start_date' => $subscription->getCreated()->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'learner_subscription' => $learner?->getSubscription()
                ]);
            }

            // Delete the uploaded file
            $this->openAIService->deleteFile($fileResponse['id']);

            // Log the extracted data
            $this->logger->info('Payment proof processed', [
                'learner_id' => $learner?->getId(),
                'extracted_data' => $extractedData
            ]);

            return [
                'status' => 'OK',
                'data' => $extractedData,
                'subscription' => [
                    'end_date' => $endDate->format('Y-m-d'),
                    'type' => $extractedData['amount'] >= 198 ? 'gold_annual' : 'gold_monthly'
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error processing payment proof: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'status' => 'NOK',
                'message' => 'Failed to process payment proof: ' . $e->getMessage()
            ];
        }
    }
}