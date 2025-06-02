<?php

namespace App\Command;

use App\Service\SubscriptionStatusService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckSubscriptionStatusCommand extends Command
{
    protected static $defaultName = 'app:check-subscriptions';
    protected static $defaultDescription = 'Checks and updates learner subscription statuses';

    public function __construct(
        private readonly SubscriptionStatusService $subscriptionStatusService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Checking Subscription Statuses');

        $results = $this->subscriptionStatusService->checkAndUpdateSubscriptions();

        $io->success(sprintf(
            'Processed %d learners. Updated %d to free. Deleted %d old subscriptions. Encountered %d errors.',
            $results['checked'],
            $results['updated'],
            $results['deleted'],
            $results['errors']
        ));

        return Command::SUCCESS;
    }
}