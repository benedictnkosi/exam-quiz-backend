<?php

namespace App\Controller;

use App\Entity\SmsMarketing;
use App\Repository\SmsMarketingRepository;
use App\Service\SmsPortalService;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/sms-marketing')]
class SmsMarketingController extends AbstractController
{
    public function __construct(
        private SmsPortalService $smsPortalService,
        private SmsMarketingRepository $smsMarketingRepository,
        private EntityManagerInterface $entityManager,
        private WhatsAppService $whatsAppService
    ) {
    }

    #[Route('/send', name: 'send_marketing_sms', methods: ['POST'])]
    public function sendMarketingSms(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $staticMessage = "Start prepping for June exams with Exam Quiz! Practice past papers, boost confidence & track progress. Your friends are on it—join now https://examquiz.co.za";

        $results = [
            'success' => [],
            'failed' => []
        ];

        // If a specific number is provided, send only to that number
        if (isset($data['phoneNumber'])) {
            $phoneNumber = trim($data['phoneNumber']);
            $record = $this->smsMarketingRepository->findOneBy(['phoneNumber' => $phoneNumber]);
            if (!$record) {
                return $this->json(['error' => 'Phone number not found'], 404);
            }
            $response = $this->smsPortalService->sendMarketingSms($record, $staticMessage);
            
            if ($response === false || (is_array($response) && isset($response['error']))) {
                $results['failed'][] = [
                    'number' => $phoneNumber,
                    'error' => is_array($response) ? $response['error'] : 'Failed to send SMS'
                ];
            } else {
                // Update the record in database
                $record->setLastSmsSentAt(new \DateTimeImmutable());
                $record->setLastMessageSent($staticMessage);
                $record->setLastMessageId($response['messageId'] ?? null);
                $this->entityManager->persist($record);
                $this->entityManager->flush();

                $results['success'][] = [
                    'number' => $phoneNumber,
                    'response' => $response
                ];
            }
        } else {
            // Send to 10 numbers where lastSmsSentAt is NULL
            $numbers = $this->smsMarketingRepository->findBy(
                ['lastSmsSentAt' => null],
                ['createdAt' => 'ASC'],
                1
            );

            if (empty($numbers)) {
                return $this->json(['message' => 'No eligible numbers found (no numbers with NULL lastSmsSentAt)']);
            }

            foreach ($numbers as $number) {
                $response = $this->smsPortalService->sendMarketingSms(
                    $number,
                    $staticMessage
                );

                if ($response === false || (is_array($response) && isset($response['error']))) {
                    $results['failed'][] = [
                        'number' => $number->getPhoneNumber(),
                        'error' => is_array($response) ? $response['error'] : 'Failed to send SMS'
                    ];
                } else {
                    // Update the record in database
                    $number->setLastSmsSentAt(new \DateTimeImmutable());
                    $number->setLastMessageSent($staticMessage);
                    $number->setLastMessageId($response['messageId'] ?? null);
                    $this->entityManager->persist($number);
                    
                    $results['success'][] = [
                        'number' => $number->getPhoneNumber(),
                        'response' => $response
                    ];
                }
            }
            
            // Flush all updates at once for bulk sending
            $this->entityManager->flush();
        }

        return $this->json([
            'message' => 'SMS marketing campaign completed',
            'results' => [
                'total_processed' => count($results['success']) + count($results['failed']),
                'success' => count($results['success']),
                'failed' => count($results['failed']),
                'details' => $results
            ]
        ]);
    }

    #[Route('/numbers/bulk', name: 'add_bulk_marketing_numbers', methods: ['POST'])]
    public function addBulkMarketingNumbers(Request $request): JsonResponse
    {
        $content = $request->getContent();
        if (empty($content)) {
            return $this->json(['error' => 'No numbers provided'], 400);
        }

        // Split the content into lines and filter out empty lines
        $numbers = array_filter(explode("\n", $content), 'trim');

        if (empty($numbers)) {
            return $this->json(['error' => 'No valid numbers found'], 400);
        }

        $results = [
            'added' => [],
            'skipped' => [],
            'errors' => []
        ];

        // First, get all existing numbers to avoid multiple database queries
        $existingNumbers = $this->smsMarketingRepository->findAll();
        $existingPhoneNumbers = array_map(function (SmsMarketing $record) {
            return $record->getPhoneNumber();
        }, $existingNumbers);

        foreach ($numbers as $number) {
            // Clean the number and ensure it has +27 prefix
            $number = trim($number);
            $fullNumber = str_starts_with($number, '+27') ? $number : '+27' . $number;

            // Debug the number format
            $debugInfo = [
                'original' => $number,
                'cleaned' => $fullNumber,
                'length' => strlen($fullNumber),
                'matches' => preg_match('/^\+27[0-9]{9}$/', $fullNumber)
            ];
            error_log(json_encode($debugInfo));

            // Validate number format
            if (!preg_match('/^\+27[0-9]{9}$/', $fullNumber)) {
                $results['errors'][] = "Invalid number format: $number (debug: " . json_encode($debugInfo) . ")";
                continue;
            }

            // Check if number already exists
            if (in_array($fullNumber, $existingPhoneNumbers)) {
                $results['skipped'][] = $fullNumber;
                continue;
            }

            try {
                $smsMarketing = new SmsMarketing();
                $smsMarketing->setPhoneNumber($fullNumber);

                $this->entityManager->persist($smsMarketing);
                $results['added'][] = $fullNumber;

                // Add to existing numbers array to prevent duplicates in the same batch
                $existingPhoneNumbers[] = $fullNumber;
            } catch (\Exception $e) {
                $results['errors'][] = "Error adding number $fullNumber: " . $e->getMessage();
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Bulk number addition completed',
            'results' => [
                'total_processed' => count($numbers),
                'added' => count($results['added']),
                'skipped' => count($results['skipped']),
                'errors' => count($results['errors']),
                'details' => $results
            ]
        ]);
    }

