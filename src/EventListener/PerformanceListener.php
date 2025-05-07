<?php

namespace App\EventListener;

use App\Service\MonitoringService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

class PerformanceListener implements EventSubscriberInterface
{
    private $startTime;
    private $logger;
    private $monitoringService;

    public function __construct(
        LoggerInterface $logger,
        MonitoringService $monitoringService
    ) {
        $this->logger = $logger;
        $this->monitoringService = $monitoringService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->startTime = microtime(true);
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $this->startTime) * 1000; // Convert to milliseconds

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $method = $request->getMethod();
        $memoryUsage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        // Log to regular logger
        $this->logger->info('Route performance', [
            'route' => $route,
            'method' => $method,
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_mb' => $memoryUsage,
        ]);

        // Write to InfluxDB
        try {
            $this->monitoringService->writeMetric(
                'route_performance',
                [
                    'execution_time_ms' => round($executionTime, 2),
                    'memory_usage_mb' => $memoryUsage,
                ],
                [
                    'route' => $route,
                    'method' => $method,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to write performance metric to InfluxDB', [
                'error' => $e->getMessage(),
                'route' => $route,
                'method' => $method,
            ]);
        }
    }
}