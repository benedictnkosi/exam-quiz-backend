<?php

namespace App\Command;

use App\Service\ExamPaperProcessorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:extract-question-numbers',
    description: 'Extract question numbers from pending exam papers',
)]
class ExtractQuestionNumbersCommand extends Command
{
    public function __construct(
        private ExamPaperProcessorService $processorService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Extracting Question Numbers from Exam Papers');

        try {
            $results = $this->processorService->processPendingPapers();

            if (!empty($results['success'])) {
                $io->success(sprintf(
                    'Successfully processed %d papers: %s',
                    count($results['success']),
                    implode(', ', $results['success'])
                ));
            }

            if (!empty($results['errors'])) {
                $io->warning(sprintf(
                    'Failed to process %d papers:',
                    count($results['errors'])
                ));

                foreach ($results['errors'] as $error) {
                    $io->error(sprintf(
                        'Paper ID %d: %s',
                        $error['id'],
                        $error['error']
                    ));
                }
            }

            if (empty($results['success']) && empty($results['errors'])) {
                $io->info('No pending papers found to process');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}