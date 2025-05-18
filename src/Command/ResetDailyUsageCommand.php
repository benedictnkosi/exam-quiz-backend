<?php

namespace App\Command;

use App\Entity\LearnerDailyUsage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ResetDailyUsageCommand extends Command
{
    protected static $defaultName = 'app:reset-daily-usage';
    protected static $defaultDescription = 'Deletes all rows from the learner_daily_usage table';

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->setHelp('This command allows you to delete all rows from the learner_daily_usage table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->delete(LearnerDailyUsage::class, 'u');
            $query = $qb->getQuery();
            $deleted = $query->execute();

            $io->success(sprintf('Successfully deleted %d rows from learner_daily_usage table', $deleted));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error deleting rows: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}