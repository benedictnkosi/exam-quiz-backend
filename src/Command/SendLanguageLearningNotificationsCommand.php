<?php

namespace App\Command;

use App\Service\LanguageLearningNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-language-notifications',
    description: 'Send notifications to language learners based on their progress'
)]
class SendLanguageLearningNotificationsCommand extends Command
{
    public function __construct(
        private readonly LanguageLearningNotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Sending Language Learning Notifications');

        $result = $this->notificationService->sendProgressNotifications();

        if ($result['status'] === 'OK') {
            $io->success(sprintf(
                'Successfully sent %d notifications to %d learners',
                $result['notificationsSent'],
                $result['totalLearners']
            ));

            if (!empty($result['errors'])) {
                $io->warning(sprintf(
                    'Failed to send %d notifications',
                    count($result['errors'])
                ));
            }

            return Command::SUCCESS;
        }

        $io->error('Failed to send notifications: ' . $result['message']);
        return Command::FAILURE;
    }
}