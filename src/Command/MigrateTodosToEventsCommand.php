<?php

namespace App\Command;

use App\Service\TodoMigrationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-todos-to-events',
    description: 'Migrate future dated todos to learner events',
)]
class MigrateTodosToEventsCommand extends Command
{
    public function __construct(
        private TodoMigrationService $todoMigrationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migrating Todos to Events');

        $result = $this->todoMigrationService->migrateAllTodosToEvents();

        if ($result['status'] === 'OK') {
            $io->success([
                "Migration completed successfully",
                "Total events migrated: {$result['migrated_count']}",
                "Affected learners: {$result['affected_learners']}"
            ]);
            return Command::SUCCESS;
        } else {
            $io->error($result['message']);
            return Command::FAILURE;
        }
    }
}