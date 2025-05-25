<?php

namespace App\Tests\Command;

use App\Command\CreateQuestionsCommand;
use PHPUnit\Framework\TestCase;

class CreateQuestionsCommandTest extends TestCase
{
    private CreateQuestionsCommand $command;

    protected function setUp(): void
    {
        $this->command = new CreateQuestionsCommand(
            $this->createMock(\App\Repository\ExamPaperRepository::class),
            $this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class),
            $this->createMock(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class)
        );
    }

    public function testCleanQuestionText(): void
    {
        $testText = "QUESTION 4 (Start on a new page.)
4.1 The equation below represents a reaction taking place in a sealed container. X represents an unknown reagent.

4.2 Refer to the following organic compounds:

Which ONE of the compounds will be more likely to undergo an oxidation (combustion) reaction? Write down A or B.";

        $expectedText = "(Start on a new page.)";

        $result = $this->command->cleanQuestionText($testText, '4');

        $this->assertEquals($expectedText, $result);
    }

    public function testCleanQuestionTextWithParentNumber(): void
    {
        $testText = "4.1 The equation below represents a reaction taking place in a sealed container. X represents an unknown reagent.

4.2 Refer to the following organic compounds:

Which ONE of the compounds will be more likely to undergo an oxidation (combustion) reaction? Write down A or B.";

        $expectedText = "The equation below represents a reaction taking place in a sealed container. X represents an unknown reagent.";

        $result = $this->command->cleanQuestionText($testText, '4.1');

        $this->assertEquals($expectedText, $result);
    }

    public function testCleanQuestionTextWithNoQuestionNumber(): void
    {
        $testText = "The equation below represents a reaction taking place in a sealed container. X represents an unknown reagent.";

        $result = $this->command->cleanQuestionText($testText, '4.1');

        $this->assertEquals($testText, $result);
    }

    public function testCleanQuestionTextWithMultipleQuestionReferences(): void
    {
        $testText = "QUESTION 4 (Start on a new page.)
4.1 The equation below represents a reaction taking place in a sealed container. X represents an unknown reagent.

4.2 Refer to the following organic compounds:

Which ONE of the compounds will be more likely to undergo an oxidation (combustion) reaction? Write down A or B.

4.3 Some other text here.";

        $expectedText = "(Start on a new page.)";

        $result = $this->command->cleanQuestionText($testText, '4');

        $this->assertEquals($expectedText, $result);
    }
}