<?php

namespace App\Command;

use App\Service\InactiveLearnerNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-inactive-learner-notifications',
    description: 'Sends notifications to learners who have registered but never answered any questions'
)]
class SendInactiveLearnerNotificationsCommand extends Command
{
    public function __construct(
        private readonly InactiveLearnerNotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sending notifications to inactive learners');

        $result = $this->notificationService->sendNotificationsToInactiveLearners();

        if ($result['status'] === 'OK') {
            $io->success(sprintf(
                'Successfully sent %d notifications to %d inactive learners',
                $result['notificationsSent'],
                $result['totalInactiveLearners']
            ));

            if (!empty($result['errors'])) {
                $io->warning(sprintf(
                    'Failed to send %d notifications',
                    count($result['errors'])
                ));
            }

            return Command::SUCCESS;
        }

        $io->error('Failed to send notifications: ' . ($result['message'] ?? 'Unknown error'));
        return Command::FAILURE;
    }
}