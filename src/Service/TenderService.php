<?php

namespace App\Service;

use App\Entity\Tender;
use App\Repository\TenderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class TenderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenderRepository $tenderRepository,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {}

    /**
     * Create a new tender
     */
    public function createTender(array $data): Tender
    {
        // Check if tender number already exists
        if (isset($data['tenderNumber'])) {
            $existingTender = $this->getTenderByNumber($data['tenderNumber']);
            if ($existingTender) {
                throw new \InvalidArgumentException("Tender number '{$data['tenderNumber']}' already exists");
            }
        }

        $tender = new Tender();

        $this->populateTenderFromData($tender, $data);

        $this->entityManager->persist($tender);
        $this->entityManager->flush();

        return $tender;
    }

    /**
     * Update an existing tender
     */
    public function updateTender(Tender $tender, array $data): Tender
    {
        $this->populateTenderFromData($tender, $data);
        
        $tender->setUpdated(new \DateTime());
        $this->entityManager->flush();

        return $tender;
    }

    /**
     * Delete a tender
     */
    public function deleteTender(Tender $tender): void
    {
        $this->entityManager->remove($tender);
        $this->entityManager->flush();
    }

    /**
     * Get tenders with pagination and filtering
     */
    public function getTenders(int $page = 1, int $limit = 10, ?string $category = null, ?string $province = null, ?string $organOfState = null): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
           ->from(Tender::class, 't')
           ->setFirstResult($offset)
           ->setMaxResults($limit)
           ->orderBy('t.closingDate', 'ASC');

        // Apply filters
        if ($category) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $category);
        }

        if ($province) {
            $qb->andWhere('t.province = :province')
               ->setParameter('province', $province);
        }

        if ($organOfState) {
            $qb->andWhere('t.organOfState = :organOfState')
               ->setParameter('organOfState', $organOfState);
        }

        $query = $qb->getQuery();
        $paginator = new Paginator($query);

        $tenders = [];
        foreach ($paginator as $tender) {
            $tenders[] = $tender->toArray();
        }

        return [
            'tenders' => $tenders,
            'total' => count($paginator),
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil(count($paginator) / $limit)
        ];
    }

    /**
     * Get tender by ID
     */
    public function getTenderById(int $id): ?Tender
    {
        return $this->tenderRepository->find($id);
    }

    /**
     * Get tender by tender number
     */
    public function getTenderByNumber(string $tenderNumber): ?Tender
    {
        return $this->tenderRepository->findByTenderNumber($tenderNumber);
    }

    /**
     * Check if tender number exists
     */
    public function tenderNumberExists(string $tenderNumber): bool
    {
        return $this->getTenderByNumber($tenderNumber) !== null;
    }

    /**
     * Get active tenders
     */
    public function getActiveTenders(): array
    {
        $tenders = $this->tenderRepository->findActiveTenders();
        return array_map(fn($tender) => $tender->toArray(), $tenders);
    }

    /**
     * Get awarded tenders
     */
    public function getAwardedTenders(): array
    {
        $tenders = $this->tenderRepository->findAwardedTenders();
        return array_map(fn($tender) => $tender->toArray(), $tenders);
    }

    /**
     * Get tenders by category
     */
    public function getTendersByCategory(string $category): array
    {
        $tenders = $this->tenderRepository->findByCategory($category);
        return array_map(fn($tender) => $tender->toArray(), $tenders);
    }

    /**
     * Get tenders by province
     */
    public function getTendersByProvince(string $province): array
    {
        $tenders = $this->tenderRepository->findByProvince($province);
        return array_map(fn($tender) => $tender->toArray(), $tenders);
    }

    /**
     * Get tenders by organ of state
     */
    public function getTendersByOrganOfState(string $organOfState): array
    {
        $tenders = $this->tenderRepository->findByOrganOfState($organOfState);
        return array_map(fn($tender) => $tender->toArray(), $tenders);
    }

    /**
     * Get tenders closing before a specific date
     */
    public function getTendersClosingBefore(\DateTimeInterface $date): array
    {
        $tenders = $this->tenderRepository->findClosingBefore($date);
        return array_map(fn($tender) => $tender->toArray(), $tenders);
    }

    /**
     * Get tenders closing after a specific date
     */
    public function getTendersClosingAfter(\DateTimeInterface $date): array
    {
        $tenders = $this->tenderRepository->findClosingAfter($date);
        return array_map(fn($tender) => $tender->toArray(), $tenders);
    }

    /**
     * Add successful bidder to tender
     */
    public function addSuccessfulBidder(Tender $tender, string $bidder): Tender
    {
        $tender->addSuccessfulBidder($bidder);
        $this->entityManager->flush();
        return $tender;
    }

    /**
     * Remove successful bidder from tender
     */
    public function removeSuccessfulBidder(Tender $tender, string $bidder): Tender
    {
        $tender->removeSuccessfulBidder($bidder);
        $this->entityManager->flush();
        return $tender;
    }

    /**
     * Populate tender entity from data array
     */
    private function populateTenderFromData(Tender $tender, array $data): void
    {
        if (isset($data['category'])) {
            $tender->setCategory($data['category']);
        }

        if (isset($data['tenderDescription'])) {
            $tender->setTenderDescription($data['tenderDescription']);
        }

        if (isset($data['advertisedAt'])) {
            $advertisedAt = $data['advertisedAt'] instanceof \DateTime 
                ? $data['advertisedAt'] 
                : new \DateTime($data['advertisedAt']);
            $tender->setAdvertisedAt($advertisedAt);
        }

        if (isset($data['awardedAt'])) {
            $awardedAt = $data['awardedAt'] instanceof \DateTime 
                ? $data['awardedAt'] 
                : new \DateTime($data['awardedAt']);
            $tender->setAwardedAt($awardedAt);
        }

        if (isset($data['tenderNumber'])) {
            $tender->setTenderNumber($data['tenderNumber']);
        }

        if (isset($data['organOfState'])) {
            $tender->setOrganOfState($data['organOfState']);
        }

        if (isset($data['province'])) {
            $tender->setProvince($data['province']);
        }

        if (isset($data['closingDate'])) {
            $closingDate = $data['closingDate'] instanceof \DateTime 
                ? $data['closingDate'] 
                : new \DateTime($data['closingDate']);
            $tender->setClosingDate($closingDate);
        }

        if (isset($data['placeWhereGoodsWorksOrServicesAreRequired'])) {
            $tender->setPlaceWhereGoodsWorksOrServicesAreRequired($data['placeWhereGoodsWorksOrServicesAreRequired']);
        }

        if (isset($data['contactPerson'])) {
            $tender->setContactPerson($data['contactPerson']);
        }

        if (isset($data['email'])) {
            $tender->setEmail($data['email']);
        }

        if (isset($data['telephoneNumber'])) {
            $tender->setTelephoneNumber($data['telephoneNumber']);
        }

        if (isset($data['successfulBidders']) && is_array($data['successfulBidders'])) {
            $tender->setSuccessfulBidders($data['successfulBidders']);
        }
    }

    /**
     * Validate tender data
     */
    public function validateTenderData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'category',
            'tenderDescription',
            'tenderNumber',
            'organOfState',
            'province',
            'closingDate',
            'placeWhereGoodsWorksOrServicesAreRequired',
            'contactPerson',
            'email',
            'telephoneNumber'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Field '{$field}' is required";
            }
        }

        // Validate email format - be more lenient
        if (isset($data['email']) && !empty($data['email'])) {
            // Basic email validation - just check for @ symbol
            if (strpos($data['email'], '@') === false) {
                $errors[] = "Invalid email format";
            }
        }

        // Validate date formats
        $dateFields = ['advertisedAt', 'awardedAt', 'closingDate'];
        foreach ($dateFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                try {
                    new \DateTime($data[$field]);
                } catch (\Exception $e) {
                    $errors[] = "Invalid date format for '{$field}'";
                }
            }
        }

        // Check if tender number already exists (for new tenders)
        if (isset($data['tenderNumber']) && !empty($data['tenderNumber'])) {
            $existingTender = $this->getTenderByNumber($data['tenderNumber']);
            if ($existingTender) {
                $errors[] = "Tender number '{$data['tenderNumber']}' already exists";
            }
        }

        return $errors;
    }

    /**
     * Fetch and save tender data from external API
     */
    public function fetchAndSaveTenderData(string $apiUrl, int $start = 0, int $length = 10, int $draw = 1): array
    {
        $this->logger->info('Starting to fetch tender data from external API', ['url' => $apiUrl]);
        
        try {
            // Build URL with parameters
            $parsedUrl = parse_url($apiUrl);
            $queryParams = [];
            
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
            }
            
            // Update or add pagination parameters
            $queryParams['start'] = $start;
            $queryParams['length'] = $length;
            $queryParams['draw'] = $draw;
            
            // Rebuild URL with updated parameters
            $finalUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'] . '?' . http_build_query($queryParams);
            
            $this->logger->info('Fetching data with parameters', [
                'original_url' => $apiUrl,
                'final_url' => $finalUrl,
                'start' => $start,
                'length' => $length,
                'draw' => $draw
            ]);
            
            // Fetch data from external API
            $response = $this->httpClient->request('GET', $finalUrl, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new \Exception("API returned status code: {$statusCode}");
            }

            $data = json_decode($response->getContent(), true);
            if (!$data || !isset($data['data']) || !is_array($data['data'])) {
                $this->logger->error('Invalid API response format', [
                    'response_content' => substr($response->getContent(), 0, 1000),
                    'decoded_data' => $data
                ]);
                throw new \Exception('Invalid response format from API');
            }

            $this->logger->info('Successfully fetched tender data', [
                'total_records' => count($data['data']),
                'records_total' => $data['recordsTotal'] ?? 'unknown',
                'start' => $start,
                'length' => $length,
                'draw' => $draw
            ]);

            $results = [
                'total_processed' => 0,
                'successful' => 0,
                'skipped' => 0,
                'errors' => []
            ];

            // Process each tender
            foreach ($data['data'] as $tenderData) {
                try {
                    $result = $this->processTenderData($tenderData);
                    $results['total_processed']++;
                    
                    if ($result['success']) {
                        $results['successful']++;
                    } else {
                        $results['skipped']++;
                        $results['errors'][] = $result['error'];
                    }
                } catch (\Exception $e) {
                    $results['total_processed']++;
                    $results['skipped']++;
                    $results['errors'][] = "Error processing tender: " . $e->getMessage();
                    $this->logger->error('Error processing tender data', [
                        'tender_data' => $tenderData,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->logger->info('Completed processing tender data', $results);
            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch tender data from external API', [
                'url' => $apiUrl,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process individual tender data from external API
     */
    private function processTenderData(array $tenderData): array
    {
        // Extract tender number - use tender_No from API or generate one
        $tenderNumber = $tenderData['tender_No'] ?? null;
        if (!$tenderNumber) {
            $this->logger->warning('Tender data missing tender number', ['tender_data' => $tenderData]);
            return [
                'success' => false,
                'error' => 'Missing tender number'
            ];
        }

        // Check if tender already exists
        try {
            $existingTender = $this->getTenderByNumber($tenderNumber);
            if ($existingTender) {
                $this->logger->info('Tender already exists, skipping', ['tender_number' => $tenderNumber]);
                return [
                    'success' => false,
                    'error' => "Tender number '{$tenderNumber}' already exists"
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('Error checking for existing tender, proceeding with import', [
                'tender_number' => $tenderNumber,
                'error' => $e->getMessage()
            ]);
            // Continue with import even if check fails
        }

        // Map API data to our entity structure
        $mappedData = $this->mapApiDataToTender($tenderData);
        
        $this->logger->debug('Mapped tender data', [
            'tender_number' => $tenderNumber,
            'mapped_data' => $mappedData
        ]);
        
        // Validate the mapped data
        $validationErrors = $this->validateTenderData($mappedData);
        if (!empty($validationErrors)) {
            $this->logger->warning('Tender validation failed', [
                'tender_number' => $tenderNumber,
                'errors' => $validationErrors
            ]);
            return [
                'success' => false,
                'error' => 'Validation failed: ' . implode(', ', $validationErrors)
            ];
        }

        // Create the tender
        try {
            $this->logger->debug('Creating tender', ['tender_number' => $tenderNumber]);
            
            $tender = $this->createTender($mappedData);
            
            $this->logger->info('Tender created successfully', [
                'tender_id' => $tender->getId(),
                'tender_number' => $tender->getTenderNumber()
            ]);
            
            // Process successful bidders if available
            if (isset($tenderData['awards']) && is_array($tenderData['awards'])) {
                $this->processSuccessfulBidders($tender, $tenderData['awards']);
            }

            return [
                'success' => true,
                'tender_id' => $tender->getId(),
                'tender_number' => $tender->getTenderNumber()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create tender', [
                'tender_number' => $tenderNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => 'Failed to create tender: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Map external API data to our tender entity structure
     */
    private function mapApiDataToTender(array $apiData): array
    {
        // Extract successful bidders from awards
        $successfulBidders = [];
        if (isset($apiData['awards']) && is_array($apiData['awards'])) {
            foreach ($apiData['awards'] as $award) {
                if (isset($award['company'])) {
                    $successfulBidders[] = $award['company'];
                }
            }
        }

        // Map the data
        $mappedData = [
            'category' => $apiData['category'] ?? 'Unknown',
            'tenderDescription' => $apiData['description'] ?? '',
            'tenderNumber' => $apiData['tender_No'] ?? '',
            'organOfState' => $apiData['organ_of_State'] ?? $apiData['department'] ?? 'Unknown',
            'province' => $apiData['province'] ?? 'Unknown',
            'placeWhereGoodsWorksOrServicesAreRequired' => $this->buildLocationString($apiData),
            'contactPerson' => $apiData['contactPerson'] ?? 'Unknown',
            'email' => $apiData['email'] ?? 'unknown@example.com',
            'telephoneNumber' => $apiData['telephone'] ?? 'Unknown',
            'successfulBidders' => $successfulBidders
        ];

        // Handle dates
        if (isset($apiData['date_Published']) && !empty($apiData['date_Published'])) {
            $mappedData['advertisedAt'] = $this->parseApiDate($apiData['date_Published']);
        }

        if (isset($apiData['awardDate']) && !empty($apiData['awardDate'])) {
            $mappedData['awardedAt'] = $this->parseApiDate($apiData['awardDate']);
        }

        if (isset($apiData['closing_Date']) && !empty($apiData['closing_Date'])) {
            $mappedData['closingDate'] = $this->parseApiDate($apiData['closing_Date']);
        }

        return $mappedData;
    }

    /**
     * Build location string from API data
     */
    private function buildLocationString(array $apiData): string
    {
        $locationParts = [];
        
        if (!empty($apiData['streetname'])) {
            $locationParts[] = $apiData['streetname'];
        }
        
        if (!empty($apiData['surburb'])) {
            $locationParts[] = $apiData['surburb'];
        }
        
        if (!empty($apiData['town'])) {
            $locationParts[] = $apiData['town'];
        }
        
        if (!empty($apiData['code'])) {
            $locationParts[] = $apiData['code'];
        }

        return !empty($locationParts) ? implode(', ', $locationParts) : 'Location not specified';
    }

    /**
     * Parse date from API format
     */
    private function parseApiDate(string $dateString): string
    {
        try {
            // Handle different date formats from API
            $date = new \DateTime($dateString);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse date', [
                'date_string' => $dateString,
                'error' => $e->getMessage()
            ]);
            // Return current date as fallback
            return (new \DateTime())->format('Y-m-d H:i:s');
        }
    }

    /**
     * Process successful bidders from awards data
     */
    private function processSuccessfulBidders(Tender $tender, array $awards): void
    {
        foreach ($awards as $award) {
            if (isset($award['company']) && !empty($award['company'])) {
                $tender->addSuccessfulBidder($award['company']);
            }
        }
        
        $this->entityManager->flush();
    }

    /**
     * Batch import tender data from external API
     */
    public function batchImportTenderData(string $apiUrl, int $batchSize = 50, int $maxRecords = null, int $startFrom = 0): array
    {
        $this->logger->info('Starting batch import of tender data', [
            'api_url' => $apiUrl,
            'batch_size' => $batchSize,
            'max_records' => $maxRecords,
            'start_from' => $startFrom
        ]);

        $totalProcessed = 0;
        $totalSuccessful = 0;
        $totalSkipped = 0;
        $allErrors = [];
        $currentStart = $startFrom;
        $draw = 1;

        try {
            while (true) {
                // Check if we've reached the maximum records limit
                if ($maxRecords !== null && $totalProcessed >= $maxRecords) {
                    $this->logger->info('Reached maximum records limit', ['max_records' => $maxRecords]);
                    break;
                }

                $this->logger->info('Processing batch', [
                    'batch_number' => $draw,
                    'start' => $currentStart,
                    'length' => $batchSize
                ]);

                // Import current batch
                $batchResults = $this->fetchAndSaveTenderData($apiUrl, $currentStart, $batchSize, $draw);

                // Update totals
                $totalProcessed += $batchResults['total_processed'];
                $totalSuccessful += $batchResults['successful'];
                $totalSkipped += $batchResults['skipped'];
                $allErrors = array_merge($allErrors, $batchResults['errors']);

                $this->logger->info('Batch completed', [
                    'batch_number' => $draw,
                    'batch_processed' => $batchResults['total_processed'],
                    'batch_successful' => $batchResults['successful'],
                    'batch_skipped' => $batchResults['skipped'],
                    'total_processed' => $totalProcessed,
                    'total_successful' => $totalSuccessful,
                    'total_skipped' => $totalSkipped
                ]);

                // If no records were processed in this batch, we've reached the end
                if ($batchResults['total_processed'] === 0) {
                    $this->logger->info('No more records to process');
                    break;
                }

                // Move to next batch
                $currentStart += $batchSize;
                $draw++;

                // Clear entity manager to free memory
                $this->entityManager->clear();
                
                // Add a small delay to avoid overwhelming the external API
                usleep(500000); // 0.5 second delay
            }

            $finalResults = [
                'total_processed' => $totalProcessed,
                'successful' => $totalSuccessful,
                'skipped' => $totalSkipped,
                'errors' => $allErrors,
                'batches_processed' => $draw - 1,
                'start_from' => $startFrom,
                'end_at' => $currentStart - $batchSize
            ];

            $this->logger->info('Batch import completed', $finalResults);
            
            // Log actual database count for verification
            $actualCount = $this->getTotalTenderCount();
            $this->logger->info('Database verification', [
                'reported_successful' => $totalSuccessful,
                'actual_database_count' => $actualCount
            ]);
            
            return $finalResults;

        } catch (\Exception $e) {
            $this->logger->error('Batch import failed', [
                'error' => $e->getMessage(),
                'total_processed' => $totalProcessed,
                'total_successful' => $totalSuccessful,
                'total_skipped' => $totalSkipped,
                'batches_processed' => $draw - 1
            ]);
            throw $e;
        }
    }

    /**
     * Get total count of tenders in database
     */
    public function getTotalTenderCount(): int
    {
        return $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Tender::class, 't')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get count of tenders created after a specific date
     */
    public function getTenderCountAfterDate(\DateTimeInterface $date): int
    {
        return $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Tender::class, 't')
            ->where('t.created >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }
} 