<?php

namespace App\Command;

use App\Service\BadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assign-badge-points',
    description: 'Assign points to users based on their badges'
)]
class AssignBadgePointsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BadgeService $badgeService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userRepository = $this->entityManager->getRepository('App\Entity\User');
        $users = $userRepository->findAll();

        $processedCount = 0;
        foreach ($users as $user) {
            $this->badgeService->assignPointsForBadges($user);
            $processedCount++;
        }

        $io->success(sprintf('Successfully processed %d users', $processedCount));

        return Command::SUCCESS;
    }
}