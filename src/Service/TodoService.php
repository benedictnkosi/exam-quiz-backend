<?php

namespace App\Service;

use App\Entity\Todo;
use App\Entity\Learner;
use App\Repository\TodoRepository;
use App\Repository\LearnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use DateTimeImmutable;

class TodoService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TodoRepository $todoRepository,
        private LearnerRepository $learnerRepository
    ) {
    }


    public function updateTodo(int $id, array $data): Todo
    {
        $todo = $this->todoRepository->find($id);
        if (!$todo) {
            throw new NotFoundHttpException('Todo not found');
        }

        if (isset($data['title'])) {
            $todo->setTitle($data['title']);
        }
        if (isset($data['status'])) {
            $todo->setStatus($data['status']);
        }
        if (isset($data['dueDate'])) {
            $dueDate = $data['dueDate'] instanceof \DateTime 
                ? \DateTimeImmutable::createFromMutable($data['dueDate'])
                : new \DateTimeImmutable($data['dueDate']);
            $todo->setDueDate($dueDate);
        }

        $this->entityManager->flush();

        return $todo;
    }

    public function deleteTodo(int $id): void
    {
        $todo = $this->todoRepository->find($id);
        if (!$todo) {
            throw new NotFoundHttpException('Todo not found');
        }

        $this->entityManager->remove($todo);
        $this->entityManager->flush();
    }

    public function getTodo(int $id): Todo
    {
        $todo = $this->todoRepository->find($id);
        if (!$todo) {
            throw new NotFoundHttpException('Todo not found');
        }

        return $todo;
    }

    public function getLearnerTodos(int $learnerId, ?string $subjectName = null): array
    {
        if ($subjectName) {
            return $this->todoRepository->findFutureTodosByLearnerAndSubject($learnerId, $subjectName);
        }
        return $this->todoRepository->findFutureTodosByLearner($learnerId);
    }

    public function getLearnerTodosBySubject(int $learnerId, string $subjectName): array
    {
        return $this->todoRepository->findByLearnerAndSubjectWithinLast30Days($learnerId, $subjectName);
    }

    public function create(string $learnerUid, string $title, string $subjectName, ?DateTimeImmutable $dueDate = null): array
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $learnerUid]);
        if (!$learner) {
            return [
                'success' => false,
                'message' => 'Learner not found'
            ];
        }

        if ($dueDate) {
            $dueDate = $dueDate->setTime(23, 59, 59);
        }
        
        $todo = new Todo();
        $todo->setTitle($title);
        $todo->setSubjectName($subjectName);
        $todo->setStatus('pending');
        $todo->setCreatedAt(new DateTimeImmutable());
        $todo->setDueDate($dueDate);
        $todo->setLearner($learner);

        $this->entityManager->persist($todo);
        $this->entityManager->flush();

        return [
            'success' => true,
            'todo' => $todo
        ];
    }
} 