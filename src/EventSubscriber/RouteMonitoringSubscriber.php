<?php

namespace App\EventSubscriber;

use App\Service\RouteMonitoringService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RouteMonitoringSubscriber implements EventSubscriberInterface
{
    private RouteMonitoringService $routeMonitoringService;

    public function __construct(RouteMonitoringService $routeMonitoringService)
    {
        $this->routeMonitoringService = $routeMonitoringService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Monitor all routes, including public ones
        $this->routeMonitoringService->monitorRoute($request, $response);
    }
} 