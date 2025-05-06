<?php

namespace App\Controller;

use App\Service\ReportedMessageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/reports')]
class ReportedMessageController extends AbstractController
{
    public function __construct(
        private ReportedMessageService $reportedMessageService
    ) {
    }

    #[Route('', name: 'get_reported_messages', methods: ['GET'])]
    public function getReportedMessages(Request $request): JsonResponse
    {
        $filters = [
            'author_id' => $request->query->get('author_id'),
            'reporter_id' => $request->query->get('reporter_id'),
            'message_uid' => $request->query->get('message_uid')
        ];

        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        $response = $this->reportedMessageService->getReportedMessages($filters, $limit, $offset);
        return new JsonResponse($response, $response['status'] === 'OK' ? 200 : 400);
    }

    #[Route('/{id}', name: 'delete_reported_message', methods: ['DELETE'])]
    public function deleteReportedMessage(int $id): JsonResponse
    {
        $response = $this->reportedMessageService->deleteReportedMessage($id);
        return new JsonResponse($response, $response['status'] === 'OK' ? 200 : 400);
    }
}