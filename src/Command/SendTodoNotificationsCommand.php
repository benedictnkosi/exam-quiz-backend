<?php

namespace App\Command;

use App\Service\TodoNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:send-todo-notifications',
    description: 'Sends notifications for todos due today, tomorrow, and in 3 days'
)]
class SendTodoNotificationsCommand extends Command
{
    public function __construct(
        private readonly TodoNotificationService $todoNotificationService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->logger->info('Starting todo notifications command');
            
            $result = $this->todoNotificationService->sendDueDateNotifications();
            
            if ($result['success']) {
                $this->logger->info('Todo notifications sent successfully');
                $output->writeln('Todo notifications sent successfully');
                return Command::SUCCESS;
            } else {
                $this->logger->error('Failed to send todo notifications: ' . ($result['error'] ?? 'Unknown error'));
                $output->writeln('Failed to send todo notifications: ' . ($result['error'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in todo notifications command: ' . $e->getMessage());
            $output->writeln('Error in todo notifications command: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 
