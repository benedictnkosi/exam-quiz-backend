<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MonitoringService
{
    private string $url;
    private string $token;
    private string $org;
    private string $bucket;
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;

    public function __construct(
        string $influxdbUrl,
        string $influxdbToken,
        string $influxdbOrg,
        string $influxdbBucket,
        LoggerInterface $logger
    ) {
        $this->url = rtrim($influxdbUrl, '/') . '/api/v2/write';
        $this->token = $influxdbToken;
        $this->org = $influxdbOrg;
        $this->bucket = $influxdbBucket;
        $this->logger = $logger;
        $this->httpClient = HttpClient::create();
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