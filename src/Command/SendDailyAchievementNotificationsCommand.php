<?php

namespace App\Command;

use App\Service\PushNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-daily-achievement-notifications',
    description: 'Send notifications to learners who completed 15 or more quizzes or lessons yesterday'
)]
class SendDailyAchievementNotificationsCommand extends Command
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sending Daily Achievement Notifications');

        $result = $this->pushNotificationService->sendDailyAchievementNotifications();

        if ($result['status'] === 'OK') {
            $io->success(sprintf(
                'Successfully sent %d notifications to eligible learners',
                $result['notificationsSent']
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