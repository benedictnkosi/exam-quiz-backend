<?php

namespace App\Command;

use App\Service\EngagedLearnerNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-engaged-learner-notifications',
    description: 'Sends push notifications to learners who have answered more than 10 questions this week'
)]
class SendEngagedLearnerNotificationsCommand extends Command
{
    public function __construct(
        private readonly EngagedLearnerNotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sending notifications to engaged learners');

        try {
            $results = $this->notificationService->sendNotificationsToEngagedLearners();

            $io->success(sprintf(
                'Processed %d learners: %d successful, %d failed, %d skipped',
                count($results['success']) + count($results['failed']) + count($results['skipped']),
                count($results['success']),
                count($results['failed']),
                count($results['skipped'])
            ));

            if (!empty($results['failed'])) {
                $io->warning('Failed notifications:');
                foreach ($results['failed'] as $failure) {
                    $io->writeln(sprintf(
                        '- Learner %s: %s',
                        $failure['learner'],
                        $failure['error']
                    ));
                }
            }

            if (!empty($results['skipped'])) {
                $io->note('Skipped learners:');
                foreach ($results['skipped'] as $skip) {
                    $io->writeln(sprintf(
                        '- Learner %s: %s',
                        $skip['learner'],
                        $skip['reason']
                    ));
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Error sending notifications: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
} 