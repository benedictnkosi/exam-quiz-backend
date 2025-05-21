<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Question;
use App\Entity\Result;
use App\Entity\SubjectPoints;
use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CheckAnswerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private PushNotificationService $pushNotificationService,
        private LearnerAdTrackingService $adTrackingService,
        private LearnerDailyUsageService $dailyUsageService
    ) {
    }

    public function checkAnswer(string $uid, int $questionId, string $answer, ?int $duration, string $mode = 'normal', ?string $sheetCell = null): array
    {
        try {
            // Get the learner
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $uid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Get the question
            $question = $this->entityManager->getRepository(Question::class)
                ->find($questionId);

            if (!$question) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question not found'
                ];
            }

            // Get topic information
            $topic = null;
            $recordingFileName = null;
            if ($question->getTopic()) {
                $topicEntity = $this->entityManager->getRepository(Topic::class)
                    ->createQueryBuilder('t')
                    ->where('t.subTopic = :subTopic')
                    ->andWhere('t.subject = :subject')
                    ->setParameter('subTopic', $question->getTopic())
                    ->setParameter('subject', $question->getSubject())
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($topicEntity) {
                    $topic = $topicEntity->getName();
                    $recordingFileName = $topicEntity->getRecordingFileName();
                }
            }

            // Check if the question is favorited by the learner
            $isFavorited = $this->entityManager->getRepository('App\Entity\Favorites')
                ->findOneBy([
                    'learner' => $learner,
                    'question' => $question
                ]);

            // Check the answer
            if ($question->getAnswerSheet() === null) {
                $this->logger->info("Question {$questionId} has no answer sheet");
                $isCorrect = $this->validateAnswer($answer, $question->getAnswer());
            } else {
                $this->logger->info("Question {$questionId} has an answer sheet");
                $isCorrect = $this->validateAccountingAnswer($sheetCell, $answer, $question->getAnswerSheet());
            }

            //if is learner is admin return the results without recording or awarding points
            if ($learner->getRole() === 'admin' || $learner->getRole() === 'reviewer') {
                return [
                    'status' => 'OK',
                    'correct' => $isCorrect,
                    'explanation' => $question->getExplanation(),
                    'correctAnswer' => $question->getAnswer(),
                    'points' => $learner->getPoints(), // Return current points without change
                    'message' => $isCorrect ? 'Correct answer! (No points awarded for favorited question)' : 'Incorrect answer',
                    'lastThreeCorrect' => false,
                    'streak' => $learner->getStreak(),
                    'streakUpdated' => false,
                    'subject' => $question->getSubject() ? $question->getSubject()->getName() : null,
                    'is_favorited' => false,
                    'topic' => $topic,
                    'recordingFileName' => $recordingFileName,
                    'remaining_quizzes' => 20
                ];
            }

            // If the question is favorited, return the result without recording or awarding points
            if ($isFavorited) {
                $this->logger->info("Question {$questionId} is favorited by learner {$uid}. Skipping points and results.");
                return [
                    'status' => 'OK',
                    'correct' => $isCorrect,
                    'explanation' => $question->getExplanation(),
                    'correctAnswer' => $question->getAnswer(),
                    'points' => $learner->getPoints(), // Return current points without change
                    'message' => $isCorrect ? 'Correct answer! (No points awarded for favorited question)' : 'Incorrect answer',
                    'lastThreeCorrect' => false,
                    'streak' => $learner->getStreak(),
                    'streakUpdated' => false,
                    'subject' => $question->getSubject() ? $question->getSubject()->getName() : null,
                    'is_favorited' => true,
                    'topic' => $topic,
                    'recordingFileName' => $recordingFileName,
                    'remaining_quizzes' => 20
                ];
            }

            // If mode is 'recording', return the result without persisting any data
            if ($mode === 'recording') {
                return [
                    'status' => 'OK',
                    'correct' => $isCorrect,
                    'explanation' => $question->getExplanation(),
                    'correctAnswer' => $question->getAnswer(),
                    'points' => $learner->getPoints(), // Return current points without change
                    'message' => $isCorrect ? 'Correct answer! (No points awarded in recording mode)' : 'Incorrect answer',
                    'lastThreeCorrect' => false,
                    'streak' => $learner->getStreak(),
                    'streakUpdated' => false,
                    'subject' => $question->getSubject() ? $question->getSubject()->getName() : null,
                    'is_favorited' => false,
                    'topic' => $topic,
                    'recordingFileName' => $recordingFileName,
                    'remaining_quizzes' => 20
                ];
            }

            // Record the result
            $date = new \DateTime('now', new \DateTimeZone('Africa/Johannesburg'));
            $result = new Result();
            $result->setLearner($learner)
                ->setQuestion($question)
                ->setOutcome($isCorrect ? 'correct' : 'incorrect')
                ->setDuration($isCorrect ? $duration : 0)
                ->setCreated($date);

            $this->entityManager->persist($result);

            // Check if this is the learner's first answer
            $previousResults = $this->entityManager->getRepository(Result::class)
                ->createQueryBuilder('r')
                ->where('r.learner = :learner')
                ->setParameter('learner', $learner)
                ->getQuery()
                ->getResult();

            $this->logger->info("previousResults: " . count($previousResults));

            $isFirstAnswer = count(value: $previousResults) === 0; // Current result is already persisted but not flushed

            // Calculate points change - 10 points for first answer, otherwise normal scoring
            $pointsChange = $isFirstAnswer ? 10 : ($isCorrect ? 1 : -1);
            $newPoints = max(0, $learner->getPoints() + $pointsChange);
            $learner->setPoints($newPoints);

            // Update streak
            $currentStreak = $learner->getStreak();
            $streakUpdated = false;

            if ($isCorrect) {
                $today = new \DateTime();
                $today->setTime(hour: 0, minute: 0, second: 0);
                $lastUpdated = $learner->getStreakLastUpdated();
                $wasUpdatedToday = $lastUpdated && $lastUpdated >= $today;

                $this->logger->info("wasUpdatedToday: " . $wasUpdatedToday);
                if (!$wasUpdatedToday) {
                    $todayResults = $this->entityManager->getRepository(Result::class)
                        ->createQueryBuilder('r')
                        ->where('r.learner = :learner')
                        ->andWhere('r.created >= :today')
                        ->andWhere('r.outcome = :outcome')
                        ->setParameter('learner', $learner)
                        ->setParameter('today', $today)
                        ->setParameter('outcome', 'correct')
                        ->getQuery()
                        ->getResult();

                    $this->logger->info("todayResults: " . count($todayResults));
                    if (count($todayResults) >= 3) {
                        $currentStreak++;
                        $streakUpdated = true;
                        $learner->setStreak($currentStreak)
                            ->setStreakLastUpdated(new \DateTime());

                        // Send streak notification to followers
                        $this->pushNotificationService->sendStreakNotification($learner, $currentStreak);
                    }
                }
            }

            // Increment quiz usage
            $this->dailyUsageService->incrementQuizUsage($learner);

            $this->entityManager->flush();

            // Update ad tracking for questions answered
            $this->adTrackingService->incrementQuestionsAnswered($learner);

            $date = new \DateTime('now', new \DateTimeZone('Africa/Johannesburg'));
            $learner->setLastSeen($date);
            $this->entityManager->persist($learner);
            $this->entityManager->flush();


            return [
                'status' => 'OK',
                'correct' => $isCorrect,
                'explanation' => $question->getExplanation(),
                'correctAnswer' => $question->getAnswer(),
                'points' => $newPoints,
                'message' => $isCorrect ? 'Correct answer!' : 'Incorrect answer',
                'lastThreeCorrect' => false,
                'streak' => $currentStreak,
                'streakUpdated' => $streakUpdated,
                'subject' => $question->getSubject() ? $question->getSubject()->getName() : null,
                'is_favorited' => false,
                'topic' => $topic,
                'recordingFileName' => $recordingFileName
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in checkAnswer: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error checking answer',
                'error' => $e->getMessage()
            ];
        }
    }

    private function validateAnswer(string $submittedAnswer, string $correctAnswer): bool
    {
        $normalizeAnswer = function (string $answer): string {
            // Convert to string in case we get a numeric value
            $answer = (string) $answer;

            $answer = trim(strtolower($answer));
            // Remove quotes
            $answer = preg_replace('/[\'"]/', '', $answer);
            // Remove leading/trailing brackets
            $answer = preg_replace('/^\[|\]$/', '', $answer);
            // Handle numeric answers
            $answer = preg_replace('/\s/', '', $answer);
            $answer = preg_replace('/,(?=\d)/', '.', $answer);

            return $answer;
        };

        try {
            $correctAnswers = json_decode($correctAnswer, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $correctAnswers = is_array($correctAnswers) ? $correctAnswers : [$correctAnswers];
            } else {
                $correctAnswers = [$correctAnswer];
            }
        } catch (\Exception $e) {
            $correctAnswers = [$correctAnswer];
        }

        $normalizedSubmitted = $normalizeAnswer($submittedAnswer);
        $normalizedCorrect = array_map($normalizeAnswer, $correctAnswers);

        return in_array($normalizedSubmitted, $normalizedCorrect);
    }

    private function validateAccountingAnswer(string $sheetCell, string $submittedAnswer, string $answerSheet): bool
    {
        try {
            $this->logger->info("Validating accounting answer for cell: {$sheetCell}");
            $this->logger->info("Submitted answer: {$submittedAnswer}");

            $answerSheetData = json_decode($answerSheet, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Invalid answer sheet JSON format');
                return false;
            }

            // Extract row and column from cell reference (e.g., 'A1' -> row=0, col='A')
            $col = strtoupper(substr($sheetCell, 0, 1));
            $row = intval(substr($sheetCell, 1)) - 1;

            $this->logger->info("Looking for row: {$row}, column: {$col}");

            // Check if row exists in answer sheet
            if (!isset($answerSheetData[$row])) {
                $this->logger->error("Row {$row} not found in answer sheet");
                return false;
            }

            $rowData = $answerSheetData[$row];

            // Check if column exists in row
            if (!isset($rowData[$col])) {
                $this->logger->error("Column {$col} not found in row {$row}");
                return false;
            }

            $cellData = $rowData[$col];
            $this->logger->info("Cell data found: " . json_encode($cellData));

            // Handle the standard cell format
            if (is_array($cellData) && isset($cellData['correct'])) {
                $correctAnswer = $cellData['correct'];
                $this->logger->info("Correct answer from cell: {$correctAnswer}");

                $normalizedSubmitted = $this->normalizeAccountingValue($submittedAnswer);
                $normalizedCorrect = $this->normalizeAccountingValue($correctAnswer);

                $this->logger->info("Normalized submitted: {$normalizedSubmitted}");
                $this->logger->info("Normalized correct: {$normalizedCorrect}");

                return $normalizedSubmitted === $normalizedCorrect;
            }

            // Handle simple string values (fallback)
            if (is_string($cellData)) {
                $this->logger->info("Simple string value found: {$cellData}");
                return $this->normalizeAccountingValue($submittedAnswer) === $this->normalizeAccountingValue($cellData);
            }

            $this->logger->error("Invalid cell data format");
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error validating accounting answer: ' . $e->getMessage());
            return false;
        }
    }

    private function normalizeAccountingValue(string $value): string
    {
        // Remove all spaces
        $value = preg_replace('/\s+/', '', $value);

        // Convert to lowercase
        $value = strtolower($value);

        // Remove any currency symbols or other special characters
        $value = preg_replace('/[^a-z0-9\-\(\)\.]/', '', $value);

        return $value;
    }
}