<?php

namespace App\Service;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class QuestionStatsService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {
    }

    public function getQuestionStats(string $fromDate, string $endDate): array
    {
        try {
            $this->logger->info("Getting question stats from date: " . $fromDate);

            // Validate date format
            $date = \DateTime::createFromFormat('Y-m-d', $fromDate);
            if (!$date) {
                return [
                    'status' => 'NOK',
                    'message' => 'Invalid date format. Please use YYYY-MM-DD'
                ];
            }

            $fromDateTime = new \DateTime($fromDate . ' 00:01:00');
            $endDateTime = new \DateTime($endDate . ' 23:59:59');

            $qb = $this->em->createQueryBuilder();
            $qb->select('q')
                ->from('App\\Entity\\Question', 'q')
                ->leftJoin('q.subject', 's')  // Add join to ensure we get questions even without subjects
                ->where('q.created >= :fromDate')
                ->andWhere('q.created <= :endDate')
                ->setParameter('fromDate', $fromDateTime)
                ->setParameter('endDate', $endDateTime);

            $questions = $qb->getQuery()->getResult();

            $stats = [
                'total_questions' => count($questions),
                'status_counts' => [
                    'new' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                    'pending' => 0
                ],
                'subject_counts' => [
                    'unknown' => 0  // Add default for questions without subject
                ],
                'grade_counts' => [
                    'unknown' => 0  // Add default for questions without grade
                ],
                'capturer_stats' => [
                    'unknown' => [
                        'total' => 0,
                        'status_counts' => [
                            'new' => 0,
                            'approved' => 0,
                            'rejected' => 0,
                            'pending' => 0
                        ],
                        'email' => 'unknown'
                    ]
                ]
            ];

            foreach ($questions as $question) {
                $subject = $question->getSubject();
                $capturer = $question->getCapturer();
                $status = strtolower($question->getStatus() ?? 'pending');

                $this->logger->info("Processing Question ID: " . $question->getId());

                // Handle capturer statistics
                $capturerKey = 'unknown';
                if ($capturer) {
                    if (method_exists($capturer, 'getUid') && $capturer->getUid()) {
                        $capturerKey = $capturer->getUid();
                        $this->logger->info("Found capturer with UID: " . $capturerKey);

                        if (!isset($stats['capturer_stats'][$capturerKey])) {
                            $this->logger->info("Initializing new capturer stats for: " . $capturerKey);
                            $stats['capturer_stats'][$capturerKey] = [
                                'total' => 0,
                                'status_counts' => [
                                    'new' => 0,
                                    'approved' => 0,
                                    'rejected' => 0,
                                    'pending' => 0
                                ],
                                'email' => method_exists($capturer, 'getEmail') ? $capturer->getEmail() : 'unknown',
                                'name' => method_exists($capturer, 'getName') ? $capturer->getName() : (
                                    method_exists($capturer, 'getFirstName') ?
                                    trim($capturer->getFirstName() . ' ' . ($capturer->getLastName() ?? '')) :
                                    'unknown'
                                )
                            ];
                        } else {
                            $this->logger->info("Using existing capturer stats for: " . $capturerKey);
                        }
                    } else {
                        $this->logger->warning("Capturer exists but has no UID, using 'unknown'");
                    }
                } else {
                    $this->logger->info("No capturer found, using 'unknown'");
                }

                // Update capturer statistics
                $stats['capturer_stats'][$capturerKey]['total']++;
                $stats['capturer_stats'][$capturerKey]['status_counts'][$status]++;

                $this->logger->debug("Updated capturer stats: " . json_encode($stats['capturer_stats'][$capturerKey]));

                // Handle subject and grade statistics
                if ($subject && method_exists($subject, 'getName')) {
                    try {
                        $subjectName = $subject->getName();
                        if ($subjectName) {
                            if (!isset($stats['subject_counts'][$subjectName])) {
                                $stats['subject_counts'][$subjectName] = 0;
                            }
                            $stats['subject_counts'][$subjectName]++;

                            // Count by grade through subject
                            $grade = $subject->getGrade();
                            if ($grade && method_exists($grade, 'getNumber')) {
                                $gradeNumber = $grade->getNumber();
                                if ($gradeNumber !== null) {
                                    if (!isset($stats['grade_counts'][$gradeNumber])) {
                                        $stats['grade_counts'][$gradeNumber] = 0;
                                    }
                                    $stats['grade_counts'][$gradeNumber]++;
                                } else {
                                    $stats['grade_counts']['unknown']++;
                                }
                            } else {
                                $stats['grade_counts']['unknown']++;
                            }
                        } else {
                            $stats['subject_counts']['unknown']++;
                            $stats['grade_counts']['unknown']++;
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('Error processing subject: ' . $e->getMessage());
                        $stats['subject_counts']['unknown']++;
                        $stats['grade_counts']['unknown']++;
                    }
                } else {
                    $stats['subject_counts']['unknown']++;
                    $stats['grade_counts']['unknown']++;
                }

                // Update overall status counts
                if (!isset($stats['status_counts'][$status])) {
                    $stats['status_counts'][$status] = 0;
                }
                $stats['status_counts'][$status]++;
            }

            // Sort all arrays by keys
            ksort($stats['subject_counts']);
            ksort($stats['grade_counts']);
            ksort($stats['status_counts']);
            ksort($stats['capturer_stats']);

            // Calculate percentages for each capturer
            foreach ($stats['capturer_stats'] as &$capturerStats) {
                $total = $capturerStats['total'];
                if ($total > 0) {
                    $capturerStats['percentages'] = [];
                    foreach ($capturerStats['status_counts'] as $status => $count) {
                        $capturerStats['percentages'][$status] = round(($count / $total) * 100, 2);
                    }
                }
            }

            // Remove empty categories
            if ($stats['subject_counts']['unknown'] === 0) {
                unset($stats['subject_counts']['unknown']);
            }
            if ($stats['grade_counts']['unknown'] === 0) {
                unset($stats['grade_counts']['unknown']);
            }
            if ($stats['capturer_stats']['unknown']['total'] === 0) {
                unset($stats['capturer_stats']['unknown']);
            }

            return [
                'status' => 'OK',
                'data' => $stats
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error getting question stats: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving question statistics ' . $e->getMessage()
            ];
        }
    }
}