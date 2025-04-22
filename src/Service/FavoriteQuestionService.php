<?php

namespace App\Service;

use App\Entity\Favorites;
use App\Entity\Learner;
use App\Entity\Question;
use App\Entity\Subject;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class FavoriteQuestionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getFavorites(string $uid, string $subjectName): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $uid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $subjects = $this->entityManager->getRepository(Subject::class)
                ->createQueryBuilder('s')
                ->where('s.grade = :grade')
                ->andWhere('LOWER(s.name) LIKE LOWER(:name)')
                ->setParameter('grade', $learner->getGrade())
                ->setParameter('name', '%' . $subjectName . '%')
                ->getQuery()
                ->getResult();

            if (empty($subjects)) {
                return [
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                ];
            }

            $subjectIds = array_map(fn($subject) => $subject->getId(), $subjects);

            $favorites = $this->entityManager->getRepository(Favorites::class)
                ->createQueryBuilder('f')
                ->select('f.id', 'f.createdAt', 'q.id as questionId', 'q.question', 'q.aiExplanation', 'IDENTITY(q.subject) as subjectId', 'q.context', 'COUNT(f.id) as favoriteCount')
                ->innerJoin('f.question', 'q')
                ->where('f.learner = :learner')
                ->andWhere('q.subject IN (:subjects)')
                ->setParameter('learner', $learner)
                ->setParameter('subjects', $subjectIds)
                ->groupBy('f.id, q.id')
                ->orderBy('f.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            // Get top 5 most favorited questions for the subject, excluding user's own favorites
            $popularQuestions = $this->entityManager->getRepository(Favorites::class)
                ->createQueryBuilder('f')
                ->select('f.id', 'f.createdAt', 'q.id as questionId', 'q.question', 'q.aiExplanation', 'IDENTITY(q.subject) as subjectId', 'q.context', 'COUNT(f.id) as favoriteCount')
                ->innerJoin('f.question', 'q')
                ->where('q.subject IN (:subjects)')
                ->andWhere('f.learner != :learner')  // Exclude user's own favorites
                ->setParameter('subjects', $subjectIds)
                ->setParameter('learner', $learner)
                ->groupBy('f.id, q.id')
                ->orderBy('favoriteCount', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            return [
                'status' => 'OK',
                'message' => 'Favorite questions retrieved successfully',
                'data' => $favorites,
                'popular' => $popularQuestions
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in getFavorites: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Internal server error'
            ];
        }
    }

    public function removeFavorite(int $questionId, string $uid): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $uid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $favorite = $this->entityManager->getRepository(Favorites::class)
                ->findOneBy([
                    'question' => $questionId,
                    'learner' => $learner->getId()
                ]);

            if (!$favorite) {
                return [
                    'status' => 'NOK',
                    'message' => 'Favorite not found'
                ];
            }

            $this->entityManager->remove($favorite);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => 'Question removed from favorites successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in removeFavorite: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Internal server error'
            ];
        }
    }

    public function addFavorite(int $questionId, string $uid): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $uid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $question = $this->entityManager->getRepository(Question::class)
                ->find($questionId);

            if (!$question) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question not found'
                ];
            }

            // Check if favorite already exists
            $existingFavorite = $this->entityManager->getRepository(Favorites::class)
                ->findOneBy([
                    'question' => $questionId,
                    'learner' => $learner->getId()
                ]);

            if ($existingFavorite) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question is already in favorites'
                ];
            }

            // Count favorites for this subject
            $subjectFavoritesCount = $this->entityManager->getRepository(Favorites::class)
                ->createQueryBuilder('f')
                ->select('COUNT(f.id)')
                ->innerJoin('f.question', 'q')
                ->where('f.learner = :learner')
                ->andWhere('q.subject = :subject')
                ->setParameter('learner', $learner)
                ->setParameter('subject', $question->getSubject())
                ->getQuery()
                ->getSingleScalarResult();

            if ($subjectFavoritesCount >= 20) {
                return [
                    'status' => 'NOK',
                    'message' => 'You can only favorite up to 20 questions per subject'
                ];
            }

            // Create new favorite
            $favorite = new Favorites();
            $favorite->setLearner($learner);
            $favorite->setQuestion($question);
            $favorite->setCreatedAt(new \DateTime());

            $this->entityManager->persist($favorite);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => 'Question added to favorites successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in addFavorite: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Internal server error'
            ];
        }
    }
}