<?php

namespace App\Tests\Service;

use App\Service\StepGenerationService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class StepGenerationServiceTest extends TestCase
{
    private StepGenerationService $service;
    private $httpClient;
    private $params;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);

        $this->params->method('get')
            ->willReturn('test_api_key');

        $this->service = new StepGenerationService(
            $this->httpClient,
            $this->params
        );
    }

    public function testCleanResponseContent(): void
    {
        $method = new \ReflectionMethod(StepGenerationService::class, 'cleanResponseContent');
        $method->setAccessible(true);

        $testCases = [
            [
                'input' => '```json
{
  "id": "sqrt_eq_01",
  "grade": 12,
  "topic": "Algebra - Solving Radical Equations",
  "question": "\\sqrt{2(1-x)} = x - 1",
  "steps": []
}
```',
                'expected' => '{
  "id": "sqrt_eq_01",
  "grade": 12,
  "topic": "Algebra - Solving Radical Equations",
  "question": "\\sqrt{2(1-x)} = x - 1",
  "steps": []
}'
            ],
            [
                'input' => 'Some text before
{
  "id": "test",
  "steps": []
}
Some text after',
                'expected' => '{
  "id": "test",
  "steps": []
}'
            ],
            [
                'input' => '{
  "id": "test",
  "steps": []
}',
                'expected' => '{
  "id": "test",
  "steps": []
}'
            ]
        ];

        foreach ($testCases as $index => $testCase) {
            $result = $method->invoke($this->service, $testCase['input']);
            $this->assertEquals(
                $testCase['expected'],
                $result,
                "Test case $index failed"
            );
        }
    }

    public function testFormatLatex(): void
    {
        $method = new \ReflectionMethod(StepGenerationService::class, 'formatLatex');
        $method->setAccessible(true);

        $testCases = [
            [
                'input' => '\\text{Simplify } \\sin(30^{\\circ})',
                'expected' => '$\\text{Simplify } \\sin(30^{\\circ})$'
            ],
            [
                'input' => 'x \\geq 1',
                'expected' => '$x \\geq 1$'
            ],
            [
                'input' => '\\text{The expression under the square root must be } \\geq 0.',
                'expected' => '$\\text{The expression under the square root must be } \\geq 0.$'
            ]
        ];

        foreach ($testCases as $index => $testCase) {
            $result = $method->invoke($this->service, $testCase['input']);
            $this->assertEquals(
                $testCase['expected'],
                $result,
                "Test case $index failed"
            );
        }
    }

    public function testGenerateStepsWithSampleResponse(): void
    {
        $sampleResponse = '{
  "id": "sqrt_eq_01",
  "grade": 12,
  "topic": "Algebra - Solving Radical Equations",
  "question": "\\\\sqrt{2(1-x)} = x - 1",
  "steps": [
    {
      "step_number": 1,
      "type": "value",
      "prompt": "\\\\text{Identify the domain restriction on } x \\\\text{ so the equation is defined}",
      "expression": null,
      "options": [
        "x \\\\geq 1",
        "x \\\\leq 1",
        "x > 1",
        "x < 1"
      ],
      "answer": "x \\\\leq 1",
      "hint": "\\\\text{The expression under the square root must be } \\\\geq 0.",
      "teach": "\\\\text{For } \\\\sqrt{2(1-x)} \\\\text{ to be real, } 2(1-x) \\\\geq 0 \\\\Rightarrow x \\\\leq 1.",
      "final_expression": null
    }
  ]
}';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => $sampleResponse
                        ]
                    ]
                ]
            ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.openai.com/v1/chat/completions',
                $this->callback(function ($options) {
                    return isset($options['headers']['Authorization']) &&
                        isset($options['headers']['Content-Type']) &&
                        isset($options['json']['model']) &&
                        isset($options['json']['messages']);
                })
            )
            ->willReturn($response);

        $result = $this->service->generateSteps($this->createMockQuestion());

        $this->assertIsArray($result);
        $this->assertEquals('sqrt_eq_01', $result['id']);
        $this->assertEquals(12, $result['grade']);
        $this->assertEquals('Algebra - Solving Radical Equations', $result['topic']);
        $this->assertIsArray($result['steps']);
        $this->assertCount(1, $result['steps']);

        $step = $result['steps'][0];
        $this->assertEquals(1, $step['step_number']);
        $this->assertEquals('value', $step['type']);
        $this->assertNull($step['expression']);
        $this->assertCount(4, $step['options']);
        $this->assertEquals('x \\leq 1', $step['answer']);
    }

    public function testProcessSampleJsonDirectly(): void
    {
        $sampleJson = '{
  "id": "sqrt_eq_01",
  "grade": 12,
  "topic": "Algebra - Solving Radical Equations",
  "question": "\\\\sqrt{2(1-x)} = x - 1",
  "steps": [
    {
      "step_number": 1,
      "type": "value",
      "prompt": "\\\\text{Identify the domain restriction on } x \\\\text{ so the equation is defined}",
      "expression": null,
      "options": [
        "x \\\\geq 1",
        "x \\\\leq 1",
        "x > 1"
      ],
      "answer": "x \\\\leq 1",
      "hint": "\\\\text{The expression under the square root must be } \\\\geq 0.",
      "teach": "\\\\text{For } \\\\sqrt{2(1-x)} \\\\text{ to be real, } 2(1-x) \\\\geq 0 \\\\Rightarrow x \\\\leq 1.",
      "final_expression": null
    }
  ]
}';

        // Clean the response (simulate AI output)
        $cleanMethod = new \ReflectionMethod(StepGenerationService::class, 'cleanResponseContent');
        $cleanMethod->setAccessible(true);
        $cleaned = $cleanMethod->invoke($this->service, $sampleJson);

        // Decode JSON
        $steps = json_decode($cleaned, true);
        $this->assertIsArray($steps);
        $this->assertEquals('sqrt_eq_01', $steps['id']);
        $this->assertEquals(12, $steps['grade']);
        $this->assertEquals('Algebra - Solving Radical Equations', $steps['topic']);
        $this->assertIsArray($steps['steps']);
        $this->assertCount(1, $steps['steps']);

        // Validate required fields
        $validateMethod = new \ReflectionMethod(StepGenerationService::class, 'validateSteps');
        $validateMethod->setAccessible(true);
        $validateMethod->invoke($this->service, $steps);

        // Format LaTeX
        $formatMethod = new \ReflectionMethod(StepGenerationService::class, 'formatStepsLatex');
        $formatMethod->setAccessible(true);
        $formatted = $formatMethod->invoke($this->service, $steps);

        $this->assertStringContainsString('$', $formatted['question']);
        $this->assertStringContainsString('$', $formatted['steps'][0]['prompt']);
        $this->assertStringContainsString('$', $formatted['steps'][0]['hint']);
        $this->assertStringContainsString('$', $formatted['steps'][0]['teach']);

        // Verify exactly 3 options
        $this->assertCount(3, $formatted['steps'][0]['options']);
    }

    private function createMockQuestion()
    {
        $question = $this->createMock(\App\Entity\Question::class);
        $subject = $this->createMock(\App\Entity\Subject::class);
        $grade = $this->createMock(\App\Entity\Grade::class);

        $grade->method('getNumber')
            ->willReturn(12);

        $subject->method('getGrade')
            ->willReturn($grade);

        $question->method('getSubject')
            ->willReturn($subject);

        $question->method('getQuestion')
            ->willReturn('\\sqrt{2(1-x)} = x - 1');

        $question->method('getAnswer')
            ->willReturn('x = 0');

        $question->method('getExplanation')
            ->willReturn('Square both sides and solve the resulting quadratic equation.');

        $question->method('getTopic')
            ->willReturn('Algebra - Solving Radical Equations');

        return $question;
    }
}