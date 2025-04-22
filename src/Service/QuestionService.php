<?php

namespace App\Service;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;

class QuestionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }


}