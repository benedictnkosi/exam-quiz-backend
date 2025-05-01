<?php

namespace App\Command;

use App\Service\QuestionTopicService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;

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

    protected function configure(): void
    {
        $this
            ->addArgument('grade', InputArgument::REQUIRED, 'The grade to generate topics for')
            ->setDescription('Generate topics for questions that don\'t have one using Deepseek API');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $grade = (int) $input->getArgument('grade');

        $io->info(sprintf('Starting topic generation for questions in grade %d...', $grade));

        try {
            $this->questionTopicService->generateTopicsForNullQuestions($grade);
            $io->success(sprintf('Topic generation completed successfully for grade %d', $grade));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred while generating topics: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}