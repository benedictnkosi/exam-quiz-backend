<?php

namespace App\Command;

use App\Service\AccountingQuestionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-accounting-questions',
    description: 'Import sample accounting questions data',
)]
class ImportAccountingQuestionsCommand extends Command
{
    public function __construct(
        private AccountingQuestionService $accountingQuestionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Importing Accounting Questions');

        // Sample data from the user's JSON
        $sampleData = [
            [
                "topic" => "Statement of Comprehensive Income",
                "level" => "Level 1: Basics",
                "questions" => [
                    [
                        "id" => "sci_q1",
                        "type" => "tap-to-select",
                        "prompt" => "Is Rent Income an income or an expense?",
                        "options" => [
                            "Income",
                            "Expense"
                        ],
                        "answer" => "Income"
                    ],
                    [
                        "id" => "sci_q2",
                        "type" => "tap-to-select",
                        "prompt" => "Is Salaries and Wages an income or an expense?",
                        "options" => [
                            "Income",
                            "Expense"
                        ],
                        "answer" => "Expense"
                    ],
                    [
                        "id" => "sci_q3",
                        "type" => "tap-to-select",
                        "prompt" => "Where does Sales go on the income statement?",
                        "options" => [
                            "Income",
                            "Expense"
                        ],
                        "answer" => "Income"
                    ],
                    [
                        "id" => "sci_q4",
                        "type" => "categorise",
                        "prompt" => "Drag each item to the correct category.",
                        "categories" => [
                            "Income",
                            "Operating Expense"
                        ],
                        "items" => [
                            "Rent Income" => "Income",
                            "Packing Material" => "Operating Expense",
                            "Service Fees" => "Income",
                            "Audit Fees" => "Operating Expense"
                        ]
                    ],
                    [
                        "id" => "sci_q5",
                        "type" => "categorise",
                        "prompt" => "Categorise these accounts.",
                        "categories" => [
                            "Income",
                            "Operating Expense"
                        ],
                        "items" => [
                            "Commission Received" => "Income",
                            "Salaries" => "Operating Expense",
                            "Telephone" => "Operating Expense",
                            "Interest Income" => "Income"
                        ]
                    ],
                    [
                        "id" => "sci_q6",
                        "type" => "true-false",
                        "prompt" => "True or False: Depreciation is an income item.",
                        "answer" => "False",
                        "explanation" => "Depreciation is an operating expense, not income."
                    ],
                    [
                        "id" => "sci_q7",
                        "type" => "true-false",
                        "prompt" => "True or False: Income tax is subtracted after operating profit.",
                        "answer" => "True",
                        "explanation" => "Income tax is deducted at the end of the income statement."
                    ],
                    [
                        "id" => "sci_q8",
                        "type" => "drag-to-sort",
                        "prompt" => "Put these in the correct order as they appear on the income statement.",
                        "items" => [
                            "Sales",
                            "Cost of Sales",
                            "Gross Profit",
                            "Operating Expenses",
                            "Net Profit"
                        ],
                        "correct_order" => [
                            "Sales",
                            "Cost of Sales",
                            "Gross Profit",
                            "Operating Expenses",
                            "Net Profit"
                        ]
                    ],
                    [
                        "id" => "sci_q9",
                        "type" => "drag-to-sort",
                        "prompt" => "Arrange these in income statement order.",
                        "items" => [
                            "Other Income",
                            "Operating Profit",
                            "Income Tax",
                            "Net Profit After Tax"
                        ],
                        "correct_order" => [
                            "Other Income",
                            "Operating Profit",
                            "Income Tax",
                            "Net Profit After Tax"
                        ]
                    ],
                    [
                        "id" => "sci_q10",
                        "type" => "matching",
                        "prompt" => "Match each item to the correct section.",
                        "pairs" => [
                            "Sales" => "Income",
                            "Bad Debts" => "Operating Expense",
                            "Directors' Fees" => "Operating Expense",
                            "Interest Income" => "Other Income"
                        ]
                    ]
                ]
            ],
            [
                "topic" => "Statement of Comprehensive Income",
                "level" => "Level 2: Core Practice",
                "questions" => [
                    [
                        "id" => "sci_l2_q1",
                        "type" => "step-flow",
                        "prompt" => "Sales for the year were R500 000. The mark-up is 25% on cost. What is the Cost of Sales?",
                        "options" => [
                            "R375 000",
                            "R400 000",
                            "R450 000"
                        ],
                        "answer" => "R400 000",
                        "explanation" => "Cost = Sales / 1.25 = R400 000"
                    ],
                    [
                        "id" => "sci_l2_q2",
                        "type" => "step-flow",
                        "prompt" => "Sales: R750 000; Cost of Sales: R500 000. What is the Gross Profit?",
                        "options" => [
                            "R250 000",
                            "R300 000",
                            "R200 000"
                        ],
                        "answer" => "R250 000",
                        "explanation" => "Gross Profit = Sales - Cost of Sales"
                    ],
                    [
                        "id" => "sci_l2_q3",
                        "type" => "step-flow",
                        "prompt" => "Gross Profit is R300 000. Operating Expenses total R150 000. What is the Operating Profit?",
                        "options" => [
                            "R150 000",
                            "R450 000",
                            "R600 000"
                        ],
                        "answer" => "R150 000",
                        "explanation" => "Operating Profit = Gross Profit - Operating Expenses"
                    ],
                    [
                        "id" => "sci_l2_q4",
                        "type" => "step-flow",
                        "prompt" => "Operating Profit: R120 000. Interest Income: R5 000. What is Net Profit before Tax?",
                        "options" => [
                            "R125 000",
                            "R115 000",
                            "R120 000"
                        ],
                        "answer" => "R125 000",
                        "explanation" => "Net Profit before Tax = Operating Profit + Other Income"
                    ],
                    [
                        "id" => "sci_l2_q5",
                        "type" => "step-flow",
                        "prompt" => "Net Profit before Tax: R200 000. Income Tax Rate: 28%. What is the Net Profit after Tax?",
                        "options" => [
                            "R144 000",
                            "R160 000",
                            "R180 000"
                        ],
                        "answer" => "R144 000",
                        "explanation" => "Net Profit after Tax = R200 000 - (28% of R200 000)"
                    ],
                    [
                        "id" => "sci_l2_q6",
                        "type" => "tap-to-select",
                        "prompt" => "Which of these is NOT part of Operating Expenses?",
                        "options" => [
                            "Salaries",
                            "Rent Income",
                            "Stationery"
                        ],
                        "answer" => "Rent Income"
                    ],
                    [
                        "id" => "sci_l2_q7",
                        "type" => "tap-to-select",
                        "prompt" => "Which section does Interest Income belong to?",
                        "options" => [
                            "Operating Expenses",
                            "Other Income",
                            "Cost of Sales"
                        ],
                        "answer" => "Other Income"
                    ],
                    [
                        "id" => "sci_l2_q8",
                        "type" => "multi-step",
                        "context" => "Given: Sales = R900 000; Cost of Sales = R600 000; Rent Income = R50 000; Salaries = R180 000; Insurance = R20 000; Income Tax Rate = 28%",
                        "steps" => [
                            [
                                "prompt" => "What is the Gross Profit?",
                                "options" => [
                                    "R300 000",
                                    "R250 000",
                                    "R400 000"
                                ],
                                "answer" => "R300 000"
                            ],
                            [
                                "prompt" => "What is the Total Operating Expenses?",
                                "options" => [
                                    "R200 000",
                                    "R180 000",
                                    "R250 000"
                                ],
                                "answer" => "R200 000"
                            ],
                            [
                                "prompt" => "What is the Net Profit before Tax?",
                                "options" => [
                                    "R150 000",
                                    "R100 000",
                                    "R130 000"
                                ],
                                "answer" => "R150 000"
                            ],
                            [
                                "prompt" => "What is the Net Profit after Tax?",
                                "options" => [
                                    "R108 000",
                                    "R110 000",
                                    "R115 000"
                                ],
                                "answer" => "R108 000"
                            ]
                        ]
                    ],
                    [
                        "id" => "sci_l2_q9",
                        "type" => "drag-to-sort",
                        "prompt" => "Arrange these in the correct income statement flow.",
                        "items" => [
                            "Sales",
                            "Cost of Sales",
                            "Gross Profit",
                            "Operating Expenses",
                            "Other Income",
                            "Net Profit before Tax",
                            "Income Tax",
                            "Net Profit after Tax"
                        ],
                        "correct_order" => [
                            "Sales",
                            "Cost of Sales",
                            "Gross Profit",
                            "Operating Expenses",
                            "Other Income",
                            "Net Profit before Tax",
                            "Income Tax",
                            "Net Profit after Tax"
                        ]
                    ],
                    [
                        "id" => "sci_l2_q10",
                        "type" => "tap-to-select",
                        "prompt" => "Which of the following BEST represents Net Profit?",
                        "options" => [
                            "Total income minus operating expenses only",
                            "Gross profit minus cost of sales",
                            "All income minus all expenses, including tax"
                        ],
                        "answer" => "All income minus all expenses, including tax"
                    ]
                ]
            ]
        ];

        try {
            $io->section('Importing sample data...');
            
            $result = $this->accountingQuestionService->importFromJson($sampleData);
            
            $io->success([
                "Import completed successfully!",
                "Imported: {$result['imported']} questions"
            ]);

            if (!empty($result['errors'])) {
                $io->warning('Some errors occurred during import:');
                foreach ($result['errors'] as $error) {
                    $io->text("- $error");
                }
            }

            $io->section('Available API endpoints:');
            $io->listing([
                'GET /api/accounting-questions - List all questions',
                'GET /api/accounting-questions/topics - Get available topics',
                'GET /api/accounting-questions/levels - Get available levels',
                'GET /api/accounting-questions/types - Get available question types',
                'GET /api/accounting-questions/topic/{topic} - Get questions by topic',
                'GET /api/accounting-questions/level/{level} - Get questions by level',
                'GET /api/accounting-questions/topic/{topic}/level/{level} - Get questions by topic and level',
                'GET /api/accounting-questions/type/{type} - Get questions by type',
                'GET /api/accounting-questions/{questionId} - Get specific question',
                'POST /api/accounting-questions/import - Import questions from JSON',
                'PUT /api/accounting-questions/{questionId} - Update question',
                'DELETE /api/accounting-questions/{questionId} - Delete question'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error([
                'Import failed!',
                $e->getMessage()
            ]);
            
            return Command::FAILURE;
        }
    }
} 