    #[Route('/numbers', name: 'add_marketing_number', methods: ['POST'])]
    public function addMarketingNumber(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['phoneNumber'])) {
            return $this->json(['error' => 'Phone number is required'], 400);
        }

        // Check if number already exists
        $phoneNumber = trim($data['phoneNumber']);
        $existing = $this->smsMarketingRepository->findOneBy(['phoneNumber' => $data['phoneNumber']]);
        if ($existing) {
            return $this->json(['error' => 'Phone number already exists'], 400);
        }

        $smsMarketing = new SmsMarketing();
        $smsMarketing->setPhoneNumber($data['phoneNumber']);

        $this->entityManager->persist($smsMarketing);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Phone number added successfully',
            'id' => $smsMarketing->getId()
        ]);
    }

    #[Route('/numbers', name: 'get_marketing_numbers', methods: ['GET'])]
    public function getMarketingNumbers(): JsonResponse
    {
        $numbers = $this->smsMarketingRepository->findAll();

        $data = array_map(function (SmsMarketing $record) {
            return [
                'id' => $record->getId(),
                'phoneNumber' => $record->getPhoneNumber(),
                'createdAt' => $record->getCreatedAt()->format('Y-m-d H:i:s'),
                'lastSmsSentAt' => $record->getLastSmsSentAt()?->format('Y-m-d H:i:s'),
                'isActive' => $record->isActive()
            ];
        }, $numbers);

        return $this->json($data);
    }

    #[Route('/whatsapp/send', name: 'send_whatsapp_marketing', methods: ['POST'])]
    public function sendWhatsAppMarketing(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $staticMessage = "📚 Start your June exam prep early with Exam Quiz! 🚀 
        \nPractice past questions, boost your confidence, and track your progress. 
        \nYour friends are already using Exam Quiz — download now and get ahead! 📝📈
        \n👉 https://examquiz.co.za";

        $results = [
            'success' => [],
            'failed' => []
        ];

        // If a specific number is provided, send only to that number
        if (isset($data['phoneNumber'])) {
            $phoneNumber = trim($data['phoneNumber']);
            $record = $this->smsMarketingRepository->findOneBy(['phoneNumber' => $phoneNumber]);
            if (!$record) {
                return $this->json(['error' => 'Phone number not found'], 404);
            }
            $response = $this->whatsAppService->sendMessage($record->getPhoneNumber(), $staticMessage);
            
            if ($response === false || (is_array($response) && isset($response['error']))) {
                $results['failed'][] = [
                    'number' => $phoneNumber,
                    'error' => is_array($response) ? $response['error'] : 'Failed to send SMS'
                ];
            } else {
                // Update the record in database
                $record->setLastSmsSentAt(new \DateTimeImmutable());
                $record->setLastMessageSent($staticMessage);
                $record->setLastMessageId($response['idMessage'] ?? null);
                $this->entityManager->persist($record);
                $this->entityManager->flush();

                $results['success'][] = [
                    'number' => $phoneNumber,
                    'response' => $response
                ];
            }
        } else {
            // Send to 10 numbers where lastSmsSentAt is NULL
            $numbers = $this->smsMarketingRepository->findBy(
                ['lastSmsSentAt' => null],
                ['createdAt' => 'ASC'],
                30
            );

            if (empty($numbers)) {
                return $this->json(['message' => 'No eligible numbers found (no numbers with NULL lastSmsSentAt)']);
            }

            foreach ($numbers as $number) {
                $response = $this->whatsAppService->sendMessage(
                    $number->getPhoneNumber(),
                    $staticMessage
                );

                if ($response === false || (is_array($response) && isset($response['error']))) {
                    $results['failed'][] = [
                        'number' => $number->getPhoneNumber(),
                        'error' => is_array($response) ? $response['error'] : 'Failed to send SMS'
                    ];
                } else {
                    // Update the record in database
                    $number->setLastSmsSentAt(new \DateTimeImmutable());
                    $number->setLastMessageSent($staticMessage);
                    $number->setLastMessageId($response['idMessage'] ?? null);
                    $this->entityManager->persist($number);
                    
                    $results['success'][] = [
                        'number' => $number->getPhoneNumber(),
                        'response' => $response
                    ];
                }
            }
            
            // Flush all updates at once for bulk sending
            $this->entityManager->flush();
        }

        return $this->json([
            'message' => 'WhatsApp marketing campaign completed',
            'results' => [
                'total_processed' => count($results['success']) + count($results['failed']),
                'success' => count($results['success']),
                'failed' => count($results['failed']),
                'details' => $results
            ]
        ]);
    }
}
