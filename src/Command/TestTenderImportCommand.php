<?php

namespace App\Command;

use App\Service\TenderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-tender-import',
    description: 'Test import of a small number of tenders for debugging',
)]
class TestTenderImportCommand extends Command
{
    public function __construct(
        private TenderService $tenderService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_REQUIRED,
                'External API URL to fetch tender data from',
                'https://www.etenders.gov.za/Home/PaginatedTenderOpportunities?draw=3&columns%5B0%5D%5Bdata%5D=&columns%5B0%5D%5Bname%5D=&columns%5B0%5D%5Bsearchable%5D=true&columns%5B0%5D%5Borderable%5D=false&columns%5B0%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B0%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B1%5D%5Bdata%5D=category&columns%5B1%5D%5Bname%5D=&columns%5B1%5D%5Bsearchable%5D=true&columns%5B1%5D%5Borderable%5D=true&columns%5B1%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B1%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B2%5D%5Bdata%5D=description&columns%5B2%5D%5Bname%5D=&columns%5B2%5D%5Bsearchable%5D=true&columns%5B2%5D%5Borderable%5D=false&columns%5B2%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B2%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B3%5D%5Bdata%5D=eSubmission&columns%5B3%5D%5Bname%5D=&columns%5B3%5D%5Bsearchable%5D=true&columns%5B3%5D%5Borderable%5D=true&columns%5B3%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B3%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B4%5D%5Bdata%5D=date_Published&columns%5B4%5D%5Bname%5D=&columns%5B4%5D%5Bsearchable%5D=true&columns%5B4%5D%5Borderable%5D=true&columns%5B4%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B4%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B5%5D%5Bdata%5D=awardDate&columns%5B5%5D%5Bname%5D=&columns%5B5%5D%5Bsearchable%5D=true&columns%5B5%5D%5Borderable%5D=true&columns%5B5%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B5%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B6%5D%5Bdata%5D=actions&columns%5B6%5D%5Bname%5D=&columns%5B6%5D%5Bsearchable%5D=true&columns%5B6%5D%5Borderable%5D=true&columns%5B6%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B6%5D%5Bsearch%5D%5Bregex%5D=false&order%5B0%5D%5Bcolumn%5D=2&order%5B0%5D%5Bdir%5D=desc&start=10&length=10&search%5Bvalue%5D=&search%5Bregex%5D=false&status=2&_=1753713431584'
            )
            ->addOption(
                'count',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Number of records to test import (default: 5)',
                5
            )
            ->setHelp('This command tests importing a small number of tenders for debugging purposes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apiUrl = $input->getOption('url');
        $count = (int) $input->getOption('count');

        $io->title('Test Tender Import');
        $io->text("Testing import of {$count} records from: {$apiUrl}");

        try {
            // Get initial count
            $initialCount = $this->tenderService->getTotalTenderCount();
            $io->info("Initial database count: {$initialCount}");

            // Import test records
            $results = $this->tenderService->fetchAndSaveTenderData($apiUrl, 0, $count, 1);

            // Get final count
            $finalCount = $this->tenderService->getTotalTenderCount();
            $actualAdded = $finalCount - $initialCount;

            $io->success([
                'Test import completed!',
                "Initial count: {$initialCount}",
                "Final count: {$finalCount}",
                "Actually added: {$actualAdded}",
                "Reported successful: {$results['successful']}",
                "Reported skipped: {$results['skipped']}",
                "Total processed: {$results['total_processed']}"
            ]);

            if (!empty($results['errors'])) {
                $io->warning('Errors occurred during test import:');
                foreach ($results['errors'] as $error) {
                    $io->text("• {$error}");
                }
            }

            if ($actualAdded !== $results['successful']) {
                $io->error([
                    'DISCREPANCY DETECTED!',
                    "Expected to add: {$results['successful']}",
                    "Actually added: {$actualAdded}"
                ]);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Test import failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
} 