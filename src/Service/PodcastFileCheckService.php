<?php

namespace App\Service;

use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PodcastFileCheckService
{
    private string $lecturesDirectory;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        ParameterBagInterface $params
    ) {
        $this->lecturesDirectory = $params->get('kernel.project_dir') . '/public/assets/lectures';
    }

    public function checkTopicRecordingFiles(): array
    {
        $this->logger->info('Starting podcast file check for topics');

        $topics = $this->entityManager->getRepository(Topic::class)->findAll();
        $results = [
            'total' => count($topics),
            'missing' => [],
            'found' => []
        ];

        foreach ($topics as $topic) {
            $recordingFileName = $topic->getRecordingFileName();
            if (!$recordingFileName) {
                continue;
            }

            // Check for both .opus and .m4a files
            $opusFile = $this->lecturesDirectory . '/' . $recordingFileName;
            $m4aFile = str_replace('.opus', '.m4a', $opusFile);

            $fileExists = file_exists($opusFile) || file_exists($m4aFile);

            $result = [
                'topic_id' => $topic->getId(),
                'name' => $topic->getName(),
                'sub_topic' => $topic->getSubTopic(),
                'recording_file' => $recordingFileName,
                'exists' => $fileExists
            ];

            if ($fileExists) {
                $results['found'][] = $result;
            } else {
                $results['missing'][] = $result;
                // Log detailed information about missing file
                $this->logger->warning('Missing podcast file', [
                    'topic_id' => $topic->getId(),
                    'topic_name' => $topic->getName(),
                    'sub_topic' => $topic->getSubTopic(),
                    'recording_file' => $recordingFileName,
                    'expected_opus_path' => $opusFile,
                    'expected_m4a_path' => $m4aFile,
                    'subject' => $topic->getSubject() ? $topic->getSubject()->getName() : 'Unknown'
                ]);
            }
        }

        $this->logger->info('Completed podcast file check', [
            'total' => $results['total'],
            'missing' => count($results['missing']),
            'found' => count($results['found'])
        ]);

        // Log a summary of missing files
        if (!empty($results['missing'])) {
            $this->logger->error('Summary of missing podcast files', [
                'missing_count' => count($results['missing']),
                'missing_files' => array_map(function ($item) {
                    return [
                        'topic_id' => $item['topic_id'],
                        'name' => $item['name'],
                        'sub_topic' => $item['sub_topic'],
                        'recording_file' => $item['recording_file']
                    ];
                }, $results['missing'])
            ]);
        }

        return $results;
    }
}