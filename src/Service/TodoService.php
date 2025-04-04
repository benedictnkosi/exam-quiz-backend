<?php

namespace App\Service;

use App\Entity\Todo;
use App\Entity\Learner;
use App\Repository\TodoRepository;
use App\Repository\LearnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TodoService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TodoRepository $todoRepository,
        private LearnerRepository $learnerRepository
    ) {
    }

    public function createTodo(Learner $learner, string $title, ?\DateTimeImmutable $dueDate = null): Todo
    {
        $todo = new Todo();
        $todo->setTitle($title);
        $todo->setDueDate($dueDate);
        $todo->setLearner($learner);
        $todo->setStatus('pending');

        $this->entityManager->persist($todo);
        $this->entityManager->flush();

        return $todo;
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
            return $this->todoRepository->findByLearnerAndSubject($learnerId, $subjectName);
        }
        return $this->todoRepository->findByLearner($learnerId);
    }

    public function getLearnerTodosBySubject(int $learnerId, string $subjectName): array
    {
        return $this->todoRepository->findByLearnerAndSubject($learnerId, $subjectName);
    }

    public function create(string $learnerUid, string $title, ?\DateTimeImmutable $dueDate = null): array
    {
        $learner = $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
        if (!$learner) {
            return [
                'status' => 'NOK',
                'message' => 'Learner not found'
            ];
        }

        $todo = new Todo();
        $todo->setTitle($title);
        $todo->setDueDate($dueDate);
        $todo->setLearner($learner);
        $todo->setStatus('pending');

        $this->entityManager->persist($todo);
        $this->entityManager->flush();

        return [
            'status' => 'OK',
            'message' => 'Todo created successfully',
            'todo' => $todo
        ];
    }
} 