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
    name: 'app:import-tender-data',
    description: 'Import tender data from external API',
)]
class ImportTenderDataCommand extends Command
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
                'start',
                's',
                InputOption::VALUE_OPTIONAL,
                'Starting record number (default: 0)',
                0
            )
            ->addOption(
                'length',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Number of records to fetch (default: 10, max: 100)',
                10
            )
            ->addOption(
                'draw',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Draw parameter for DataTables (default: 1)',
                1
            )
            ->addOption(
                'batch',
                'b',
                InputOption::VALUE_NONE,
                'Enable batch processing mode'
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of records per batch (default: 50)',
                50
            )
            ->addOption(
                'max-records',
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum number of records to import (optional)'
            )
            ->addOption(
                'start-from',
                null,
                InputOption::VALUE_OPTIONAL,
                'Starting record number for batch processing (default: 0)',
                0
            )
            ->setHelp('This command imports tender data from the external etenders.gov.za API. Use --batch for processing large datasets.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apiUrl = $input->getOption('url');
        
        $io->title('Tender Data Import');
        
        // Check if batch mode is enabled
        $batchMode = $input->getOption('batch');
        
        if ($batchMode) {
            $batchSize = (int) $input->getOption('batch-size');
            $maxRecords = $input->getOption('max-records');
            $startFrom = (int) $input->getOption('start-from');
            
            // Validate batch parameters
            if ($batchSize < 1 || $batchSize > 100) {
                $io->error('Batch size must be between 1 and 100');
                return Command::FAILURE;
            }
            
            if ($startFrom < 0) {
                $io->error('Start from parameter must be non-negative');
                return Command::FAILURE;
            }
            
            if ($maxRecords !== null && (int) $maxRecords < 1) {
                $io->error('Max records parameter must be positive');
                return Command::FAILURE;
            }
            
            $io->text("Batch import mode enabled");
            $io->text("Importing tender data from: {$apiUrl}");
            $io->text("Batch parameters: size={$batchSize}, startFrom={$startFrom}, maxRecords=" . ($maxRecords ?? 'unlimited'));
            
            try {
                $io->progressStart();
                
                $results = $this->tenderService->batchImportTenderData($apiUrl, $batchSize, $maxRecords ? (int) $maxRecords : null, $startFrom);
                
                $io->progressFinish();
                
                $io->success([
                    'Batch import completed successfully!',
                    "Total processed: {$results['total_processed']}",
                    "Successful: {$results['successful']}",
                    "Skipped: {$results['skipped']}",
                    "Batches processed: {$results['batches_processed']}"
                ]);

                if (!empty($results['errors'])) {
                    $io->warning('Some errors occurred during import:');
                    foreach (array_slice($results['errors'], 0, 10) as $error) {
                        $io->text("• {$error}");
                    }
                    
                    if (count($results['errors']) > 10) {
                        $io->text('... and ' . (count($results['errors']) - 10) . ' more errors');
                    }
                }

                return Command::SUCCESS;
                
            } catch (\Exception $e) {
                $io->progressFinish();
                $io->error("Batch import failed: {$e->getMessage()}");
                return Command::FAILURE;
            }
        } else {
            // Single batch mode
            $start = (int) $input->getOption('start');
            $length = (int) $input->getOption('length');
            $draw = (int) $input->getOption('draw');

            // Validate parameters
            if ($start < 0) {
                $io->error('Start parameter must be non-negative');
                return Command::FAILURE;
            }

            if ($length < 1 || $length > 100) {
                $io->error('Length parameter must be between 1 and 100');
                return Command::FAILURE;
            }

            if ($draw < 1) {
                $io->error('Draw parameter must be positive');
                return Command::FAILURE;
            }

            $io->text("Single batch mode");
            $io->text("Importing tender data from: {$apiUrl}");
            $io->text("Parameters: start={$start}, length={$length}, draw={$draw}");

            try {
                $io->progressStart();
                
                $results = $this->tenderService->fetchAndSaveTenderData($apiUrl, $start, $length, $draw);
                
                $io->progressFinish();
                
                $io->success([
                    'Import completed successfully!',
                    "Total processed: {$results['total_processed']}",
                    "Successful: {$results['successful']}",
                    "Skipped: {$results['skipped']}"
                ]);

                if (!empty($results['errors'])) {
                    $io->warning('Some errors occurred during import:');
                    foreach (array_slice($results['errors'], 0, 10) as $error) {
                        $io->text("• {$error}");
                    }
                    
                    if (count($results['errors']) > 10) {
                        $io->text('... and ' . (count($results['errors']) - 10) . ' more errors');
                    }
                }

                return Command::SUCCESS;
                
            } catch (\Exception $e) {
                $io->progressFinish();
                $io->error("Import failed: {$e->getMessage()}");
                return Command::FAILURE;
            }
        }
    }
} 