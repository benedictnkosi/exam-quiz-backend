<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Subject;
use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ExamReminderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private PushNotificationService $pushNotificationService,
        private ParameterBagInterface $params
    ) {
    }

    /**
     * Find exams due in X number of days and send notifications to eligible learners
     * 
     * @param int $daysAhead Number of days ahead to check for exams
     * @return array Array containing notification results
     */
    public function sendExamReminders(int $daysAhead): array
    {
        try {
            // Calculate the target date range
            $startDate = new \DateTime();
            $startDate->modify("+{$daysAhead} days");
            $startDate->setTime(0, 0, 0);

            $endDate = clone $startDate;
            $endDate->setTime(23, 59, 59);

            // Find subjects with exams on the target date
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('s')
                ->from(Subject::class, 's')
                ->where('s.examDate BETWEEN :startDate AND :endDate')
                ->andWhere('s.active = :active')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->setParameter('active', true);

            $subjects = $qb->getQuery()->getResult();

            if (empty($subjects)) {
                return [
                    'status' => 'OK',
                    'message' => 'No exams scheduled for the target date',
                    'notifications_sent' => 0
                ];
            }

            $notificationsSent = 0;
            $errors = [];

            foreach ($subjects as $subject) {
                // Find grade 1 learners who have answered questions for this subject
                $qb = $this->entityManager->createQueryBuilder();
                $qb->select('DISTINCT l')
                    ->from(Learner::class, 'l')
                    ->join(Result::class, 'r', 'WITH', 'r.learner = l.id')
                    ->join('r.question', 'q')
                    ->where('q.subject = :subject')
                    ->andWhere('l.grade = :grade')
                    ->andWhere('l.expoPushToken IS NOT NULL')
                    ->setParameter('subject', $subject)
                    ->setParameter('grade', 1); // Grade 1

                $learners = $qb->getQuery()->getResult();

                foreach ($learners as $learner) {
                    try {
                        $notification = [
                            'to' => $learner->getExpoPushToken(),
                            'title' => '🚨 Exam Coming Up!',
                            'body' => sprintf(
                                '📖 %s is in just %s days! ⏳ Time for a quick revision!',
                                $subject->getName(),
                                $daysAhead
                            ),
                            'sound' => 'default',
                            'data' => [
                                'type' => 'exam_reminder',
                                'subject' => $subject->getName(),
                                'examDate' => $subject->getExamDate()->format('Y-m-d H:i:s')
                            ]
                        ];

                        $this->pushNotificationService->sendPushNotification($notification);
                        $notificationsSent++;
                    } catch (\Exception $e) {
                        $errors[] = sprintf(
                            'Failed to send notification to learner %s: %s',
                            $learner->getUid(),
                            $e->getMessage()
                        );
                    }
                }
            }

            return [
                'status' => 'OK',
                'message' => sprintf(
                    'Successfully sent %d notifications. %d errors occurred.',
                    $notificationsSent,
                    count($errors)
                ),
                'notifications_sent' => $notificationsSent,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error sending exam reminders: ' . $e->getMessage()
            ];
        }
    }
}