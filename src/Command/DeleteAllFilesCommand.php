<?php

namespace App\Command;

use App\Service\OpenAIService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:delete-all-files',
    description: 'Delete all files from OpenAI',
)]
class DeleteAllFilesCommand extends Command
{
    public function __construct(
        private readonly OpenAIService $openAIService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->info('Starting to delete all files from OpenAI...');

            $responses = $this->openAIService->deleteAllFiles();

            $io->success(sprintf('Successfully deleted %d files from OpenAI.', count($responses)));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to delete files: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}