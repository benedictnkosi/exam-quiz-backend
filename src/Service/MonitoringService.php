<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class MonitoringService
{
    private string $url;
    private string $token;
    private string $org;
    private string $bucket;
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 1000;

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
        $this->httpClient = HttpClient::create([
            'timeout' => 5.0,
        ]);
    }

    /**
     * Write a metric to InfluxDB
     *
     * @param string $measurement The measurement name
     * @param array $fields The fields to write (must be numeric or boolean)
     * @param array $tags The tags to associate with the measurement
     * @param int|null $timestamp Optional timestamp in nanoseconds
     * @throws \RuntimeException If the write operation fails
     */
    public function writeMetric(
        string $measurement,
        array $fields,
        array $tags = [],
        ?int $timestamp = null
    ): void {
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $this->logger->debug('Attempting to write metric to InfluxDB', [
                    'measurement' => $measurement,
                    'attempt' => $attempt + 1,
                    'fields' => $fields,
                    'tags' => $tags
                ]);

                // Format the line protocol
                $line = $this->formatLineProtocol($measurement, $fields, $tags, $timestamp);

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

                if ($response->getStatusCode() === 204) {
                    $this->logger->info('Successfully wrote metric to InfluxDB', [
                        'measurement' => $measurement,
                        'line' => $line
                    ]);
                    return;
                }

                throw new \RuntimeException(
                    'Failed to write metric: ' . $response->getContent(false)
                );
            } catch (TransportExceptionInterface $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < self::MAX_RETRIES) {
                    $this->logger->warning('Retrying metric write after failure', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'measurement' => $measurement
                    ]);
                    usleep(self::RETRY_DELAY_MS * 1000); // Convert to microseconds
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to write metric to InfluxDB', [
                    'measurement' => $measurement,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }

        $this->logger->error('Failed to write metric after all retries', [
            'measurement' => $measurement,
            'error' => $lastException ? $lastException->getMessage() : 'Unknown error',
            'attempts' => $attempt
        ]);
        throw new \RuntimeException(
            'Failed to write metric after ' . self::MAX_RETRIES . ' attempts: ' .
            ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    /**
     * Format the line protocol for InfluxDB
     *
     * @param string $measurement
     * @param array $fields
     * @param array $tags
     * @param int|null $timestamp
     * @return string
     */
    private function formatLineProtocol(
        string $measurement,
        array $fields,
        array $tags = [],
        ?int $timestamp = null
    ): string {
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

        // Use provided timestamp or current time in nanoseconds
        $timestamp = $timestamp ?? (int) (microtime(true) * 1_000_000_000);

        return sprintf(
            '%s%s %s %d',
            $this->escapeMeasurement($measurement),
            $tagString,
            implode(',', $fieldStrings),
            $timestamp
        );
    }

    /**
     * Escape a key for InfluxDB line protocol
     */
    private function escapeKey(string $key): string
    {
        return preg_replace('/[,\s=]/', '\\$0', $key);
    }

    /**
     * Escape a value for InfluxDB line protocol
     */
    private function escapeValue(string $value): string
    {
        return preg_replace('/[,\s=]/', '\\$0', $value);
    }

    /**
     * Escape a measurement name for InfluxDB line protocol
     */
    private function escapeMeasurement(string $measurement): string
    {
        return preg_replace('/[,\s]/', '\\$0', $measurement);
    }

    /**
     * Format a field value according to InfluxDB line protocol
     *
     * @param mixed $value
     * @return string
     */
    private function formatFieldValue($value): string
    {
        if (is_int($value)) {
            return $value . 'i';
        } elseif (is_float($value)) {
            return (string) $value;
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } else {
            return '"' . str_replace('"', '\\"', (string) $value) . '"';
        }
    }
}