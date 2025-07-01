<?php

namespace App\Command;

use App\Entity\ReadGenres;
use App\Repository\ReadGenresRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-read-genres',
    description: 'Populate read genres with predefined data',
)]
class PopulateReadGenresCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReadGenresRepository $readGenresRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Populating Read Genres');

        $genresData = [
            "Adventure" => [
                "Treasure Hunt",
                "Lost in the Wild",
                "Escape Mission",
                "Quest or Journey",
                "Underground Worlds"
            ],
            "Mystery" => [
                "Detective",
                "Missing Person",
                "Locked Room",
                "Crime Solving",
                "Secret Societies"
            ],
            "Fantasy" => [
                "Magic School",
                "Dragons & Creatures",
                "Warring Kingdoms",
                "Prophecies",
                "Urban Fantasy"
            ],
            "Sci-Fi" => [
                "Space Travel",
                "Robots & AI",
                "Time Travel",
                "Virtual Reality",
                "Post-Apocalyptic"
            ],
            "School Life" => [
                "New Kid",
                "Teacher Trouble",
                "Test Stress",
                "Friend Drama",
                "Classroom Rivalries"
            ],
            "Comedy" => [
                "Pranks & Mischief",
                "Awkward Moments",
                "Wacky Families",
                "School Blunders",
                "Bizarre Adventures"
            ],
            "Sports" => [
                "Soccer",
                "Basketball",
                "Track & Field",
                "Martial Arts",
                "eSports & Gaming"
            ],
            "Animal Tales" => [
                "Talking Animals",
                "Pet Rescue",
                "Wildlife Survival",
                "Farm Life",
                "Zoo Escapades"
            ],
            "Superheroes" => [
                "Secret Identity",
                "Origin Story",
                "Team Missions",
                "Villain Showdowns",
                "Power Struggles"
            ],
            "Friendship" => [
                "New Besties",
                "Falling Out",
                "Unlikely Duos",
                "Group Challenges",
                "Reunions"
            ],
            "Family" => [
                "Sibling Rivalry",
                "Single Parent Life",
                "Extended Family Visits",
                "Family Road Trips",
                "Lost & Found"
            ],
            "Survival" => [
                "Natural Disaster",
                "Stranded Island",
                "Escape from Danger",
                "Wilderness Trek",
                "Outsmarting the Wild"
            ],
            "Dreams & Imagination" => [
                "Dream Worlds",
                "Imaginary Friends",
                "Mind Travel",
                "Magic Items",
                "Portal Stories"
            ],
            "Local Legends" => [
                "Myth Retold",
                "Urban Legend",
                "Ancient Curse",
                "Ancestral Spirits",
                "Village Secrets"
            ],
            "Time Travel" => [
                "Future Shock",
                "Back in Time",
                "Time Loop",
                "Historical Adventure",
                "Time Machine Gone Wrong"
            ],
            "Spooky" => [
                "Haunted House",
                "Friendly Ghost",
                "Cursed Object",
                "Night Creature",
                "Scary-but-Funny"
            ],
            "Slice of Life" => [
                "Daily Routine",
                "Quiet Moments",
                "First Job",
                "Birthday Drama",
                "Small Wins"
            ],
            "Community" => [
                "Neighborhood Mystery",
                "Local Event",
                "Helping Hands",
                "Block Party",
                "Lost Pet Search"
            ],
            "Invention & Discovery" => [
                "Young Inventors",
                "Science Fair",
                "Secret Lab",
                "Tech Gone Wrong",
                "Big Ideas"
            ],
            "Self-Discovery" => [
                "New Hobby",
                "Learning Confidence",
                "Trying Something New",
                "Mistakes & Growth",
                "Finding Your Voice"
            ]
        ];

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($genresData as $genreName => $subcategories) {
            $existingGenre = $this->readGenresRepository->findByGenreName($genreName);

            if ($existingGenre) {
                $existingGenre->setSubcategories($subcategories);
                $existingGenre->setUpdatedAt(new \DateTime());
                $this->entityManager->persist($existingGenre);
                $updatedCount++;
                $io->text("Updated genre: <info>{$genreName}</info>");
            } else {
                $genre = new ReadGenres();
                $genre->setGenreName($genreName);
                $genre->setSubcategories($subcategories);
                $this->entityManager->persist($genre);
                $createdCount++;
                $io->text("Created genre: <info>{$genreName}</info>");
            }
        }

        $this->entityManager->flush();

        $io->success([
            "Read genres populated successfully!",
            "Created: {$createdCount} genres",
            "Updated: {$updatedCount} genres",
            "Total: " . count($genresData) . " genres"
        ]);

        return Command::SUCCESS;
    }
} 