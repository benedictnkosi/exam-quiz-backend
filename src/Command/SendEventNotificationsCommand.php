<?php

namespace App\Command;

use App\Service\EventNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:send-event-notifications',
    description: 'Sends notifications for events scheduled today, tomorrow, and in 3 days'
)]
class SendEventNotificationsCommand extends Command
{
    public function __construct(
        private readonly EventNotificationService $eventNotificationService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->logger->info('Starting event notifications command');

            $result = $this->eventNotificationService->sendEventNotifications();

            if ($result['success']) {
                $this->logger->info('Event notifications sent successfully');
                $output->writeln('Event notifications sent successfully');
                return Command::SUCCESS;
            } else {
                $this->logger->error('Failed to send event notifications: ' . ($result['error'] ?? 'Unknown error'));
                $output->writeln('Failed to send event notifications: ' . ($result['error'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in event notifications command: ' . $e->getMessage());
            $output->writeln('Error in event notifications command: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}