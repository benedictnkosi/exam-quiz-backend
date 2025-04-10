<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RouteMonitoringService
{
    private LoggerInterface $logger;
    private MonitoringService $monitoringService;

    public function __construct(
        LoggerInterface $logger,
        MonitoringService $monitoringService
    ) {
        $this->logger = $logger;
        $this->monitoringService = $monitoringService;
    }

    public function monitorRoute(Request $request, Response $response): void
    {
        $path = $request->getPathInfo();
        $method = $request->getMethod();
        $statusCode = $response->getStatusCode();
        $startTime = $request->server->get('REQUEST_TIME_FLOAT');
        $duration = microtime(true) - $startTime;

        $this->logger->debug('Monitoring route', [
            'path' => $path,
            'method' => $method,
            'status_code' => $statusCode,
            'duration' => $duration
        ]);

        try {
            // Write metrics to InfluxDB with properly formatted data
            $this->monitoringService->writeMetric(
                'route_performance',
                [
                    'duration_ms' => round($duration * 1000, 2), // Convert to milliseconds
                    'status_code' => (int)$statusCode
                ],
                [
                    'path' => $this->sanitizePath($path),
                    'method' => $method
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to write route metrics', [
                'error' => $e->getMessage(),
                'path' => $path,
                'method' => $method
            ]);
        }
    }

    private function sanitizePath(string $path): string
    {
        // Remove any characters that might cause issues in InfluxDB
        return preg_replace('/[^a-zA-Z0-9\/\-_]/', '_', $path);
    }
} 