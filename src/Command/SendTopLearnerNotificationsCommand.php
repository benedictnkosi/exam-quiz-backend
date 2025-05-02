<?php

namespace App\Command;

use App\Service\TopLearnerNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-top-learner-notifications',
    description: 'Sends notifications to yesterday\'s top learners for each grade'
)]
class SendTopLearnerNotificationsCommand extends Command
{
    public function __construct(
        private readonly TopLearnerNotificationService $topLearnerNotificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sending Top Learner Notifications');

        $result = $this->topLearnerNotificationService->sendTopLearnerNotifications();

        if ($result['status'] === 'OK') {
            $io->success(sprintf(
                'Successfully sent %d notifications to top learners',
                $result['notificationsSent']
            ));

            if (!empty($result['errors'])) {
                $io->warning(sprintf(
                    'Failed to send %d notifications',
                    count($result['errors'])
                ));
                foreach ($result['errors'] as $error) {
                    $io->writeln(sprintf(
                        '- Learner %s: %s',
                        $error['learnerUid'],
                        $error['error']
                    ));
                }
            }

            return Command::SUCCESS;
        }

        $io->error(sprintf(
            'Failed to send top learner notifications: %s',
            $result['message'] ?? 'Unknown error'
        ));

        return Command::FAILURE;
    }
}