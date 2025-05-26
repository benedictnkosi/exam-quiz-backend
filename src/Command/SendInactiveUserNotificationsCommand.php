<?php

namespace App\Command;

use App\Service\PushNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-inactive-user-notifications',
    description: 'Send notifications to users who haven\'t logged in for 14 days'
)]
class SendInactiveUserNotificationsCommand extends Command
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sending Inactive User Notifications');

        $result = $this->pushNotificationService->sendInactiveUserNotifications();

        if ($result['status'] === 'OK') {
            $io->success(sprintf(
                'Successfully sent %d notifications to inactive users',
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