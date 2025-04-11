<?php

namespace App\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RequestTimingSubscriber implements EventSubscriberInterface
{
    private $logger;
    private $startTimes = [];
    private HttpClientInterface $httpClient;

    private string $url;
    private string $token;
    private string $org;
    private string $bucket;

    public function __construct(
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
        string $influxdbUrl,
        string $influxdbToken,
        string $influxdbOrg,
        string $influxdbBucket
    ) {
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->url = rtrim($influxdbUrl, '/') . '/api/v2/write';
        $this->token = $influxdbToken;
        $this->org = $influxdbOrg;
        $this->bucket = $influxdbBucket;
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

            // Write metric to InfluxDB
            $this->writeMetric('request_timing', [
                'duration' => $duration * 1000, // Convert to milliseconds
            ], [
                'path' => $path,
                'method' => $method,
                'status_code' => (string)$statusCode,
            ]);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            'kernel.request' => 'onKernelRequest',
            'kernel.terminate' => 'onKernelTerminate',
        ];
    }

    public function writeMetric(string $measurement, array $fields, array $tags = []): void
    {
        try {
            $this->logger->info('Writing metric to InfluxDB', [
                'measurement' => $measurement,
                'fields' => $fields,
                'tags' => $tags
            ]);

            // Format the line protocol
            $line = $this->formatLineProtocol($measurement, $fields, $tags);
            
            // Send the request
            $response = $this->httpClient->request('POST', $this->url, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->token,
                    'Content-Type' => 'text/plain; charset=utf-8',
                ],
                'query' => [
                    'org' => $this->org,
                    'bucket' => $this->bucket,
                    'precision' => 'ns',
                ],
                'body' => $line,
            ]);

            if ($response->getStatusCode() !== 204) {
                throw new \RuntimeException('Failed to write metric: ' . $response->getContent(false));
            }

            $this->logger->info('Successfully wrote metric to InfluxDB', [
                'measurement' => $measurement,
                'line' => $line
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to write metric to InfluxDB', [
                'measurement' => $measurement,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function formatLineProtocol(string $measurement, array $fields, array $tags): string
    {
        // Format tags
        $tagString = '';
        foreach ($tags as $key => $value) {
            if (is_string($value)) {
                $tagString .= ',' . $this->escapeKey($key) . '=' . $this->escapeValue($value);
            }
        }

        // Format fields
        $fieldStrings = [];
        foreach ($fields as $key => $value) {
            if (is_numeric($value) || is_bool($value)) {
                $fieldStrings[] = $this->escapeKey($key) . '=' . $this->formatFieldValue($value);
            }
        }

        // Get current time in nanoseconds
        $timestamp = (int)(microtime(true) * 1_000_000_000);

        return sprintf(
            '%s%s %s %d',
            $this->escapeMeasurement($measurement),
            $tagString,
            implode(',', $fieldStrings),
            $timestamp
        );
    }

    private function escapeKey(string $key): string
    {
        return preg_replace('/[,\s=]/', '\\$0', $key);
    }

    private function escapeValue(string $value): string
    {
        return preg_replace('/[,\s=]/', '\\$0', $value);
    }

    private function escapeMeasurement(string $measurement): string
    {
        return preg_replace('/[,\s]/', '\\$0', $measurement);
    }

    private function formatFieldValue($value)
    {
        if (is_int($value)) {
            return $value . 'i';
        } elseif (is_float($value)) {
            return $value;
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } else {
            return '"' . str_replace('"', '\\"', (string)$value) . '"';
        }
    }
}
