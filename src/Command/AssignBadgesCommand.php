<?php

namespace App\Command;

use App\Service\BadgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assign-badges',
    description: 'Assign badges to all learners based on their performance',
)]
class AssignBadgesCommand extends Command
{
    public function __construct(
        private readonly BadgeService $badgeService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting badge assignment process');

        try {
            $this->badgeService->checkAndAssignBadgesToAllLearners();
            $io->success('Badge assignment completed successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error during badge assignment: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}