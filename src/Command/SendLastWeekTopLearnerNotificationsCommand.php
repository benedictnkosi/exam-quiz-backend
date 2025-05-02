<?php

namespace App\Command;

use App\Service\TopLearnerNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-last-week-top-learner-notifications',
    description: 'Sends notifications to last week\'s top learners for each grade'
)]
class SendLastWeekTopLearnerNotificationsCommand extends Command
{
    public function __construct(
        private readonly TopLearnerNotificationService $topLearnerNotificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sending Last Week\'s Top Learner Notifications');

        $result = $this->topLearnerNotificationService->sendLastWeekTopLearnerNotifications();

        if ($result['status'] === 'OK') {
            $io->success(sprintf(
                'Successfully sent %d notifications to last week\'s top learners',
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
            'Failed to send last week\'s top learner notifications: %s',
            $result['message'] ?? 'Unknown error'
        ));

        return Command::FAILURE;
    }
}