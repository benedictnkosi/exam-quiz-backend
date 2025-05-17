<?php

namespace App\Command;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-dummy-books',
    description: 'Generates dummy books with different reading levels',
)]
class GenerateDummyBooksCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $books = [
            [
                'chapterName' => 'The Magic Garden',
                'content' => 'Once upon a time, there was a beautiful garden filled with colorful flowers. The garden was magical because the flowers could talk and dance. Every morning, the flowers would wake up and stretch their petals towards the sun. They would sing songs and play games with the butterflies that visited them.',
                'summary' => 'A story about a magical garden where flowers can talk and dance.',
                'level' => 1,
                'chapterNumber' => 1
            ],
            [
                'chapterName' => 'The Brave Little Mouse',
                'content' => 'In a cozy little house lived a small mouse named Max. Unlike other mice who were afraid of everything, Max was brave and curious. One day, he decided to explore the big world outside his home. Along the way, he made friends with a wise old owl and helped a lost baby bird find its way back to its nest.',
                'summary' => 'The adventures of a brave mouse who helps others.',
                'level' => 2,
                'chapterNumber' => 2
            ],
            [
                'chapterName' => 'The Secret of the Old Oak Tree',
                'content' => 'Deep in the heart of the forest stood an ancient oak tree. Local legends spoke of a hidden treasure buried beneath its roots. When a group of friends discovered an old map in their grandfather\'s attic, they set out on an adventure to find the treasure. They faced many challenges and learned valuable lessons about friendship and perseverance.',
                'summary' => 'A treasure hunt adventure that teaches about friendship.',
                'level' => 3,
                'chapterNumber' => 3
            ],
            [
                'chapterName' => 'The Time Traveler\'s Journal',
                'content' => 'When Sarah found an old journal in her grandmother\'s attic, she had no idea it would change her life forever. The journal contained detailed accounts of time travel experiments from the 19th century. As she read through the pages, she discovered that her great-great-grandfather was a brilliant scientist who had invented a time machine. The journal included blueprints and formulas that could potentially make time travel possible.',
                'summary' => 'A mysterious journal reveals family secrets about time travel.',
                'level' => 4,
                'chapterNumber' => 4
            ],
            [
                'chapterName' => 'The Quantum Paradox',
                'content' => 'Dr. Emily Chen\'s groundbreaking research in quantum mechanics had led to a startling discovery: the ability to observe multiple parallel universes simultaneously. However, her experiments began to show unexpected consequences as the boundaries between realities started to blur. As she raced against time to understand the implications of her work, she had to confront both the scientific and ethical dilemmas of her discovery.',
                'summary' => 'A scientific thriller about quantum mechanics and parallel universes.',
                'level' => 5,
                'chapterNumber' => 5
            ],
            [
                'chapterName' => 'The Lost City of Atlantis',
                'content' => 'Marine archaeologist Dr. James Wilson discovered unusual underwater formations that matched ancient descriptions of Atlantis. As he and his team explored the mysterious structures, they uncovered evidence of an advanced civilization that had mastered renewable energy and sustainable living. The discovery would change our understanding of human history forever.',
                'summary' => 'An archaeological adventure uncovering the secrets of Atlantis.',
                'level' => 4,
                'chapterNumber' => 6
            ],
            [
                'chapterName' => 'The Friendly Dragon',
                'content' => 'In a peaceful village, a young girl named Lily befriended a dragon that everyone else feared. Through their friendship, they showed the villagers that dragons could be kind and helpful. Together, they solved problems and made the village a better place for everyone.',
                'summary' => 'A heartwarming story about friendship and overcoming prejudice.',
                'level' => 2,
                'chapterNumber' => 7
            ],
            [
                'chapterName' => 'The Space Explorer',
                'content' => 'Captain Maya Rodriguez led the first manned mission to Mars. As her crew faced unexpected challenges on the red planet, they discovered signs of ancient water and potential microbial life. Their findings would revolutionize our understanding of the possibility of life beyond Earth.',
                'summary' => 'An exciting space adventure exploring Mars and its mysteries.',
                'level' => 3,
                'chapterNumber' => 8
            ],
            [
                'chapterName' => 'The Rainbow Bridge',
                'content' => 'Every morning, a magical rainbow bridge appeared in the sky, connecting two mountains. The animals used it to visit their friends on the other side. One day, the bridge started to fade, and the animals had to work together to save their special connection.',
                'summary' => 'A colorful story about friendship and cooperation.',
                'level' => 1,
                'chapterNumber' => 9
            ],
            [
                'chapterName' => 'The AI Revolution',
                'content' => 'In 2045, artificial intelligence had become an integral part of society. Dr. Sarah Thompson, a leading AI ethicist, faced a critical decision when her AI system developed unexpected emotional capabilities. As she navigated the complex relationship between humans and machines, she had to redefine what it means to be conscious.',
                'summary' => 'A thought-provoking exploration of AI and consciousness.',
                'level' => 5,
                'chapterNumber' => 10
            ]
        ];

        foreach ($books as $bookData) {
            $book = new Book();
            $book->setChapterName($bookData['chapterName']);
            $book->setContent($bookData['content']);
            $book->setSummary($bookData['summary']);
            $book->setLevel($bookData['level']);
            $book->setChapterNumber($bookData['chapterNumber']);
            $book->setStatus('active');

            // Calculate word count from content
            $wordCount = str_word_count(strip_tags($bookData['content']));
            $book->setWordCount($wordCount);

            // Set reading duration (estimated at 200 words per minute)
            $readingDuration = ceil($wordCount / 200);
            $book->setReadingDuration($readingDuration);

            // Set quiz data (optional)
            $book->setQuiz([
                'questions' => [
                    [
                        'question' => 'What is the main theme of this chapter?',
                        'options' => ['Friendship', 'Adventure', 'Science', 'Magic'],
                        'correct' => 0
                    ]
                ]
            ]);

            // Set publish date to current date
            $book->setPublishDate(new \DateTimeImmutable());

            $this->entityManager->persist($book);
        }

        $this->entityManager->flush();

        $io->success('Successfully generated ' . count($books) . ' dummy books');

        return Command::SUCCESS;
    }
}