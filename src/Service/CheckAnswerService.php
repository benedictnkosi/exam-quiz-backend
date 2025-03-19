<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Question;
use App\Entity\Result;
use App\Entity\SubjectPoints;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CheckAnswerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function checkAnswer(string $uid, int $questionId, string $answer, ?int $duration): array
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

            // Check if the question is favorited by the learner
            $isFavorited = $this->entityManager->getRepository('App\Entity\Favorites')
                ->findOneBy([
                    'learner' => $learner,
                    'question' => $question
                ]);

            // Check the answer
            $isCorrect = $this->validateAnswer($answer, $question->getAnswer());

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
                    'is_favorited' => false
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
                    'is_favorited' => true
                ];
            }

            // Record the result
            $result = new Result();
            $result->setLearner($learner)
                ->setQuestion($question)
                ->setOutcome($isCorrect ? 'correct' : 'incorrect')
                ->setDuration($isCorrect ? $duration : 0);

            $this->entityManager->persist($result);

            // Get last 3 answers
            $lastAnswers = $this->entityManager->getRepository(Result::class)
                ->createQueryBuilder('r')
                ->where('r.learner = :learner')
                ->setParameter('learner', $learner)
                ->orderBy('r.created', 'DESC')
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();

            $lastThreeCorrect = count($lastAnswers) === 3 &&
                array_reduce($lastAnswers, fn($carry, $item) => $carry && $item->getOutcome() === 'correct', true);

            // Calculate points change
            $pointsChange = $isCorrect ? ($lastThreeCorrect ? 3 : 1) : -1;
            $newPoints = max(0, $learner->getPoints() + $pointsChange);
            $learner->setPoints($newPoints);

            // Update subject points
            if ($question->getSubject()) {
                $subjectPoints = $this->entityManager->getRepository(SubjectPoints::class)
                    ->findOneBy([
                        'learner' => $learner,
                        'subject' => $question->getSubject()
                    ]);

                if (!$subjectPoints) {
                    $subjectPoints = new SubjectPoints();
                    $subjectPoints->setLearner($learner)
                        ->setSubject($question->getSubject())
                        ->setPoints(max(0, $pointsChange));
                    $this->entityManager->persist($subjectPoints);
                } else {
                    $newSubjectPoints = max(0, $subjectPoints->getPoints() + $pointsChange);
                    $subjectPoints->setPoints($newSubjectPoints);
                }
            }

            // Update streak
            $currentStreak = $learner->getStreak();
            $streakUpdated = false;

            if ($isCorrect) {
                $today = new \DateTime();
                $today->setTime(0, 0, 0);
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
                    }
                }
            }

            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'correct' => $isCorrect,
                'explanation' => $question->getExplanation(),
                'correctAnswer' => $question->getAnswer(),
                'points' => $newPoints,
                'message' => $isCorrect ? 'Correct answer!' : 'Incorrect answer',
                'lastThreeCorrect' => $lastThreeCorrect,
                'streak' => $currentStreak,
                'streakUpdated' => $streakUpdated,
                'subject' => $question->getSubject() ? $question->getSubject()->getName() : null,
                'is_favorited' => false
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
}