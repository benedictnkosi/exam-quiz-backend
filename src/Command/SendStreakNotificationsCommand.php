<?php

namespace App\Command;

use App\Service\PushNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:send-streak-notifications',
    description: 'Sends notifications to learners who have not logged in for a while'
)]
class SendStreakNotificationsCommand extends Command
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sending streak notifications');

        try {
            $result = $this->pushNotificationService->sendNotificationsToInactiveUsers();

            if ($result['status'] === 'OK') {
                $io->success(sprintf(
                    'Processed %d learners: %d successful, %d failed',
                    $result['totalInactiveUsers'],
                    $result['notificationsSent'],
                    count($result['errors'])
                ));

                if (!empty($result['errors'])) {    
                    $io->warning('Failed notifications:');
                    foreach ($result['errors'] as $error) {
                        $io->writeln(sprintf(
                            '- Learner %s: %s',
                            $error['learnerUid'],
                            $error['error']
                        ));
                    }
                }

                return Command::SUCCESS;
            } else {
                $io->error('Failed to send streak notifications: ' . ($result['message'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in streak notifications command: ' . $e->getMessage());
            $io->error('Error in streak notifications command: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 