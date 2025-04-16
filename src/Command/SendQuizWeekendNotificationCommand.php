<?php

namespace App\Command;

use App\Service\PushNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(
    name: 'app:send-quiz-weekend-notification',
    description: 'Sends quiz weekend notifications to learners in a specific grade'
)]
class SendQuizWeekendNotificationCommand extends Command
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
        $io->title('Sending quiz weekend notifications');

        try {
            $gradeId = $input->getArgument('grade');
            $result = $this->pushNotificationService->sendQuizWeekendNotification($gradeId);

            if ($result['status'] === 'OK') {
                $io->success(sprintf(
                    'Processed %d learners: %d successful, %d failed',
                    $result['totalLearners'],
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
                $io->error('Failed to send quiz weekend notifications: ' . ($result['message'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in quiz weekend notifications command: ' . $e->getMessage());
            $io->error('Error in quiz weekend notifications command: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Sends quiz weekend notifications to learners in a specific grade')
            ->addArgument('grade', InputArgument::REQUIRED, 'Grade ID to send notifications to')
        ;
    }
} 