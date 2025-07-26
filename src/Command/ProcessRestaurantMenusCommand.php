<?php

namespace App\Command;

use App\Entity\RestaurantMenu;
use App\Repository\RestaurantMenuRepository;
use App\Service\OpenAIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:process-restaurant-menus',
    description: 'Process pending restaurant menu requests by request_id',
)]
class ProcessRestaurantMenusCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RestaurantMenuRepository $menuRepository,
        private readonly OpenAIService $openAIService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('request-ids', InputArgument::REQUIRED, 'Comma-separated list of request IDs to process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $requestIds = explode(',', $input->getArgument('request-ids'));
        $menus = $this->menuRepository->findByRequestIdsAndStatus($requestIds, ['pending', 'processing']);
        $io->title('Processing Restaurant Menus');
        if (empty($menus)) {
            $io->success('No pending menus to process.');
            return Command::SUCCESS;
        }
        foreach ($menus as $menu) {
            $io->section('Processing: ' . $menu->getRestaurantName() . ' / ' . $menu->getFoodType());
            $menu->setStatus('processing');
            $menu->setUpdatedAt(new \DateTime());
            $this->entityManager->persist($menu);
            $this->entityManager->flush();
            try {
                $result = $this->openAIService->generateMenuByTypeAndRestaurant($menu->getRestaurantName(), $menu->getFoodType());
                $menu->setMenuResult($result);
                $menu->setStatus('ready');
                $menu->setErrorMessage(null);
                $menu->setUpdatedAt(new \DateTime());
                $io->success('Menu generated and saved.');
            } catch (\Exception $e) {
                $menu->setStatus('error');
                $menu->setErrorMessage($e->getMessage());
                $menu->setUpdatedAt(new \DateTime());
                $this->logger->error('Error processing menu', [
                    'restaurant' => $menu->getRestaurantName(),
                    'food_type' => $menu->getFoodType(),
                    'error' => $e->getMessage(),
                ]);
                $io->error('Error: ' . $e->getMessage());
            }
            $this->entityManager->persist($menu);
            $this->entityManager->flush();
        }
        $io->success('Processing complete.');
        return Command::SUCCESS;
    }
} 