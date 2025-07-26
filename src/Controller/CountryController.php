<?php

namespace App\Controller;

use App\Service\ShadyMeterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/countries')]
class CountryController extends AbstractController
{
    public function __construct(
        private readonly ShadyMeterService $shadyMeterService
    ) {
    }

    /**
     * GET - Get Country Statistics
     * 
     * Retrieves corruption statistics for countries, including scandal counts, active cases, and politician counts.
     */
    #[Route('', name: 'get_country_statistics', methods: ['GET'])]
    public function getCountryStatistics(Request $request): JsonResponse
    {
        try {
            $statistics = $this->shadyMeterService->getCountryStatistics($request);
            return $this->json($statistics);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve country statistics',
                'details' => $e->getMessage()
            ], 500);
        }
    }
} 