<?php

namespace App\Command;

use App\Entity\CommuteDistanceMessages;
use App\Entity\Commuter;
use App\Repository\CommuteDistanceMessagesRepository;
use App\Repository\CommuterRepository;
use App\Service\CommuteDistanceMessageTrackingService;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:disable-inactive-commute-accounts',
    description: 'Disable accounts with one-sided conversations older than 48 hours'
)]
class DisableInactiveCommuteAccountsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommuteDistanceMessagesRepository $messageTrackingRepository,
        private CommuterRepository $commuterRepository,
        private CommuteDistanceMessageTrackingService $messageTrackingService,
        private PushNotificationService $pushNotificationService,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run without making actual changes (just show what would be done)'
            )
            ->addOption(
                'hours',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of hours to consider as inactive (default: 48)',
                48
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum number of accounts to process (default: 100)',
                100
            )
            ->setHelp('This command finds commute distance message tracking records where only one party sent a message and the other did not respond within the specified hours, then disables those accounts.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $hours = (int) $input->getOption('hours');
        $limit = (int) $input->getOption('limit');

        $io->title('Disable Inactive Commute Accounts');
        $io->text("Checking for one-sided conversations older than {$hours} hours...");

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No actual changes will be made');
        }

        try {
            // Find one-sided conversations older than specified hours
            $oneSidedConversations = $this->findOneSidedConversations($hours, $limit);
            
            if (empty($oneSidedConversations)) {
                $io->success('No one-sided conversations found that meet the criteria.');
                return Command::SUCCESS;
            }

            $io->text(sprintf('Found %d one-sided conversations to process.', count($oneSidedConversations)));

            $processedCount = 0;
            $disabledCount = 0;
            $notificationCount = 0;
            $errors = [];

            foreach ($oneSidedConversations as $conversation) {
                $processedCount++;
                
                try {
                    $result = $this->processOneSidedConversation($conversation, $dryRun);
                    
                    if ($result['disabled']) {
                        $disabledCount++;
                    }
                    
                    if ($result['notification_sent']) {
                        $notificationCount++;
                    }
                    
                    if ($result['error']) {
                        $errors[] = $result['error'];
                    }

                    // Show progress
                    if ($processedCount % 10 === 0) {
                        $io->text("Processed {$processedCount}/" . count($oneSidedConversations));
                    }

                } catch (\Exception $e) {
                    $errors[] = "Error processing conversation ID {$conversation['id']}: " . $e->getMessage();
                    $this->logger->error('Error processing one-sided conversation', [
                        'conversation_id' => $conversation['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Summary
            $io->section('Summary');
            $io->table(
                ['Metric', 'Count'],
                [
                    ['Total Processed', $processedCount],
                    ['Accounts Disabled', $disabledCount],
                    ['Notifications Sent', $notificationCount],
                    ['Errors', count($errors)]
                ]
            );

            if (!empty($errors)) {
                $io->section('Errors');
                foreach ($errors as $error) {
                    $io->error($error);
                }
            }

            if ($dryRun) {
                $io->success('Dry run completed successfully. No actual changes were made.');
            } else {
                $io->success("Successfully processed {$processedCount} conversations. {$disabledCount} accounts disabled.");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Command failed: ' . $e->getMessage());
            $this->logger->error('DisableInactiveCommuteAccountsCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Find one-sided conversations older than specified hours
     */
    private function findOneSidedConversations(int $hours, int $limit): array
    {
        $cutoffDate = new \DateTime("-{$hours} hours");
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('cdm')
            ->from(CommuteDistanceMessages::class, 'cdm')
            ->where('(cdm.passengerLastMessageDate IS NOT NULL AND cdm.driverLastMessageDate IS NULL AND cdm.passengerLastMessageDate < :cutoff)')
            ->orWhere('(cdm.driverLastMessageDate IS NOT NULL AND cdm.passengerLastMessageDate IS NULL AND cdm.driverLastMessageDate < :cutoff)')
            ->setParameter('cutoff', $cutoffDate)
            ->orderBy('cdm.updatedAt', 'ASC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Process a one-sided conversation
     */
    private function processOneSidedConversation(CommuteDistanceMessages $conversation, bool $dryRun): array
    {
        $result = [
            'disabled' => false,
            'notification_sent' => false,
            'error' => null
        ];

        try {
            // Determine which party sent the message and which didn't respond
            $hasPassengerMessage = $conversation->getPassengerLastMessageDate() !== null;
            $hasDriverMessage = $conversation->getDriverLastMessageDate() !== null;
            
            if ($hasPassengerMessage && !$hasDriverMessage) {
                // Passenger sent message, driver didn't respond
                $inactiveParty = 'driver';
                $lastMessageDate = $conversation->getPassengerLastMessageDate();
            } elseif ($hasDriverMessage && !$hasPassengerMessage) {
                // Driver sent message, passenger didn't respond
                $inactiveParty = 'passenger';
                $lastMessageDate = $conversation->getDriverLastMessageDate();
            } else {
                // This shouldn't happen, but just in case
                $result['error'] = 'Invalid conversation state';
                return $result;
            }

            // Get the commute distance to find the commuters
            $distance = $this->entityManager->getRepository(\App\Entity\DriverPassengerDistance::class)
                ->findById($conversation->getCommuteDistanceId());

            if (!$distance) {
                $result['error'] = 'Commute distance not found';
                return $result;
            }

            // Find the inactive commuter
            $inactiveCommuter = $this->findInactiveCommuter($distance, $inactiveParty);
            
            if (!$inactiveCommuter) {
                $result['error'] = "Inactive {$inactiveParty} commuter not found";
                return $result;
            }

            // Check if account is already disabled
            if ($inactiveCommuter->getStatus() === 'inactive') {
                $result['error'] = "Account already disabled";
                return $result;
            }

            if (!$dryRun) {
                // Disable the account
                $inactiveCommuter->setStatus('inactive');
                $this->entityManager->persist($inactiveCommuter);
                $this->entityManager->flush();

                $result['disabled'] = true;

                // Send notification
                $notificationSent = $this->sendAccountDisabledNotification($inactiveCommuter, $lastMessageDate);
                $result['notification_sent'] = $notificationSent;

                $this->logger->info('Account disabled due to one-sided conversation', [
                    'commuter_uid' => $inactiveCommuter->getUid(),
                    'commuter_name' => $inactiveCommuter->getName(),
                    'commute_distance_id' => $conversation->getCommuteDistanceId(),
                    'inactive_party' => $inactiveParty,
                    'last_message_date' => $lastMessageDate->format('Y-m-d H:i:s'),
                    'notification_sent' => $notificationSent
                ]);
            } else {
                // Dry run - just log what would be done
                $this->logger->info('DRY RUN: Would disable account', [
                    'commuter_uid' => $inactiveCommuter->getUid(),
                    'commuter_name' => $inactiveCommuter->getName(),
                    'commute_distance_id' => $conversation->getCommuteDistanceId(),
                    'inactive_party' => $inactiveParty,
                    'last_message_date' => $lastMessageDate->format('Y-m-d H:i:s')
                ]);
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Find the inactive commuter based on the commute distance
     * 
     * Note: This method needs to be implemented based on your actual data structure.
     * The current implementation assumes you have a way to map commute IDs to commuter UIDs.
     * You may need to:
     * 1. Add a findByCommuteId method to CommuterRepository
     * 2. Store commute IDs in the Commuter entity
     * 3. Use a different approach to identify the inactive commuter
     */
    private function findInactiveCommuter(\App\Entity\DriverPassengerDistance $distance, string $inactiveParty): ?Commuter
    {
        // TODO: Implement based on your actual data structure
        // For now, this is a placeholder that returns null
        // You need to implement the logic to find the inactive commuter
        
        $this->logger->warning('findInactiveCommuter method needs implementation', [
            'commute_distance_id' => $distance->getId(),
            'driver_commute_id' => $distance->getDriverCommuteId(),
            'passenger_commute_id' => $distance->getPassengerCommuteId(),
            'inactive_party' => $inactiveParty
        ]);
        
        return null;
        
        // Example implementation (uncomment and modify as needed):
        /*
        if ($inactiveParty === 'driver') {
            // Find driver by driver_commute_id
            return $this->commuterRepository->findByCommuteId($distance->getDriverCommuteId());
        } else {
            // Find passenger by passenger_commute_id
            return $this->commuterRepository->findByCommuteId($distance->getPassengerCommuteId());
        }
        */
    }

    /**
     * Send notification to the disabled account
     */
    private function sendAccountDisabledNotification(Commuter $commuter, \DateTimeInterface $lastMessageDate): bool
    {
        try {
            $pushToken = $commuter->getPushNotificationToken();
            
            if (!$pushToken) {
                $this->logger->warning('No push token available for disabled commuter', [
                    'commuter_uid' => $commuter->getUid()
                ]);
                return false;
            }

            $hoursSinceLastMessage = (new \DateTime())->diff($lastMessageDate)->h + 
                                   (new \DateTime())->diff($lastMessageDate)->days * 24;

            $notification = [
                'to' => $pushToken,
                'title' => 'Account Temporarily Paused',
                'body' => "Your account has been paused due to inactivity. You haven't responded to a message for {$hoursSinceLastMessage} hours. Contact support to reactivate your account.",
                'data' => [
                    'type' => 'account_disabled',
                    'commuter_uid' => $commuter->getUid(),
                    'reason' => 'one_sided_conversation',
                    'hours_inactive' => $hoursSinceLastMessage,
                    'timestamp' => time()
                ]
            ];

            $result = $this->pushNotificationService->sendPushNotification($notification);

            if ($result['status'] === 'OK') {
                $this->logger->info('Account disabled notification sent successfully', [
                    'commuter_uid' => $commuter->getUid(),
                    'hours_inactive' => $hoursSinceLastMessage
                ]);
                return true;
            } else {
                $this->logger->error('Failed to send account disabled notification', [
                    'commuter_uid' => $commuter->getUid(),
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
                return false;
            }

        } catch (\Exception $e) {
            $this->logger->error('Error sending account disabled notification', [
                'commuter_uid' => $commuter->getUid(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 