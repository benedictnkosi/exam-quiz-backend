<?php

namespace App\Service;

use App\Entity\ExamPaper;
use App\Repository\ExamPaperRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ExamPaperProcessorService
{
    public function __construct(
        private ExamPaperRepository $examPaperRepository,
        private QuestionNumberExtractorService $questionNumberExtractor
    ) {
    }

    public function processPendingPapers(): array
    {
        $results = [
            'success' => [],
            'errors' => []
        ];

        $pendingPapers = $this->examPaperRepository->findBy(['status' => 'pending']);

        foreach ($pendingPapers as $paper) {
            try {
                $this->processPaper($paper);
                $results['success'][] = $paper->getId();
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'id' => $paper->getId(),
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    public function processPaper(ExamPaper $paper): void
    {
        if (!$paper->getPaperOpenAiFileId()) {
            throw new \Exception('No OpenAI file ID found for paper');
        }

        try {
            $questionNumbers = $this->questionNumberExtractor->extractQuestionNumbers($paper->getPaperOpenAiFileId());

            $paper->setQuestionNumbers($questionNumbers);
            $paper->setNumberOfQuestions(count($questionNumbers));
            $paper->setStatus('processed_numbers');

            $this->examPaperRepository->save($paper, true);
        } catch (\Exception $e) {
            $paper->setStatus('error');
            $this->examPaperRepository->save($paper, true);
            throw $e;
        }
    }
}