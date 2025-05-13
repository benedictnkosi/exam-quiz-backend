<?php

namespace App\Command;

use App\Service\ExamPaperProcessorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-exam-papers',
    description: 'Process pending exam papers using OpenAI',
)]
class ProcessExamPapersCommand extends Command
{
    public function __construct(
        private ExamPaperProcessorService $processorService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Processing Pending Exam Papers');

        try {
            $results = $this->processorService->processPendingPapers();

            if (empty($results)) {
                $io->success('No pending papers found');
                return Command::SUCCESS;
            }

            $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
            $errorCount = count(array_filter($results, fn($r) => $r['status'] === 'error'));

            $io->section('Processing Results');
            $io->table(
                ['ID', 'Status', 'Message'],
                array_map(fn($r) => [$r['id'], $r['status'], $r['message']], $results)
            );

            $io->success(sprintf(
                'Processing complete. %d papers processed successfully, %d failed.',
                $successCount,
                $errorCount
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}