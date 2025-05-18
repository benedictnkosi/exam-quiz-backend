<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OpenAIService
{
    private HttpClientInterface $client;
    private string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct(string $openaiApiKey)
    {
        $this->client = HttpClient::create();
        $this->apiKey = $openaiApiKey;
    }

    public function generateLecture(string $subjectName, string $topic, string $subTopic): string
    {
        $prompt = "You are an energetic and relatable lecturer creating content specifically for South African high school students (ages 14-17). Your mission is to make the subject {$subjectName} feel relevant, exciting, and easy to grasp, even for students who might find it boring or difficult. You understand the unique South African context they live in.

Your tone should be playful, vibrant, and approachable. Imagine you're chatting with them like a cool older cousin or a popular local content creator – using language they understand, throwing in relatable jokes, and showing genuine passion for the subject.

Generate a lecture script explaining \"{$topic}: {$subTopic}\". The script should flow naturally and cover the necessary concepts while fully integrating the specified style and local relevance throughout, without using explicit headings or numbered sections to structure the content.

Here's what the script needs to achieve in its flow:

Begin with an immediate, engaging hook: Start with something relatable to their South African reality – a shared frustration, a local news item, or a popular local trend – that unexpectedly connects to the topic.

Simply introduce the topic: State what the lecture is about in a way that shows them why it's cool or important, not just something they have to learn. Clearly link its relevance to understanding the South African context.

Break down the key concepts: Explain the main ideas using analogies that resonate specifically with their everyday South African experience. Build clear bridges between the complex ideas and local examples.

Weave in rhetorical questions: Scatter questions throughout the script that encourage them to think about the concepts or relate them to their own lives and observations in South Africa.

Explicitly show relevance to SA: Throughout the explanation and towards the end, tell or show them why understanding this topic matters in the broader South African context.

Conclude powerfully: Summarize the main ideas memorably, perhaps revisiting the initial hook or analogy. Leave them with a final thought or a challenge that feels relevant to their future understanding.

At the end of the script, provide a search text for Google to find the best image to go with the lecture. Format it as: [Image Search: your search text here]";

        try {
            $response = $this->client->request('POST', $this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful assistant that generates engaging lecture content for South African high school students.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 2000
                ]
            ]);

            $data = json_decode($response->getContent(), true);

            return $data['choices'][0]['message']['content'] ?? 'Failed to generate lecture content.';
        } catch (\Exception $e) {
            error_log('OpenAI API Error: ' . $e->getMessage());
            return 'Failed to generate lecture content due to an API error.';
        }
    }

    public function generateChapterContent(
        string $theme,
        string $goal,
        string $chapterName,
        string $outline,
        string $readingLevel,
        string $characterInfo,
        int $wordCountLimit,
        array $pastSummaries = [],
        array $futurePlot = [],
        ?string $previousChapterContent = null
    ): array {
        $minWords = $wordCountLimit - 50;
        $maxWords = $wordCountLimit + 50;

        // Format past summaries
        $pastSummariesText = "";
        if (!empty($pastSummaries)) {
            $pastSummariesText = "\nPast Chapter Summaries:\n";
            foreach ($pastSummaries as $summary) {
                $pastSummariesText .= "Chapter {$summary['chapter_number']}: {$summary['summary']}\n";
            }
        }

        // Format future plot
        $futurePlotText = "";
        if (!empty($futurePlot)) {
            $futurePlotText = "\nUpcoming Chapters:\n";
            foreach ($futurePlot as $plot) {
                $futurePlotText .= "Chapter: {$plot['chapter_name']}\nGoal: {$plot['goal']}\n";
            }
        }

        // Add previous chapter content if available
        $previousChapterText = "";
        if ($previousChapterContent) {
            $previousChapterText = "\nPrevious Chapter Content:\n{$previousChapterContent}\n";
        }

        $prompt = "You are a creative and engaging storyteller. Create a chapter for a story with the following details:

Theme: {$theme}
Goal: {$goal}
Chapter Name: {$chapterName}
Outline: {$outline}
Reading Level: {$readingLevel}
Word Count Limit: min {$minWords} max {$maxWords} words{$previousChapterText}{$pastSummariesText}{$futurePlotText}

Main Character:
{$characterInfo}

Please write a complete chapter that:
1. Follows the provided outline structure
2. Maintains consistency with the theme and goal
3. Creates engaging and vivid scenes
4. Develops the main character naturally, staying true to their personality and traits
5. Uses descriptive language that reflects the character's perspective
6. Includes dialogue that matches the character's voice and personality
7. Has a natural flow and pacing
8. Ends with a hook that encourages reading the next chapter
9. Uses vocabulary and sentence structure appropriate for {$readingLevel} reading level
10. Stays within the {$wordCountLimit} word limit
11. Uses emojis sparingly and appropriately to enhance emotional moments, key events, or character expressions. Do not overuse emojis - they should complement the story, not overwhelm it.
12. Maintains continuity with past chapters and sets up future plot developments naturally
13. If a previous chapter is provided, ensure smooth transition and continuity from its events and character development

The chapter should be well-structured and engaging, suitable for the target reading level. Focus on showing rather than telling, and use sensory details to bring the story to life. Adjust the complexity of language and concepts to match the {$readingLevel} reading level. Ensure the narrative voice and perspective align with the main character's age, personality, and experiences. Be concise and efficient with your word choice to stay within the word limit while maintaining the story's impact.

After writing the chapter, provide:
1. A concise summary (maximum 50 words) that captures the key events and emotional journey of the chapter
2. A quiz with 3 questions that test comprehension of the chapter's key events and themes. Each question should have 4 multiple-choice options, with only one correct answer.

Format your response as follows:

[CHAPTER]
[Your chapter content here]

[SUMMARY]
[Your 50-word summary here]

[QUIZ]
[
  {
    \"question\": \"First question here\",
    \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
    \"correct\": 0
  },
  {
    \"question\": \"Second question here\",
    \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
    \"correct\": 1
  },
  {
    \"question\": \"Third question here\",
    \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
    \"correct\": 2
  }
]";

        // Log the prompt for debugging
        error_log("AI Prompt for Chapter '{$chapterName}':\n" . $prompt);

        try {
            $response = $this->client->request('POST', $this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a creative and insightful storyteller. Your job is to write engaging and emotionally rich chapters for a serialized story targeted at specific reading levels. You must follow a provided theme, chapter goal, outline, and character profile. Adapt your tone, vocabulary, and sentence structure to match the reader\'s age and reading ability. Focus on showing rather than telling, create vivid scenes, and stay true to the voice and personality of the main character. Chapters should have a clear structure, natural pacing, and end with a compelling hook that keeps readers eager for the next part. Maintain continuity with past chapters and set up future plot developments naturally.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $content = $data['choices'][0]['message']['content'] ?? 'Failed to generate chapter content.';

            // Log token usage
            $promptTokens = $data['usage']['prompt_tokens'] ?? 0;
            $completionTokens = $data['usage']['completion_tokens'] ?? 0;
            $totalTokens = $data['usage']['total_tokens'] ?? 0;
            error_log("Token Usage for Chapter '{$chapterName}':\n" .
                "Prompt tokens: {$promptTokens}\n" .
                "Completion tokens: {$completionTokens}\n" .
                "Total tokens: {$totalTokens}");

            // Log the full AI response
            error_log("AI Response for Chapter '{$chapterName}':\n" . $content);

            // Parse the response to separate chapter, summary, and quiz
            preg_match('/\[CHAPTER\](.*?)\[SUMMARY\](.*?)\[QUIZ\](.*?)$/s', $content, $matches);

            $result = [
                'content' => trim($matches[1] ?? $content),
                'summary' => trim($matches[2] ?? 'Failed to generate summary.'),
                'quiz' => json_decode(trim($matches[3] ?? '[]'), true)
            ];

            // Log the parsed result
            error_log("Parsed Result for Chapter '{$chapterName}':\n" .
                "Content length: " . strlen($result['content']) . " characters\n" .
                "Summary: " . $result['summary'] . "\n" .
                "Quiz questions: " . count($result['quiz']));

            return $result;
        } catch (\Exception $e) {
            error_log('OpenAI API Error: ' . $e->getMessage());
            return [
                'content' => 'Failed to generate chapter content due to an API error.',
                'summary' => 'Failed to generate summary due to an API error.',
                'quiz' => []
            ];
        }
    }

    public function uploadFile(UploadedFile $file): array
    {
        try {
            $url = 'https://api.openai.com/v1/files';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey
            ]);

            $postData = [
                'purpose' => 'assistants',
                'file' => new \CURLFile(
                    $file->getPathname(),
                    $file->getMimeType(),
                    $file->getClientOriginalName()
                )
            ];

            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode !== 200) {
                throw new \Exception('OpenAI API returned status code ' . $httpCode . ': ' . $response);
            }

            curl_close($ch);

            return json_decode($response, true);
        } catch (\Exception $e) {
            throw new \Exception('Failed to upload file to OpenAI: ' . $e->getMessage());
        }
    }

    /**
     * Delete a file from OpenAI by its ID
     * 
     * @param string $fileId The ID of the file to delete
     * @return array The response from the OpenAI API
     * @throws \Exception If the deletion fails
     */
    public function deleteFile(string $fileId): array
    {
        try {
            $url = 'https://api.openai.com/v1/files/' . $fileId;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode !== 200) {
                throw new \Exception('OpenAI API returned status code ' . $httpCode . ': ' . $response);
            }

            curl_close($ch);

            return json_decode($response, true);
        } catch (\Exception $e) {
            throw new \Exception('Failed to delete file from OpenAI: ' . $e->getMessage());
        }
    }

    /**
     * Delete all files from OpenAI
     * 
     * @return array Array of responses from the OpenAI API for each deleted file
     * @throws \Exception If the deletion fails
     */
    public function deleteAllFiles(): array
    {
        try {
            $url = 'https://api.openai.com/v1/files';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode !== 200) {
                throw new \Exception('OpenAI API returned status code ' . $httpCode . ': ' . $response);
            }

            curl_close($ch);

            $data = json_decode($response, true);
            $responses = [];

            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $file) {
                    if (isset($file['id'])) {
                        $responses[] = $this->deleteFile($file['id']);
                    }
                }
            }

            return $responses;
        } catch (\Exception $e) {
            throw new \Exception('Failed to delete all files from OpenAI: ' . $e->getMessage());
        }
    }

    public function rewriteChapterForReadingLevel(
        string $originalContent,
        string $targetReadingLevel,
        int $wordCountLimit
    ): array {
        $minWords = $wordCountLimit - 50;
        $maxWords = $wordCountLimit + 50;

        $prompt = "You are a creative and engaging storyteller. Your task is to rewrite a chapter for a different reading level while maintaining the same story, characters, and emotional impact.

Original Content:
{$originalContent}

Target Reading Level: {$targetReadingLevel}
Word Count Limit: min {$minWords} max {$maxWords} words

Please rewrite the chapter to:
1. Maintain the exact same plot points and story progression
2. Keep the same character personalities and relationships
3. Preserve the emotional journey and key moments
4. Adjust vocabulary and sentence structure to match {$targetReadingLevel} reading level
5. Use simpler or more complex language as appropriate for the target level
6. Keep the same pacing and flow of the story
7. Maintain the same ending and hook for the next chapter
8. Use emojis sparingly and appropriately to enhance emotional moments
9. Stay within the {$wordCountLimit} word limit

Format your response as follows:

[CHAPTER]
[Your rewritten chapter content here]";

        try {
            $response = $this->client->request('POST', $this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a skilled educational content writer who specializes in adapting stories for different reading levels. Your job is to rewrite chapters while maintaining story consistency and adjusting language complexity. You must preserve the original story\'s plot, characters, and emotional impact while making the content accessible to readers at the target level.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $content = $data['choices'][0]['message']['content'] ?? 'Failed to rewrite chapter content.';

            // Log token usage
            $promptTokens = $data['usage']['prompt_tokens'] ?? 0;
            $completionTokens = $data['usage']['completion_tokens'] ?? 0;
            $totalTokens = $data['usage']['total_tokens'] ?? 0;
            error_log("Token Usage for Rewriting Chapter (Level {$targetReadingLevel}):\n" .
                "Prompt tokens: {$promptTokens}\n" .
                "Completion tokens: {$completionTokens}\n" .
                "Total tokens: {$totalTokens}");

            // Log the full AI response
            error_log("AI Response for Rewritten Chapter (Level {$targetReadingLevel}):\n" . $content);

            // Parse the response to get the chapter content
            preg_match('/\[CHAPTER\](.*?)$/s', $content, $matches);

            return [
                'content' => trim($matches[1] ?? $content)
            ];
        } catch (\Exception $e) {
            error_log('OpenAI API Error: ' . $e->getMessage());
            return [
                'content' => 'Failed to rewrite chapter content due to an API error.'
            ];
        }
    }
}