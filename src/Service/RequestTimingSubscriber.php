<?php

namespace App\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Psr\Log\LoggerInterface;

class RequestTimingSubscriber implements EventSubscriberInterface
{
    private $logger;
    private $startTimes = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        $request->attributes->set('request_start_time', microtime(true));
    }

    public function onKernelTerminate(TerminateEvent $event)
    {
        $request = $event->getRequest();
        $startTime = $request->attributes->get('request_start_time');

        if ($startTime) {
            $duration = microtime(true) - $startTime;
            $path = $request->getPathInfo();
            $method = $request->getMethod();
            $statusCode = $event->getResponse()->getStatusCode();

            $this->logger->info(sprintf(
                '[%s] %s %s - %d (%.2f ms)',
                date('Y-m-d H:i:s'),
                $method,
                $path,
                $statusCode,
                $duration * 1000
            ));
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            'kernel.request' => 'onKernelRequest',
            'kernel.terminate' => 'onKernelTerminate',
        ];
    }
}
