<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:late:upload-youtube',
    description: 'Upload a video to YouTube via Late API (testing helper)'
)]
class LateUploadYouTubeCommand extends Command
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('video-url', null, InputOption::VALUE_OPTIONAL, 'Video URL to upload', 'https://files2.heygen.ai/aws_pacific/avatar_tmp/6e14c07771804ffaad19670e4febd520/c75e150c3d3f4cef82a4d39b3bd92013.mp4?Expires=1763029814&Signature=IBkgLGFAYTZWzta4IqMF5~PvRdOifTGwgNc1vHA2vrmXHmU-JKV9tNqJaTYJPob1HQsprgpNjhpvsRRxvGprgtv823ZiTbidR7Eg2GT7wz4uSzwu9MpN~TX8iXF1hvEFfL5Ijw~gvZjhF9UOR5NGSJrwcMDxUPbVsD~wQH4eeMQdHcgQMUkc17iETRtlrxZ8lcqAKHGQ8JpHn~BksrGfHXeH3wkJWPOzyI3rvPfKsKDnRh~OpI6P6fv8dR9hkjb5AfUGkRp11B5w7squPOrGC6-5WRbRyYfrLrVWqb3q~zoVNQ6SCG0cFWX9g13tc0NaLokyNiBY0rcWYjRsucBRiQ__&Key-Pair-Id=K38HBHX5LX3X2H')
            ->addOption('title', null, InputOption::VALUE_OPTIONAL, 'YouTube video title', 'SABC Digital News Test Upload')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'YouTube video description', 'Automated test upload via Late API from backend command.')
            ->addOption('privacy', null, InputOption::VALUE_OPTIONAL, 'YouTube privacyStatus (public|unlisted|private)', 'public')
            ->addOption('late-account-id', null, InputOption::VALUE_OPTIONAL, 'Late YouTube accountId (or set GETLATE_YOUTUBE_ACCOUNT_ID)')
            ->addOption('scheduled-for', null, InputOption::VALUE_OPTIONAL, 'ISO8601 UTC time to schedule (omit to publish now)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiKey = $this->resolveEnv('GETLATE_API_KEY');
        if ($apiKey === '') {
            $output->writeln('<error>GETLATE_API_KEY is not set in environment.</error>');
            return Command::FAILURE;
        }

        $accountId = (string)($input->getOption('late-account-id') ?: ($this->resolveEnv('GETLATE_YOUTUBE_ACCOUNT_ID') ?: $this->resolveEnv('GETLATE_ACCOUNT_ID') ?: ''));
        if ($accountId === '') {
            $output->writeln('<error>No Late accountId provided. Use --late-account-id or set GETLATE_YOUTUBE_ACCOUNT_ID.</error>');
            return Command::FAILURE;
        }

        $videoUrl = (string)$input->getOption('video-url');
        $title = (string)$input->getOption('title');
        $description = (string)$input->getOption('description');
        $privacy = (string)$input->getOption('privacy');
        $scheduledFor = $input->getOption('scheduled-for');

        $endpoint = 'https://getlate.dev/api/v1/posts';
        $payload = [
            'platforms' => [[
                'platform' => 'youtube',
                'accountId' => $accountId,
                'platformSpecificData' => [
                    'privacyStatus' => $privacy,
                    'title' => $title,
                    'description' => $description,
                ],
            ]],
            'content' => $title,
            'mediaItems' => [[
                'type' => 'video',
                'url' => $videoUrl,
            ]],
        ];

        if (is_string($scheduledFor) && trim($scheduledFor) !== '') {
            $payload['scheduledFor'] = $scheduledFor;
        }

        $output->writeln('<info>Submitting upload request to Late...</info>');
        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 60,
            ]);

            $status = $response->getStatusCode();
            $data = null;
            try { $data = $response->toArray(false); } catch (\Throwable $e) { /* ignore parse errors */ }

            if ($status >= 200 && $status < 300) {
                $output->writeln('<info>Late upload request accepted.</info>');
                if (is_array($data)) {
                    $this->logger->info('Late upload success', ['response' => $data]);
                    $preview = json_encode($data);
                    if ($preview !== false) {
                        $output->writeln('<comment>Response: ' . mb_strimwidth($preview, 0, 300, '...') . '</comment>');
                    }
                }
                return Command::SUCCESS;
            }

            $this->logger->error('Late upload error', ['status' => $status, 'response' => $data]);
            $output->writeln('<error>Late upload failed with status ' . $status . '</error>');
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $this->logger->error('Late upload exception', ['error' => $e->getMessage()]);
            $output->writeln('<error>Late upload exception: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function resolveEnv(string $name): string
    {
        if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return (string)$_SERVER[$name];
        }
        if (isset($_ENV[$name]) && is_string($_ENV[$name]) && $_ENV[$name] !== '') {
            return (string)$_ENV[$name];
        }
        $val = getenv($name);
        return is_string($val) ? $val : '';
    }
}


