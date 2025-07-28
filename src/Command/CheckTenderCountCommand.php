<?php

namespace App\Command;

use App\Service\TenderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-tender-count',
    description: 'Check the actual count of tenders in the database',
)]
class CheckTenderCountCommand extends Command
{
    public function __construct(
        private TenderService $tenderService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Tender Database Count Check');

        try {
            $totalCount = $this->tenderService->getTotalTenderCount();
            
            $io->success([
                "Total tenders in database: {$totalCount}"
            ]);

            // Check count from last 24 hours
            $yesterday = new \DateTime('-24 hours');
            $recentCount = $this->tenderService->getTenderCountAfterDate($yesterday);
            
            $io->info([
                "Tenders created in last 24 hours: {$recentCount}"
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Error checking tender count: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
} 