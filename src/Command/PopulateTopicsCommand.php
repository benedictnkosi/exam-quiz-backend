<?php

namespace App\Command;

use App\Service\TopicPopulationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-topics',
    description: 'Populate topics table from questions and subjects',
)]
class PopulateTopicsCommand extends Command
{
    private TopicPopulationService $topicPopulationService;

    public function __construct(TopicPopulationService $topicPopulationService)
    {
        parent::__construct();
        $this->topicPopulationService = $topicPopulationService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->info('Starting to populate topics...');
            $this->topicPopulationService->populateTopics();
            $io->success('Topics have been populated successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred while populating topics: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}