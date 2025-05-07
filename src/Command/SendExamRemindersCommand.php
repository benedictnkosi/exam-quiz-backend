<?php

namespace App\Command;

use App\Service\ExamReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-exam-reminders',
    description: 'Send reminders for upcoming exams to eligible learners'
)]
class SendExamRemindersCommand extends Command
{
    public function __construct(
        private ExamReminderService $examReminderService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days-ahead',
                'd',
                InputOption::VALUE_REQUIRED,
                'Number of days ahead to check for exams',
                7
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $daysAhead = (int) $input->getOption('days-ahead');

        $io->title('Sending Exam Reminders');
        $io->text(sprintf('Checking for exams scheduled in %d days...', $daysAhead));

        $result = $this->examReminderService->sendExamReminders($daysAhead);

        if ($result['status'] === 'OK') {
            $io->success($result['message']);
            if (!empty($result['errors'])) {
                $io->warning('Some notifications failed to send:');
                foreach ($result['errors'] as $error) {
                    $io->text($error);
                }
            }
            return Command::SUCCESS;
        } else {
            $io->error($result['message']);
            return Command::FAILURE;
        }
    }
}