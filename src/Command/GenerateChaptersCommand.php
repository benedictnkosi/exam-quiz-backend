<?php

namespace App\Command;

use App\Service\ChapterGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-chapters',
    description: 'Generate chapters for new story arcs',
)]
class GenerateChaptersCommand extends Command
{
    public function __construct(
        private readonly ChapterGeneratorService $chapterGenerator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generating chapters for new story arcs');

        try {
            $generatedChapters = $this->chapterGenerator->generateChaptersForNewArcs();

            if (empty($generatedChapters)) {
                $io->success('No new story arcs found to process.');
                return Command::SUCCESS;
            }

            $io->success(sprintf(
                'Successfully generated %d chapters for %d story arcs.',
                count($generatedChapters),
                count(array_unique(array_column($generatedChapters, 'arc_id')))
            ));

            // Display summary of generated chapters
            $io->section('Generated Chapters Summary');
            foreach ($generatedChapters as $chapter) {
                $io->text(sprintf(
                    'Arc ID: %d, Reading Level: %d, Chapter: %s, Words: %d',
                    $chapter['arc_id'],
                    $chapter['reading_level'],
                    $chapter['chapter_name'],
                    $chapter['word_count']
                ));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred while generating chapters: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}