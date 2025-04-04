<?php

namespace App\Controller;

use App\Entity\Learner;
use App\Service\TodoService;
use App\Repository\LearnerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use Psr\Log\LoggerInterface;


#[Route('/api/todos')]
class TodoController extends AbstractController
{
    private $serializer;


    public function __construct(
        private TodoService $todoService,
        private LearnerRepository $learnerRepository,
        private LoggerInterface $logger
    ) {
        $this->serializer = SerializerBuilder::create()->build();
    }

    #[Route('', name: 'todo_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $learnerUid = $data['learnerUid'] ?? null;
        $title = $data['title'] ?? null;
        $dueDate = isset($data['dueDate']) 
            ? new \DateTimeImmutable($data['dueDate'])
            : null;

        if (!$learnerUid || !$title) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'learnerUid and title are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $response = $this->todoService->create($learnerUid, $title, $dueDate);
        $context = SerializationContext::create()->enableMaxDepthChecks()->setGroups(['todo']);
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}', name: 'todo_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['learnerUid'])) {
            return new JsonResponse(['error' => 'learnerUid is required'], Response::HTTP_BAD_REQUEST);
        }

        $learner = $this->learnerRepository->findOneBy(['uid' => $data['learnerUid']]);
        if (!$learner) {
            return new JsonResponse(['error' => 'Learner not found'], Response::HTTP_NOT_FOUND);
        }

        $todo = $this->todoService->getTodo($id);
        if ($todo->getLearner()->getId() !== $learner->getId()) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        if (isset($data['dueDate'])) {
            $data['dueDate'] = new \DateTime($data['dueDate']);
        }

        $updatedTodo = $this->todoService->updateTodo($id, $data);

        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($updatedTodo, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_OK, ['Access-Control-Allow-Origin' => '*'], true);
    }

    #[Route('/{id}', name: 'todo_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['learnerUid'])) {
            return new JsonResponse(['error' => 'learnerUid is required'], Response::HTTP_BAD_REQUEST);
        }

        $learner = $this->learnerRepository->findOneBy(['uid' => $data['learnerUid']]);
        if (!$learner) {
            return new JsonResponse(['error' => 'Learner not found'], Response::HTTP_NOT_FOUND);
        }

        $todo = $this->todoService->getTodo($id);
        if ($todo->getLearner()->getId() !== $learner->getId()) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->todoService->deleteTodo($id);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('', name: 'todo_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->logger->error('Listing todos');
        $learnerUid = $request->query->get('learnerUid');
        if (!$learnerUid) {
            return new JsonResponse(['error' => 'learnerUid is required'], Response::HTTP_BAD_REQUEST);
        }

        $learner = $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
        if (!$learner) {
            return new JsonResponse(['error' => 'Learner not found'], Response::HTTP_NOT_FOUND);
        }

        $status = $request->query->get('status');
        $todos = $status
            ? $this->todoService->getLearnerTodosByStatus($learner->getId(), $status)
            : $this->todoService->getLearnerTodos($learner->getId());

        $this->logger->info('Todos: ' . json_encode($todos));
            $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($todos, 'json', $context);
        return new JsonResponse($jsonContent, Response::HTTP_OK, ['Access-Control-Allow-Origin' => '*'], true);
    }

    #[Route('/{id}', name: 'todo_get', methods: ['GET'])]
    public function get(int $id, Request $request): JsonResponse
    {
        $learnerUid = $request->query->get('learnerUid');
        if (!$learnerUid) {
            return new JsonResponse(['error' => 'learnerUid is required'], Response::HTTP_BAD_REQUEST);
        }

        $learner = $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
        if (!$learner) {
            return new JsonResponse(['error' => 'Learner not found'], Response::HTTP_NOT_FOUND);
        }

        $todo = $this->todoService->getTodo($id);
        if ($todo->getLearner()->getId() !== $learner->getId()) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($todo, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_OK, ['Access-Control-Allow-Origin' => '*'], true);
    }
} 