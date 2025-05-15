<?php

namespace App\Tests\Command;

use App\Command\CreateMathsQuestionsCommand;
use PHPUnit\Framework\TestCase;
use App\Repository\ExamPaperRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateMathsQuestionsCommandTest extends TestCase
{
    private CreateMathsQuestionsCommand $command;

    protected function setUp(): void
    {
        $this->command = new CreateMathsQuestionsCommand(
            $this->createMock(\App\Repository\ExamPaperRepository::class),
            $this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class),
            $this->createMock(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class)
        );
    }

    public function testParseWrongAnswers(): void
    {
        $input = 'first_option: $x=4 \text{ or } x=-3$ second_option: $x=6 \text{ or } x=-2$ third_option: $x=2 \text{ or } x=-6$';
        $correctAnswer = '$x=3 \text{ or } x=-4$';

        echo "\nTest Input:\n" . $input . "\n";
        echo "Correct Answer:\n" . $correctAnswer . "\n";

        $result = $this->command->parseWrongAnswers($input, $correctAnswer);

        echo "\nProcessed Options:\n";
        foreach ($result as $index => $option) {
            echo "Option " . ($index + 1) . ": " . $option . "\n";
        }

        $this->assertIsArray($result);
        $this->assertCount(4, $result);

        // Test options
        $this->assertEquals('$x=4 \\text{ or } x=-3$', $result[0]);
        $this->assertEquals('$x=6 \\text{ or } x=-2$', $result[1]);
        $this->assertEquals('$x=2 \\text{ or } x=-6$', $result[2]);
        $this->assertEquals('$x=3 \\text{ or } x=-4$', $result[3]);
    }

    public function testParseWrongAnswersWithMultiLineInput(): void
    {
        $input = "first_option: \$x=4 \\text{ or } x=-3\$\nsecond_option: \$x=6 \\text{ or } x=-2\$\nthird_option: \$x=2 \\text{ or } x=-6\$";
        $correctAnswer = '$x=3 \text{ or } x=-4$';

        echo "\nTest Input (Multi-line):\n" . $input . "\n";
        echo "Correct Answer:\n" . $correctAnswer . "\n";

        $result = $this->command->parseWrongAnswers($input, $correctAnswer);

        echo "\nProcessed Options:\n";
        foreach ($result as $index => $option) {
            echo "Option " . ($index + 1) . ": " . $option . "\n";
        }

        $this->assertIsArray($result);
        $this->assertCount(4, $result);

        // Test options
        $this->assertEquals('$x=4 \\text{ or } x=-3$', $result[0]);
        $this->assertEquals('$x=6 \\text{ or } x=-2$', $result[1]);
        $this->assertEquals('$x=2 \\text{ or } x=-6$', $result[2]);
        $this->assertEquals('$x=3 \\text{ or } x=-4$', $result[3]);
    }

    public function testParseWrongAnswersWithoutDollarSigns(): void
    {
        $input = 'first_option: T_n = 3(n-1)^2 second_option: T_n = 2(3)^{n-1} third_option: T_n = 3^n + 2n';
        $correctAnswer = 'T_n = 3^n';

        echo "\nTest Input (Without Dollar Signs):\n" . $input . "\n";
        echo "Correct Answer:\n" . $correctAnswer . "\n";

        $result = $this->command->parseWrongAnswers($input, $correctAnswer);

        echo "\nProcessed Options:\n";
        foreach ($result as $index => $option) {
            echo "Option " . ($index + 1) . ": " . $option . "\n";
        }

        $this->assertIsArray($result);
        $this->assertCount(4, $result);

        // Test options
        $this->assertEquals('$T_n = 3(n-1)^2$', $result[0]);
        $this->assertEquals('$T_n = 2(3)^{n-1}$', $result[1]);
        $this->assertEquals('$T_n = 3^n + 2n$', $result[2]);
        $this->assertEquals('$T_n = 3^n$', $result[3]);
    }

    public function testParseWrongAnswersSimpleOptions(): void
    {
        $input = 'first_option: x = 3 second_option: x = 5 third_option: x = 2';
        $correctAnswer = 'x = 4';

        echo "\nTest Input (Simple Options):\n" . $input . "\n";
        echo "Correct Answer:\n" . $correctAnswer . "\n";

        $result = $this->command->parseWrongAnswers($input, $correctAnswer);

        echo "\nProcessed Options:\n";
        foreach ($result as $index => $option) {
            echo "Option " . ($index + 1) . ": " . $option . "\n";
        }

        $this->assertIsArray($result);
        $this->assertCount(4, $result);

        // Test options
        $this->assertEquals('$x = 3$', $result[0]);
        $this->assertEquals('$x = 5$', $result[1]);
        $this->assertEquals('$x = 2$', $result[2]);
        $this->assertEquals('$x = 4$', $result[3]);
    }

    public function testParseWrongAnswersWithFractions(): void
    {
        $input = 'first_option: "$\frac{1}{2}$" second_option: "$\frac{3}{4}$" third_option: "$\frac{1}{3}$"';
        $correctAnswer = '$\frac{1}{4}$';

        echo "\nTest Input (With Fractions):\n" . $input . "\n";
        echo "Correct Answer:\n" . $correctAnswer . "\n";

        $result = $this->command->parseWrongAnswers($input, $correctAnswer);

        echo "\nProcessed Options:\n";
        foreach ($result as $index => $option) {
            echo "Option " . ($index + 1) . ": " . $option . "\n";
        }

        $this->assertIsArray($result);
        $this->assertCount(4, $result);

        // Test options
        $this->assertEquals('$\frac{1}{2}$', $result[0]);
        $this->assertEquals('$\frac{3}{4}$', $result[1]);
        $this->assertEquals('$\frac{1}{3}$', $result[2]);
        $this->assertEquals('$\frac{1}{4}$', $result[3]);
    }

    public function testParseWrongAnswersWithSubscripts(): void
    {
        $command = new CreateMathsQuestionsCommand(
            $this->createMock(ExamPaperRepository::class),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(EntityManagerInterface::class)
        );

        $input = 'first_option: "$T_{91} = 462$" second_option: "$T_{91} = 443$" third_option: "$T_{91} = 470$"';
        $correctAnswer = '$T_{91} = 455$';

        $result = $command->parseWrongAnswers($input, $correctAnswer);

        $this->assertIsArray($result);
        $this->assertCount(4, $result);

        // Test options
        $this->assertEquals('$T_{91} = 462$', $result[0]);
        $this->assertEquals('$T_{91} = 443$', $result[1]);
        $this->assertEquals('$T_{91} = 470$', $result[2]);
        $this->assertEquals('$T_{91} = 455$', $result[3]);
    }

    public function testParseWrongAnswersWithNewlines(): void
    {
        $input = "first_option: \$r = 7,8\\% \$\nsecond_option: \$r = 9,1\\% \$\nthird_option: \$r = 6,5\\% \$";
        $correctAnswer = "\$r = 8,7\\%\$";

        $command = new CreateMathsQuestionsCommand(
            $this->createMock(ExamPaperRepository::class),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(EntityManagerInterface::class)
        );

        $result = $command->parseWrongAnswers($input, $correctAnswer);

        $this->assertCount(4, $result, 'Should return 4 options (3 wrong + 1 correct)');
        $this->assertEquals('$r = 7,8\%$', $result[0], 'First option should be correctly formatted');
        $this->assertEquals('$r = 9,1\%$', $result[1], 'Second option should be correctly formatted');
        $this->assertEquals('$r = 6,5\%$', $result[2], 'Third option should be correctly formatted');
        $this->assertEquals('$r = 8,7\%$', $result[3], 'Correct answer should be correctly formatted');
    }

    public function testParseWrongAnswersWithNewlinesAndPercentages(): void
    {
        $input = "first_option: \$r = 7,5\\%\$\\newline second_option: \$r = 9,3\\%\$\\newline third_option: \$r = 6,8\\%\$";
        $correctAnswer = "\$r = 8,7\\%\$";

        $command = new CreateMathsQuestionsCommand(
            $this->createMock(ExamPaperRepository::class),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(EntityManagerInterface::class)
        );

        $result = $command->parseWrongAnswers($input, $correctAnswer);

        $this->assertCount(4, $result, 'Should return 4 options (3 wrong + 1 correct)');
        $this->assertEquals('$r = 7,5\%$', $result[0], 'First option should be correctly formatted');
        $this->assertEquals('$r = 9,3\%$', $result[1], 'Second option should be correctly formatted');
        $this->assertEquals('$r = 6,8\%$', $result[2], 'Third option should be correctly formatted');
        $this->assertEquals('$r = 8,7\%$', $result[3], 'Correct answer should be correctly formatted');
    }

    public function testFormatLatexWithCalculations(): void
    {
        $input = "\\sqrt{2x+1} = x-1 \newline 2x+1 = (x-1)^2 \newline 2x+1 = x^2 - 2x + 1 \newline 0 = x^2 - 4x \newline x(x-4) = 0 \newline x = 0 \\text{ or } x = 4 \newline \\text{Since } x-1 \\geq 0, x \\neq 0 \newline \\text{Therefore, } x = 4";

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('formatLatex');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $input);

        $expected = '$\\sqrt{2x+1} = x-1 \newline 2x+1 = (x-1)^2 \newline 2x+1 = x^2 - 2x + 1 \newline 0 = x^2 - 4x \newline x(x-4) = 0 \newline x = 0 \\text{ or } x = 4 \newline \\text{Since } x-1 \\geq 0, x \\neq 0 \newline \\text{Therefore, } x = 4$';

        $this->assertEquals($expected, $result, 'LaTeX calculations should be properly formatted with dollar signs and preserved newlines');
    }

    public function testFormatLatexWithQuadraticEquation(): void
    {
        $input = '$x^2 + x - 12 = 0 \\newline (x-3)(x+4) = 0 \\newline x = 3 \\text{ or } x = -4$';

        // Create a mock OutputInterface
        $output = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);
        $output->expects($this->any())
            ->method('writeln')
            ->willReturnCallback(function ($message) {
                echo $message . "\n";
            });

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('formatLatex');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $input, $output);

        $expected = '$x^2 + x - 12 = 0 \newline (x-3)(x+4) = 0 \newline x = 3 \\text{ or } x = -4$';

        $this->assertEquals($expected, $result, 'LaTeX quadratic equation should be properly formatted with dollar signs and preserved newlines');
    }
}