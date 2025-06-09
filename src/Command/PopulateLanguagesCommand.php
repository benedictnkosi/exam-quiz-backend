<?php

namespace App\Command;

use App\Service\LanguagePopulationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-languages',
    description: 'Populate languages table with South African languages',
)]
class PopulateLanguagesCommand extends Command
{
    private LanguagePopulationService $languagePopulationService;

    public function __construct(LanguagePopulationService $languagePopulationService)
    {
        parent::__construct();
        $this->languagePopulationService = $languagePopulationService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->info('Starting to populate languages...');
            $this->languagePopulationService->populateLanguages();
            $io->success('Languages have been populated successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred while populating languages: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}