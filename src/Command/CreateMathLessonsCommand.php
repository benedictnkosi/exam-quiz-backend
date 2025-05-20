<?php

namespace App\Command;

use App\Dto\MathStepDto;
use App\Entity\MathLesson;
use App\Entity\Question;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:create-math-lessons',
    description: 'Creates sample math lessons for testing'
)]
class CreateMathLessonsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionRepository $questionRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Grade 8 Algebra
        $this->createAlgebraLesson(
            grade: 8,
            topic: 'Algebra',
            subTopic: 'Linear Equations',
            question: 'Solve for x: 2x + 5 = 13',
            steps: [
                new MathStepDto(
                    stepNumber: 1,
                    prompt: 'First, subtract 5 from both sides',
                    expression: '2x + 5 - 5 = 13 - 5',
                    options: ['2x = 8', '2x = 13', '2x = 18'],
                    answer: '2x = 8',
                    hint: 'Remember to perform the same operation on both sides of the equation',
                    teach: 'When solving linear equations, we need to isolate the variable. The first step is to remove the constant term by subtracting it from both sides.',
                    finalExpression: '2x = 8'
                ),
                new MathStepDto(
                    stepNumber: 2,
                    prompt: 'Now, divide both sides by 2',
                    expression: '2x/2 = 8/2',
                    options: ['x = 4', 'x = 8', 'x = 16'],
                    answer: 'x = 4',
                    hint: 'Dividing both sides by the coefficient of x will isolate x',
                    teach: 'After removing the constant term, we need to isolate x by dividing both sides by its coefficient.',
                    finalExpression: 'x = 4'
                )
            ]
        );

        // Grade 9 Geometry
        $this->createGeometryLesson(
            grade: 9,
            topic: 'Geometry',
            subTopic: 'Pythagorean Theorem',
            question: 'Find the length of the hypotenuse in a right triangle with legs of 3 and 4 units',
            steps: [
                new MathStepDto(
                    stepNumber: 1,
                    prompt: 'Write the Pythagorean theorem formula',
                    expression: 'a² + b² = c²',
                    options: ['a² + b² = c²', 'a + b = c', 'a² - b² = c²'],
                    answer: 'a² + b² = c²',
                    hint: 'The Pythagorean theorem states that in a right triangle, the square of the hypotenuse equals the sum of squares of the other two sides',
                    teach: 'The Pythagorean theorem is a fundamental relationship in right triangles where c represents the hypotenuse, and a and b represent the other two sides.',
                    finalExpression: 'a² + b² = c²'
                ),
                new MathStepDto(
                    stepNumber: 2,
                    prompt: 'Substitute the known values',
                    expression: '3² + 4² = c²',
                    options: ['9 + 16 = c²', '6 + 8 = c²', '3 + 4 = c²'],
                    answer: '9 + 16 = c²',
                    hint: 'Square each leg length before adding',
                    teach: 'We substitute the known values for a and b, making sure to square each value before adding them together.',
                    finalExpression: '9 + 16 = c²'
                ),
                new MathStepDto(
                    stepNumber: 3,
                    prompt: 'Calculate the sum',
                    expression: '25 = c²',
                    options: ['25 = c²', '15 = c²', '35 = c²'],
                    answer: '25 = c²',
                    hint: 'Add the squared values: 9 + 16 = 25',
                    teach: 'After squaring the values, we add them together to get the sum of the squares.',
                    finalExpression: '25 = c²'
                ),
                new MathStepDto(
                    stepNumber: 4,
                    prompt: 'Take the square root of both sides',
                    expression: '√25 = c',
                    options: ['5 = c', '25 = c', '12.5 = c'],
                    answer: '5 = c',
                    hint: 'The square root of 25 is 5',
                    teach: 'To find the length of the hypotenuse, we take the square root of both sides of the equation.',
                    finalExpression: 'c = 5'
                )
            ]
        );

        // Grade 10 Trigonometry
        $this->createTrigonometryLesson(
            grade: 10,
            topic: 'Trigonometry',
            subTopic: 'Sine and Cosine',
            question: 'Find the value of sin(30°)',
            steps: [
                new MathStepDto(
                    stepNumber: 1,
                    prompt: 'Recall the special angle value for 30°',
                    expression: 'sin(30°) = 1/2',
                    options: ['1/2', '√3/2', '1'],
                    answer: '1/2',
                    hint: 'For 30° angles, the sine value is always 1/2',
                    teach: 'The sine of 30 degrees is a special angle value that we should memorize. It equals 1/2.',
                    finalExpression: 'sin(30°) = 1/2'
                )
            ]
        );

        $this->entityManager->flush();
        $output->writeln('Successfully created sample math lessons');

        return Command::SUCCESS;
    }

    private function createAlgebraLesson(int $grade, string $topic, string $subTopic, string $question, array $steps): void
    {
        $lesson = new MathLesson();
        $lesson->setId(Uuid::v4()->toRfc4122());
        $lesson->setGrade($grade);
        $lesson->setTopic($topic);
        $lesson->setSubTopic($subTopic);
        $lesson->setSteps($steps);

        // Create and link a question
        $questionEntity = new Question();
        $questionEntity->setQuestion($question);
        $questionEntity->setType('multiple_choice');
        $questionEntity->setAnswer('x = 4');
        $questionEntity->setOptions([
            'option1' => 'x = 4',
            'option2' => 'x = 8',
            'option3' => 'x = 16',
            'option4' => 'x = 2'
        ]);
        $questionEntity->setYear(2024);
        $questionEntity->setTerm(1);
        $questionEntity->setStatus('approved');
        $questionEntity->setActive(true);

        $this->entityManager->persist($questionEntity);
        $lesson->setQuestion($questionEntity);
        $this->entityManager->persist($lesson);
    }

    private function createGeometryLesson(int $grade, string $topic, string $subTopic, string $question, array $steps): void
    {
        $lesson = new MathLesson();
        $lesson->setId(Uuid::v4()->toRfc4122());
        $lesson->setGrade($grade);
        $lesson->setTopic($topic);
        $lesson->setSubTopic($subTopic);
        $lesson->setSteps($steps);

        // Create and link a question
        $questionEntity = new Question();
        $questionEntity->setQuestion($question);
        $questionEntity->setType('multiple_choice');
        $questionEntity->setAnswer('5 units');
        $questionEntity->setOptions([
            'option1' => '5 units',
            'option2' => '7 units',
            'option3' => '12 units',
            'option4' => '25 units'
        ]);
        $questionEntity->setYear(2024);
        $questionEntity->setTerm(1);
        $questionEntity->setStatus('approved');
        $questionEntity->setActive(true);

        $this->entityManager->persist($questionEntity);
        $lesson->setQuestion($questionEntity);
        $this->entityManager->persist($lesson);
    }

    private function createTrigonometryLesson(int $grade, string $topic, string $subTopic, string $question, array $steps): void
    {
        $lesson = new MathLesson();
        $lesson->setId(Uuid::v4()->toRfc4122());
        $lesson->setGrade($grade);
        $lesson->setTopic($topic);
        $lesson->setSubTopic($subTopic);
        $lesson->setSteps($steps);

        // Create and link a question
        $questionEntity = new Question();
        $questionEntity->setQuestion($question);
        $questionEntity->setType('multiple_choice');
        $questionEntity->setAnswer('1/2');
        $questionEntity->setOptions([
            'option1' => '1/2',
            'option2' => '√3/2',
            'option3' => '1',
            'option4' => '0'
        ]);
        $questionEntity->setYear(2024);
        $questionEntity->setTerm(1);
        $questionEntity->setStatus('approved');
        $questionEntity->setActive(true);

        $this->entityManager->persist($questionEntity);
        $lesson->setQuestion($questionEntity);
        $this->entityManager->persist($lesson);
    }
}