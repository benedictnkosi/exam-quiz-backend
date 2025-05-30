<?php

namespace App\Command;

use App\Service\ThreadService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-thread-from-book',
    description: 'Creates a thread from today\'s book chapter',
)]
class CreateThreadFromBookCommand extends Command
{
    public function __construct(
        private ThreadService $threadService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $book = $this->threadService->getBookForToday();

        if (!$book) {
            $io->error('No book found for today with chat thread content');
            return Command::FAILURE;
        }

        try {
            $result = $this->threadService->createThreadFromBook(
                $book,
                'u65pX1a9KCbshI5VuprMcgVVfQl2', // This should be replaced with actual user UID
                'Siya The AI'
            );

            $io->success('Thread created successfully');
            $io->table(
                ['Thread Name', 'Message Body'],
                [[$book->getChatThreadTitle(), $book->getChatThreadContent()]]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to create thread: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}