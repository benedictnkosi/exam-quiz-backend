<?php

namespace App\Command;

use App\Service\SubjectCapturerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:reset-inactive-capturers',
    description: 'Reset subject capturers to NULL if no questions have been created in the past 48 hours'
)]
class ResetInactiveCapturersCommand extends Command
{
    public function __construct(
        private SubjectCapturerService $subjectCapturerService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting to check for inactive capturers...');

        $result = $this->subjectCapturerService->resetInactiveCapturers();

        if ($result['status'] === 'OK') {
            $output->writeln($result['message']);
            return Command::SUCCESS;
        } else {
            $output->writeln('<error>' . $result['message'] . '</error>');
            return Command::FAILURE;
        }
    }
}