<?php

namespace App\Command;

use App\Service\GradeMessageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(
    name: 'app:send-grade-message',
    description: 'Sends a message to all learners in a specific grade'
)]
class SendGradeMessageCommand extends Command
{
    public function __construct(
        private readonly GradeMessageService $gradeMessageService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('grade', InputArgument::REQUIRED, 'The grade number to send the message to (e.g., 12 for Grade 12)')
            ->addArgument('title', InputArgument::REQUIRED, 'The title of the message')
            ->addArgument('message', InputArgument::REQUIRED, 'The message body')
            ->addArgument('lastseen', InputArgument::REQUIRED, 'The number of days since the last seen date');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $grade = (int) $input->getArgument('grade');
        $title = $input->getArgument('title');
        $message = $input->getArgument('message');
        $lastSeen = $input->getArgument('lastseen');

        $io->title('Sending Message to Grade ' . $grade);

        $result = $this->gradeMessageService->sendMessageToGrade($grade, $title, $message, $lastSeen);

        if ($result['status'] === 'OK') {
            $io->success(sprintf(
                'Successfully sent %d notifications to %d learners in Grade %d',
                $result['notificationsSent'],
                $result['totalLearners'],
                $grade
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
            'Failed to send grade message: %s',
            $result['message'] ?? 'Unknown error'
        ));

        return Command::FAILURE;
    }
}