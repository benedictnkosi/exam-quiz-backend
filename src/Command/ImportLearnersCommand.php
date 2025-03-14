<?php

namespace App\Command;

use App\Service\LearnerImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-learners',
    description: 'Import learners from a JSON file',
)]
class ImportLearnersCommand extends Command
{
    private LearnerImportService $learnerImportService;

    public function __construct(LearnerImportService $learnerImportService)
    {
        parent::__construct();
        $this->learnerImportService = $learnerImportService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the JSON file containing learner data')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $io->error(sprintf('File not found: %s', $filePath));
            return Command::FAILURE;
        }

        $io->title('Importing learners from JSON file');
        $io->text(sprintf('Reading file: %s', $filePath));

        try {
            $jsonContent = file_get_contents($filePath);
            $result = $this->learnerImportService->importFromJson($jsonContent);

            if (empty($result['errors'])) {
                $io->success(sprintf('Successfully imported %d learners', $result['success']));
                return Command::SUCCESS;
            } else {
                $io->warning(sprintf('Imported %d learners with %d errors', $result['success'], count($result['errors'])));
                foreach ($result['errors'] as $error) {
                    $io->error($error);
                }
                return $result['success'] > 0 ? Command::SUCCESS : Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error(sprintf('Error importing learners: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}