<?php

namespace App\Command;

use App\Service\PodcastFileCheckService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-podcast-files',
    description: 'Check if podcast files exist for topics',
)]
class CheckPodcastFilesCommand extends Command
{
    public function __construct(
        private readonly PodcastFileCheckService $podcastFileCheckService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Checking Podcast Files');

        try {
            $results = $this->podcastFileCheckService->checkTopicRecordingFiles();

            $io->section('Summary');
            $io->table(
                ['Metric', 'Count'],
                [
                    ['Total Topics', $results['total']],
                    ['Files Found', count($results['found'])],
                    ['Files Missing', count($results['missing'])]
                ]
            );

            if (!empty($results['missing'])) {
                $io->section('Missing Files');
                $io->table(
                    ['Topic ID', 'Name', 'Sub Topic', 'Recording File'],
                    array_map(function ($item) {
                        return [
                            $item['topic_id'],
                            $item['name'],
                            $item['sub_topic'],
                            $item['recording_file']
                        ];
                    }, $results['missing'])
                );
            }

            if (!empty($results['found'])) {
                $io->section('Found Files');
                $io->table(
                    ['Topic ID', 'Name', 'Sub Topic', 'Recording File'],
                    array_map(function ($item) {
                        return [
                            $item['topic_id'],
                            $item['name'],
                            $item['sub_topic'],
                            $item['recording_file']
                        ];
                    }, $results['found'])
                );
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}