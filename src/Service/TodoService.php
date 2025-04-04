<?php

namespace App\Service;

use App\Entity\Todo;
use App\Entity\Learner;
use App\Repository\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TodoService
{
    public function __construct(
        private TodoRepository $todoRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function createTodo(Learner $learner, string $title, ?string $description = null, ?\DateTime $dueDate = null): Todo
    {
        $todo = new Todo();
        $todo->setTitle($title);
        $todo->setDescription($description);
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
        if (isset($data['description'])) {
            $todo->setDescription($data['description']);
        }
        if (isset($data['status'])) {
            $todo->setStatus($data['status']);
        }
        if (isset($data['dueDate'])) {
            $todo->setDueDate($data['dueDate']);
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

    public function getLearnerTodos(int $learnerId): array
    {
        return $this->todoRepository->findByLearner($learnerId);
    }

    public function getLearnerTodosByStatus(int $learnerId, string $status): array
    {
        return $this->todoRepository->findByLearnerAndStatus($learnerId, $status);
    }
} 