<?php

namespace App\Command;

use App\Service\QuestionTopicService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-question-topics',
    description: 'Generate topics for questions that don\'t have one using Deepseek API'
)]
class GenerateQuestionTopicsCommand extends Command
{
    public function __construct(
        private QuestionTopicService $questionTopicService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Starting topic generation for questions...');

        try {
            $this->questionTopicService->generateTopicsForNullQuestions();
            $io->success('Topic generation completed successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred while generating topics: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}