<?php

namespace App\Service;

use App\Entity\Lesson;
use App\Entity\Unit;
use Doctrine\ORM\EntityManagerInterface;

class LessonManagementService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function addLesson(array $data, Unit $unit): Lesson
    {
        $lesson = new Lesson();
        $lesson->setTitle($data['title']);
        $lesson->setLessonOrder($data['lessonOrder']);
        $lesson->setUnit($unit);
        $this->em->persist($lesson);
        $this->em->flush();
        return $lesson;
    }

    public function getLessons(): array
    {
        return $this->em->getRepository(Lesson::class)->findAll();
    }

    public function getLesson(int $id): ?Lesson
    {
        return $this->em->getRepository(Lesson::class)->find($id);
    }

    public function updateLesson(int $id, array $data): ?Lesson
    {
        $lesson = $this->getLesson($id);
        if (!$lesson) {
            return null;
        }
        if (isset($data['title'])) {
            $lesson->setTitle($data['title']);
        }
        if (isset($data['lessonOrder'])) {
            $lesson->setLessonOrder($data['lessonOrder']);
        }
        if (isset($data['unitId'])) {
            $unit = $this->em->getRepository(Unit::class)->find($data['unitId']);
            if ($unit) {
                $lesson->setUnit($unit);
            }
        }
        $this->em->flush();
        return $lesson;
    }

    public function deleteLesson(int $id): bool
    {
        $lesson = $this->getLesson($id);
        if ($lesson) {
            $this->em->remove($lesson);
            $this->em->flush();
            return true;
        }
        return false;
    }
}