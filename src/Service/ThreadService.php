<?php

namespace App\Service;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ThreadService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient
    ) {
    }

    public function createThreadFromBook(Book $book, string $userUid, string $userName): array
    {
        $response = $this->httpClient->request('POST', 'https://examquiz.co.za/api/threads/create', [
            'json' => [
                'threadName' => $book->getChatThreadTitle(),
                'messageBody' => $book->getChatThreadContent(),
                'subjectName' => 'The Dimpo Chronicles',
                'grade' => 12,
                'createdById' => $userUid,
                'createdByName' => $userName
            ]
        ]);

        return $response->toArray();
    }

    public function getBookForToday(): ?Book
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');

        return $this->entityManager->getRepository(Book::class)
            ->createQueryBuilder('b')
            ->where('b.publishDate >= :today')
            ->andWhere('b.publishDate < :tomorrow')
            ->andWhere('b.chatThreadTitle IS NOT NULL')
            ->andWhere('b.chatThreadContent IS NOT NULL')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}