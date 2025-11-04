<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\RestaurantMenu;

class OpenAIService
{
    private HttpClientInterface $client;
    private string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        string $openaiApiKey,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager
    ) {
        $this->client = HttpClient::create();
        $this->apiKey = $openaiApiKey;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function getClient(): HttpClientInterface
    {
        return $this->client;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
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
            $this->logger->error('OpenAI API Error: ' . $e->getMessage());
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
1. Uses vocabulary and sentence structure appropriate for {$readingLevel} reading level
2. Follows the provided outline structure
3. Maintains consistency with the theme and goal
4. Creates engaging and vivid scenes
5. Develops the main character naturally, staying true to their personality and traits
6. Uses descriptive language that reflects the character's perspective
7. Includes dialogue that matches the character's voice and personality
8. Has a natural flow and pacing
9. Ends with a hook that encourages reading the next chapter
10. Stays within the {$wordCountLimit} word limit
11. Uses emojis sparingly and appropriately to enhance emotional moments, key events, or character expressions. Do not overuse emojis - they should complement the story, not overwhelm it.
12. Maintains continuity with past chapters and sets up future plot developments naturally
13. If a previous chapter is provided, ensure smooth transition and continuity from its events and character development

The chapter should be well-structured and engaging, suitable for the target reading level. Focus on showing rather than telling, and use sensory details to bring the story to life. Adjust the complexity of language and concepts to match the {$readingLevel} reading level. Ensure the narrative voice and perspective align with the main character's age, personality, and experiences. Be concise and efficient with your word choice to stay within the word limit while maintaining the story's impact.

After writing the chapter, provide:
1. A concise summary (maximum 50 words) that captures the key events and emotional journey of the chapter
2. A quiz with 3 questions that test comprehension of the chapter's key events and themes. Each question should have 4 multiple-choice options, with only one correct answer.
3. A chat thread title and initial content that will drive engagement and discussion among readers. The chat thread should:
   - Have an attention-grabbing title that hints at a key moment or theme from the chapter
   - Include an opening post that poses an interesting question or observation about the chapter
   - Encourage readers to share their thoughts, predictions, or personal connections to the story
   - Be written in a conversational, engaging tone that matches the chapter's style
   - Include 1 follow-up discussion points or questions to keep the conversation going

Format your response as follows:

[CHAPTER]
[Your chapter content here]

[SUMMARY]
[Your 50-word summary here]

[CHAT_THREAD_TITLE]
[Your engaging chat thread title here]

[CHAT_THREAD_CONTENT]
[Your initial chat thread post and discussion points here]

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
        $this->logger->debug("AI Prompt for Chapter '{$chapterName}':\n" . $prompt);

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
                    'temperature' => 0.7
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $content = $data['choices'][0]['message']['content'] ?? 'Failed to generate chapter content.';

            // Log token usage
            $promptTokens = $data['usage']['prompt_tokens'] ?? 0;
            $completionTokens = $data['usage']['completion_tokens'] ?? 0;
            $totalTokens = $data['usage']['total_tokens'] ?? 0;
            $this->logger->debug("Token Usage for Chapter '{$chapterName}':\n" .
                "Prompt tokens: {$promptTokens}\n" .
                "Completion tokens: {$completionTokens}\n" .
                "Total tokens: {$totalTokens}");

            // Log the full AI response
            $this->logger->debug("AI Response for Chapter '{$chapterName}':\n" . $content);

            // Parse the response to separate chapter, summary, and quiz
            preg_match('/\[CHAPTER\](.*?)\[SUMMARY\](.*?)\[CHAT_THREAD_TITLE\](.*?)\[CHAT_THREAD_CONTENT\](.*?)\[QUIZ\](.*?)$/s', $content, $matches);

            $result = [
                'content' => trim($matches[1] ?? $content),
                'summary' => trim($matches[2] ?? 'Failed to generate summary.'),
                'chat_thread_title' => trim($matches[3] ?? null),
                'chat_thread_content' => trim($matches[4] ?? null),
                'quiz' => json_decode(trim($matches[5] ?? '[]'), true)
            ];

            // Log the parsed result
            $this->logger->debug("Parsed Result for Chapter '{$chapterName}':\n" .
                "Content length: " . strlen($result['content']) . " characters\n" .
                "Summary: " . $result['summary'] . "\n" .
                "Chat Thread Title: " . ($result['chat_thread_title'] ?? 'Not generated') . "\n" .
                "Chat Thread Content: " . ($result['chat_thread_content'] ?? 'Not generated') . "\n" .
                "Quiz questions: " . count($result['quiz']));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('OpenAI API Error: ' . $e->getMessage());
            error_log('OpenAI API Error: ' . $e->getMessage());
            return [
                'content' => 'Failed to generate chapter content due to an API error.',
                'summary' => 'Failed to generate summary due to an API error.',
                'chat_thread_title' => null,
                'chat_thread_content' => null,
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
        int $wordCountLimit,
        ?string $originalChatThreadTitle = null,
        ?string $originalChatThreadContent = null
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

            // Log parsing results
            error_log("Debug - Response Parsing:");
            error_log("Matches found: " . count($matches));
            if (count($matches) > 1) {
                error_log("Chapter content length: " . strlen($matches[1]));
            } else {
                error_log("No chapter content found in matches");
            }

            $result = [
                'content' => trim($matches[1] ?? $content),
                'chat_thread_title' => $originalChatThreadTitle,
                'chat_thread_content' => $originalChatThreadContent
            ];

            // Log final result
            error_log("Debug - Final Result:");
            error_log("Content length: " . strlen($result['content']));
            error_log("Chat thread title: " . ($result['chat_thread_title'] ?? 'null'));
            error_log("Chat thread content: " . ($result['chat_thread_content'] ?? 'null'));

            return $result;
        } catch (\Exception $e) {
            error_log('OpenAI API Error: ' . $e->getMessage());
            error_log('Error trace: ' . $e->getTraceAsString());

            $errorResult = [
                'content' => 'Failed to rewrite chapter content due to an API error.',
                'chat_thread_title' => $originalChatThreadTitle,
                'chat_thread_content' => $originalChatThreadContent
            ];

            // Log error result
            error_log("Debug - Error Result:");
            error_log("Content: " . $errorResult['content']);
            error_log("Chat thread title: " . ($errorResult['chat_thread_title'] ?? 'null'));
            error_log("Chat thread content: " . ($errorResult['chat_thread_content'] ?? 'null'));

            return $errorResult;
        }
    }

    /**
     * @param string $message The current message
     * @param array $history The previous messages in the conversation
     * @return array|null
     */
    public function checkMessageForPhoneOrAddress(string $message, array $history = []): ?array
    {
        $historyText = '';
        if (!empty($history)) {
            $historyText = "Conversation history (previous messages):\n";
            foreach ($history as $i => $msg) {
                $historyText .= ($i + 1) . ". " . $msg . "\n";
            }
        }
        $prompt = "Are any of these messages (including the current one) trying to share a phone number or a street address? Reply ONLY with a valid JSON object with two boolean fields: contains_phone_number and contains_street_address. Do not include any explanation or text outside the JSON.\n" .
            $historyText .
            "Current message: '''$message'''";

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
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 100,
                    'temperature' => 0.0
                ]
            ]);
            $data = json_decode($response->getContent(), true);
            $openaiContent = $data['choices'][0]['message']['content'] ?? '';
            $json = json_decode($openaiContent, true);
            if (!is_array($json)) {
                // Try to extract JSON from the response if extra text is present
                if (preg_match('/\{.*\}/s', $openaiContent, $matches)) {
                    $json = json_decode($matches[0], true);
                }
            }
            return is_array($json) ? $json : null;
        } catch (\Exception $e) {
            $this->logger->error('OpenAI API Error (checkMessageForPhoneOrAddress): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate a low carb menu from a restaurant menu using AI
     *
     * @param string $restaurantName
     * @param string $menuText The menu as a string (all items)
     * @return array Grouped by starters, mains, dessert, drinks. Each item: name, description, estimated calories/kj
     */
    public function generateLowCarbMenu(string $restaurantName, string $menuText): array
    {
        $prompt = "You are a nutrition and food expert. Given the menu for a restaurant, identify ONLY the low carb options. Group them as Starters, Mains, Dessert, and Drinks. For each, return:\n- name of the meal\n- a short description\n- an estimated calories (kcal) or kilojoules (kJ) value (estimate if not provided)\n\nReturn ONLY a valid JSON object in this format:\n{\n  'starters': [ { 'name': '', 'description': '', 'calories_kj': '' }, ... ],\n  'mains': [ ... ],\n  'dessert': [ ... ],\n  'drinks': [ ... ]\n}\n\nIf a group has no low carb options, return an empty array for that group. Do not include any other text or explanation. Here is the menu for $restaurantName:\n\n$menuText";

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
                            'content' => 'You are a nutrition and food expert. Only return valid JSON.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            // Try to extract JSON from the response
            $json = json_decode($content, true);
            if (!is_array($json)) {
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $json = json_decode($matches[0], true);
                }
            }
            return is_array($json) ? $json : [];
        } catch (\Exception $e) {
            $this->logger->error('OpenAI API Error (generateLowCarbMenu): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate a menu for a given restaurant and food type by searching the internet
     *
     * @param string $restaurantName
     * @param string $foodType (e.g., 'low carb', 'vegetarian')
     * @param RestaurantMenu|null $menuEntity (optional) If provided, update status and error fields
     * @return array Grouped by starters, mains, dessert, drinks. Each item: name, description, estimated calories/kj
     */
    public function generateMenuByTypeAndRestaurant(string $restaurantName, string $foodType, $menuEntity = null): array
    {
        // Check cache first
        $repo = $this->entityManager->getRepository(RestaurantMenu::class);
        $existing = $repo->findOneBy([
            'restaurantName' => $restaurantName,
            'foodType' => $foodType
        ]);
        if ($existing && $menuEntity === null) {
            $createdAt = $existing->getCreatedAt();
            $now = new \DateTime();
            $interval = $now->diff($createdAt);
            $menuResult = $existing->getMenuResult();
            // Only return cached if not empty and less than 3 months old
            if (!empty($menuResult) && $interval->m < 3 && $interval->y === 0) {
                $this->logger->info('Returning cached menu for ' . $restaurantName . ' / ' . $foodType);
                return $menuResult;
            }
            $this->logger->info('Refreshing cached menu for ' . $restaurantName . ' / ' . $foodType . ' (older than 3 months or empty)', [
                'menuResult_empty' => empty($menuResult),
                'interval_months' => $interval->m,
                'interval_years' => $interval->y
            ]);
        }
        $aiInput = '';
        $isVegetarian = (strtolower(trim($foodType)) === 'vegetarian');
        $isLowCarb = (stripos($foodType, 'low-carb') !== false);
        $isVegan = (stripos(str_replace([' ', '-'], '', strtolower($foodType)), 'vegan') !== false);
        if ($isLowCarb) {
            $aiInput = "You are a nutrition and food expert. Search the internet for the menu of $restaurantName. Identify ONLY the low carb options (strictly EXCLUDE any meal containing bread, toast, chips, potatoes, rice, wraps, pizza base, pasta, or any other high-carb ingredients). Only include meals that are truly low carb (less than 15g net carbs per serving if possible). Group them as Starters, Mains, Dessert, and Drinks. For each, return:\n- name of the meal\n- a short description\n- an estimated calories (kcal) or kilojoules (kJ) value (estimate if not provided)\n- price (if available)\n\nReturn ONLY a valid JSON object in this format:\n{\n  \"starters\": [ { \"name\": \"\", \"description\": \"\", \"calories_kj\": 0, \"price\": \"\" }, ... ],\n  \"mains\": [ ... ],\n  \"dessert\": [ ... ],\n  \"drinks\": [ ... ]\n}\n\nIf a group has no low carb options, return an empty array for that group. Do not include any other text or explanation. If you cannot find any low carb options, return all groups as empty arrays. If you are unsure about a meal, err on the side of excluding it.";
        } elseif ($isVegan) {
            $aiInput = "You are a nutrition and food expert. Search the internet for the menu of $restaurantName. Identify ONLY the vegan options (strictly EXCLUDE any meal containing meat, fish, eggs, dairy, honey, or any animal-derived ingredients). Only include meals that are 100% plant-based. Group them as Starters, Mains, Dessert, and Drinks. For each, return:\n- name of the meal\n- a short description\n- an estimated calories (kcal) or kilojoules (kJ) value (estimate if not provided)\n- price (if available)\n\nReturn ONLY a valid JSON object in this format:\n{\n  \"starters\": [ { \"name\": \"\", \"description\": \"\", \"calories_kj\": 0, \"price\": \"\" }, ... ],\n  \"mains\": [ ... ],\n  \"dessert\": [ ... ],\n  \"drinks\": [ ... ]\n}\n\nIf a group has no vegan options, return an empty array for that group. Do not include any other text or explanation. If you cannot find any vegan options, return all groups as empty arrays. If you are unsure about a meal, err on the side of excluding it.";
        } elseif ($isVegetarian) {
            $aiInput = "You are a nutrition and food expert. Search the internet for the menu of $restaurantName. Identify ONLY the $foodType options. Group them as Starters and Mains. For each, return:\n- name of the meal\n- a short description\n- an estimated calories (kcal) or kilojoules (kJ) value (estimate if not provided)\n- price (if available)\n\nReturn ONLY a valid JSON object in this format:\n{\n  \"starters\": [ { \"name\": \"\", \"description\": \"\", \"calories_kj\": 0, \"price\": \"\" }, ... ],\n  \"mains\": [ ... ]\n}\n\nIf a group has no options, return an empty array for that group. Do not include any other text or explanation. If you cannot find the menu, return all groups as empty arrays.";
        } else {
            $aiInput = "You are a nutrition and food expert. Search the internet for the menu of $restaurantName. Identify ONLY the $foodType options. Group them as Starters, Mains, Dessert, and Drinks. For each, return:\n- name of the meal\n- a short description\n- an estimated calories (kcal) or kilojoules (kJ) value (estimate if not provided)\n- price (if available)\n\nReturn ONLY a valid JSON object in this format:\n{\n  \"starters\": [ { \"name\": \"\", \"description\": \"\", \"calories_kj\": 0, \"price\": \"\" }, ... ],\n  \"mains\": [ ... ],\n  \"dessert\": [ ... ],\n  \"drinks\": [ ... ]\n}\n\nIf a group has no options, return an empty array for that group. Do not include any other text or explanation. If you cannot find the menu, return all groups as empty arrays.";
        }

        $logContext = [
            'restaurant_name' => $restaurantName,
            'food_type' => $foodType,
            'ai_input' => $aiInput
        ];
        $this->logger->info('AI menu search request', $logContext);

        try {
            $response = $this->client->request('POST', 'https://api.openai.com/v1/responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4.1',
                    'tools' => [[ 'type' => 'web_search_preview' ]],
                    'input' => $aiInput
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $this->logger->info('AI menu search raw response', [
                'restaurant_name' => $restaurantName,
                'food_type' => $foodType,
                'raw_response' => $data
            ]);
            // Extract the JSON menu from the output message
            $result = [];
            $found = false;
            if (isset($data['output']) && is_array($data['output'])) {
                foreach ($data['output'] as $outputItem) {
                    if ($found) break;
                    if (
                        isset($outputItem['type']) && $outputItem['type'] === 'message' &&
                        isset($outputItem['content']) && is_array($outputItem['content'])
                    ) {
                        foreach ($outputItem['content'] as $contentItem) {
                            if ($found) break;
                            if (
                                isset($contentItem['type']) && $contentItem['type'] === 'output_text' &&
                                isset($contentItem['text'])
                            ) {
                                $text = $contentItem['text'];
                                // Remove code block markers if present
                                if (preg_match('/^```json\\n([\s\S]*)```$/', trim($text), $matches)) {
                                    $text = $matches[1];
                                }
                                $json = json_decode($text, true);
                                if (is_array($json)) {
                                    $result = $json;
                                    $found = true;
                                }
                            }
                        }
                    }
                }
            }
            $this->logger->info('AI menu search parsed result', [
                'restaurant_name' => $restaurantName,
                'food_type' => $foodType,
                'parsed_result' => $result
            ]);
            if (empty($result)) {
                $this->logger->warning('AI menu search result is empty or invalid', [
                    'restaurant_name' => $restaurantName,
                    'food_type' => $foodType,
                    'raw_response' => $data
                ]);
            }
            // Save to DB
            // Extract prices per group if present
            $menuPrices = null;
            if (is_array($result)) {
                $menuPrices = [];
                foreach (['starters', 'mains', 'dessert', 'drinks'] as $group) {
                    if (isset($result[$group]) && is_array($result[$group])) {
                        $menuPrices[$group] = array_map(function($meal) {
                            return $meal['price'] ?? null;
                        }, $result[$group]);
                    }
                }
            }
            if ($existing && $menuEntity === null) {
                $existing->setMenuResult($result);
                $existing->setMenuPrices($menuPrices);
                $existing->setUpdatedAt(new \DateTime());
                $existing->setCreatedAt(new \DateTime());
                $this->entityManager->flush();
            } elseif ($menuEntity) {
                $menuEntity->setMenuResult($result);
                $menuEntity->setMenuPrices($menuPrices);
                $menuEntity->setStatus('ready');
                $menuEntity->setErrorMessage(null);
                $menuEntity->setUpdatedAt(new \DateTime());
                $this->entityManager->persist($menuEntity);
                $this->entityManager->flush();
            } else {
                $menu = new RestaurantMenu();
                $menu->setRestaurantName($restaurantName);
                $menu->setFoodType($foodType);
                $menu->setMenuResult($result);
                $menu->setMenuPrices($menuPrices);
                $menu->setCreatedAt(new \DateTime());
                $menu->setUpdatedAt(new \DateTime());
                $this->entityManager->persist($menu);
                $this->entityManager->flush();
            }
            return $result;
        } catch (\Exception $e) {
            if ($menuEntity) {
                $menuEntity->setStatus('error');
                $menuEntity->setErrorMessage($e->getMessage());
                $menuEntity->setUpdatedAt(new \DateTime());
                $this->entityManager->persist($menuEntity);
                $this->entityManager->flush();
            }
            $this->logger->error('OpenAI API Error (generateMenuByTypeAndRestaurant): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate menus by food type for a list of restaurants using AI
     *
     * @param string $foodType
     * @param array $restaurantNames
     * @return array Associative array: restaurant name => grouped menu
     */
    public function generateMenusByTypeForRestaurants(string $foodType, array $restaurantNames): array
    {
        $restaurantList = implode(", ", $restaurantNames);
        $prompt = "You are a nutrition and food expert. For each of the following restaurants, search the internet for their menu and identify ONLY the $foodType options. For each restaurant, return ONLY the top 3 Mains (main courses), sorted by popularity or menu prominence if possible. For each item, return:\n- name of the meal\n- a short description\n- an estimated calories (kcal) or kilojoules (kJ) value (estimate if not provided)\n\nReturn ONLY a valid JSON object in this format:\n{ 'RESTAURANT_NAME': { 'mains': [...] }, ... }\n\nIf there are no mains, return an empty array for that restaurant. If you cannot find the menu for a restaurant, return 'mains' as an empty array for that restaurant. Here are the restaurants:\n$restaurantList";

        $this->logger->info('AI menus by type for restaurants request', [
            'food_type' => $foodType,
            'restaurants' => $restaurantNames,
            'prompt' => $prompt
        ]);

        try {
            $response = $this->client->request('POST', 'https://api.openai.com/v1/responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4.1',
                    'tools' => [[ 'type' => 'web_search_preview' ]],
                    'input' => $prompt
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            $this->logger->info('AI menus by type for restaurants response', [
                'food_type' => $foodType,
                'restaurants' => $restaurantNames,
                'raw_response' => $content
            ]);
            // Try to extract JSON from the response
            $json = json_decode($content, true);
            if (!is_array($json)) {
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $json = json_decode($matches[0], true);
                }
            }
            // Only return the top 5 'mains' for each restaurant
            if (is_array($json)) {
                $mainsOnly = [];
                foreach ($json as $restaurant => $groups) {
                    $mains = $groups['mains'] ?? [];
                    $mainsOnly[$restaurant] = [
                        'mains' => array_slice($mains, 0, 5)
                    ];
                }
                return $mainsOnly;
            }
            return [];
        } catch (\Exception $e) {
            $this->logger->error('OpenAI API Error (generateMenusByTypeForRestaurants): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate a list of restaurants and their menus by food type near a location using AI web search
     *
     * @param float $lat
     * @param float $lng
     * @param float $radius (in km)
     * @param string $foodType
     * @return array
     */
    public function generateNearbyMenusByType(float $lat, float $lng, float $radius, string $foodType): array
    {
        $prompt = "You are a nutrition and food expert. Search the internet for restaurants within {$radius}km of latitude {$lat}, longitude {$lng}. For each restaurant, return:\n- name\n- address (if available)\n- logo_url (if available)\n- the top 3 Mains (main courses) for {$foodType} eaters, sorted by popularity or menu prominence if possible. For each main, return name, a short description, and estimated calories (kcal) or kilojoules (kJ) if available.\n\nReturn ONLY a valid JSON array in this format:\n[ { 'name': '', 'address': '', 'logo_url': '', 'mains': [ { 'name': '', 'description': '', 'calories_kj': '' }, ... ] }, ... ]\n\nIf you cannot find any restaurants or menus, return an empty array. Do not include any other text or explanation.";

        $this->logger->info('AI nearby menus by type request', [
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius,
            'food_type' => $foodType,
            'prompt' => $prompt
        ]);

        try {
            $response = $this->client->request('POST', 'https://api.openai.com/v1/responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4.1',
                    'tools' => [[ 'type' => 'web_search_preview' ]],
                    'input' => $prompt
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            $this->logger->info('AI nearby menus by type response', [
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radius,
                'food_type' => $foodType,
                'raw_response' => $content
            ]);
            // Try to extract JSON from the response
            $json = json_decode($content, true);
            if (!is_array($json)) {
                if (preg_match('/\[.*\]/s', $content, $matches)) {
                    $json = json_decode($matches[0], true);
                }
            }
            return is_array($json) ? $json : [];
        } catch (\Exception $e) {
            $this->logger->error('OpenAI API Error (generateNearbyMenusByType): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a satirical news video script from a transcript file ID targeting a duration in seconds.
     * Returns the script as a string, ready to read aloud.
     * 
     * @param string $fileId The OpenAI file ID containing the transcript
     * @param int $maxStories Maximum number of stories to include
     * @param int $targetSeconds Target duration in seconds
     * @param string $anchorName The name of the news anchor (default: "Dan")
     * @return string
     */
    public function generateVideoScriptFromFileId(string $fileId, int $maxStories = 10, int $targetSeconds = 60, string $anchorName = 'Dan'): string
    {
        $wordsPerSecond = 2.6;
        $targetWords = (int)round($targetSeconds * $wordsPerSecond);
        $minWords = max(30, (int)floor($targetWords * 0.9));
        $maxWords = (int)ceil($targetWords * 1.1);

        // Customize intro and outro based on anchor name
        $taglines = [
            'Ammy' => [
                'intro' => "I'm Ammy — here's your commission daily update.",
                'outro' => "Goodnight, Mzansi."
            ],
            'Sam' => [
                'intro' => "I'm Sam — here's your parliamentary ad-hoc daily update.",
                'outro' => "Goodnight, Mzansi."
            ],
            'Dan' => [
                'intro' => "I'm Dan — here's your daily update.",
                'outro' => "Goodnight, Mzansi."
            ]
        ];
        
        $anchorTaglines = $taglines[$anchorName] ?? $taglines['Dan'];
        $introLine = $anchorTaglines['intro'];
        $outroLine = $anchorTaglines['outro'];

        $rules = <<<TXT
TONE & STYLE
- Neutral, authoritative, fast-paced. No jokes, no sarcasm, no wordplay.
- Broadcast headline style. Concise, direct, fact-driven.
- Sentences under 14 words. Sharp, punchy, information-dense.
- Natural to read aloud near the target duration.

STRUCTURE
INTRO (5–8s) — EXACT line (do not change):
"{$introLine}"
Optionally add [Music fades].

MAIN STORIES (descending importance)
- Rapid-fire updates across national, politics, crime/commission, economy, society, global.
- Cover many points briefly; prefer breadth over depth.
- Each story: 1 short factual sentence. Optional second sentence for key context.

OUTRO — CONSISTENT line:
"{$outroLine}"

LENGTH & PACING (duration target)
- Target {$targetSeconds}s total. Aim for {$minWords}–{$maxWords} words (≈ {$wordsPerSecond} wps).
- Max stories: {$maxStories}. Prefer 8–12 micro-updates if content allows.

STANDARDS
- No editorialising beyond neutral qualifiers. No humour or punchlines.
- At least one verifiable fact or figure from the transcript.
TXT;

        $prompt = <<<PR
You will write a neutral, fast-paced news script using the RULES below for a target duration of {$targetSeconds} seconds.

Source transcript (summarize into many concise points, keep facts accurate):
Please analyze the transcript from the uploaded file and create a script based on its content.

OUTPUT REQUIREMENTS
- Aim for {$minWords}–{$maxWords} words (≈ {$wordsPerSecond} words/second) for ~{$targetSeconds}s.
- Up to {$maxStories} stories; prefer many short updates.
- Each story: 1 short factual sentence; optional second sentence for key context.
- Keep sentences under 14 words.
- Start with the exact INTRO line:
  "{$introLine}"
- End with the exact OUTRO line.
- Do not include any explanation outside the script.
- Include at least one short verifiable fact from the transcript.

STRICT OUTPUT FORMAT (PLAIN PARAGRAPH)
- Do NOT include headings, labels, brackets, tags, numbering, or list markers.
- Output as ONE single paragraph with no line breaks and no \n characters.
- No extra whitespace or blank lines.

RULES
{$rules}
PR;

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
                            'content' => 'You are a senior broadcast news writer. You write neutral, fast-paced headline scripts with many concise updates, no humour, and strict timing.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.5,
                    'max_tokens' => 1200
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $script = trim($data['choices'][0]['message']['content'] ?? '');
            if ($script === '') {
                return 'Failed to generate script.';
            }
            // Enforce approximate length around target
            $words = preg_split('/\s+/', strip_tags($script));
            $count = is_array($words) ? count($words) : 0;
            if ($count < $minWords || $count > $maxWords) {
                // Second-pass adjustment request to hit the window
                try {
                    $rewritePrompt = "Rewrite the following as ONE paragraph, no line breaks, no numbering. Keep the exact intro and a valid outro. Aim for {$minWords}-{$maxWords} words. Keep sentences under 18 words. Max {$maxStories} stories. Script: '" . $script . "'";
                    $resp2 = $this->client->request('POST', $this->apiUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->apiKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'model' => 'gpt-4o-mini',
                            'messages' => [
                                [ 'role' => 'system', 'content' => 'You compress scripts to exact word budgets without changing specified required lines.' ],
                                [ 'role' => 'user', 'content' => $rewritePrompt ],
                            ],
                            'temperature' => 0.3,
                            'max_tokens' => 1200,
                        ],
                    ]);
                    $d2 = json_decode($resp2->getContent(), true);
                    $s2 = trim($d2['choices'][0]['message']['content'] ?? '');
                    if ($s2 !== '') {
                        $script = $s2;
                        $words = preg_split('/\s+/', strip_tags($script));
                        $count = is_array($words) ? count($words) : 0;
                    }
                } catch (\Exception $e) {
                    // fall through
                }
            }
            // No final hard clamp for long-form targets to preserve coherence
            $this->logger->info('Generated video script from transcript', [ 'approx_words' => is_array($words) ? count($words) : null ]);
            return $script;
        } catch (\Exception $e) {
            $this->logger->error('OpenAI API Error (generateVideoScriptFromTranscript): ' . $e->getMessage());
            return 'Failed to generate script due to an API error.';
        }
    }

    /**
     * Simplify South African names that AI struggles to pronounce correctly.
     */
    private function simplifySouthAfricanNames(string $text): string
    {
        $replacements = [
            'Bheki Cele' => 'Bheki',
            'Senzo Mchunu' => 'Senzo',
            'matlala' => '',
            'Nhlanhla Mkhwanazi' => 'Mkhwanazi',
        ];

        foreach ($replacements as $fullName => $shortName) {
            $text = str_ireplace($fullName, $shortName, $text);
        }

        return $text;
    }

    /**
     * Upload a file to OpenAI and return the file ID
     */
    public function uploadTranscriptFile(string $filePath, string $purpose = 'assistants'): ?string
    {
        try {
            // Create multipart form data manually
            $boundary = uniqid();
            $fileContent = file_get_contents($filePath);
            $fileName = basename($filePath);
            
            $body = "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$fileName}\"\r\n";
            $body .= "Content-Type: text/plain\r\n\r\n";
            $body .= $fileContent . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"purpose\"\r\n\r\n";
            $body .= $purpose . "\r\n";
            $body .= "--{$boundary}--\r\n";

            $response = $this->client->request('POST', 'https://api.openai.com/v1/files', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => "multipart/form-data; boundary={$boundary}",
                ],
                'body' => $body
            ]);

            $data = json_decode($response->getContent(), true);
            return $data['id'] ?? null;
        } catch (\Exception $e) {
            $this->logger->error('OpenAI file upload error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a file from OpenAI
     */
    public function deleteTranscriptFile(string $fileId): bool
    {
        try {
            $this->client->request('DELETE', "https://api.openai.com/v1/files/{$fileId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ]
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('OpenAI file deletion error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a satirical news video script from a transcript targeting a duration in seconds.
     * Returns the script as a string, ready to read aloud.
     * 
     * @param string $transcript The source transcript
     * @param int $maxStories Maximum number of stories to include
     * @param int $targetSeconds Target duration in seconds
     * @param string $anchorName The name of the news anchor (default: "Dan")
     * @return string
     */
    public function generateVideoScriptFromTranscript(string $transcript, int $maxStories = 10, int $targetSeconds = 60, string $anchorName = 'Dan'): string
    {
        $wordsPerSecond = 2.6;
        $targetWords = (int)round($targetSeconds * $wordsPerSecond);
        $minWords = max(30, (int)floor($targetWords * 0.9));
        $maxWords = (int)ceil($targetWords * 1.1);

        // Customize intro and outro based on anchor name
        $taglines = [
            'Ammy' => [
                'intro' => "I'm Ammy — here's your commission daily update.",
                'outro' => "Goodnight, Mzansi."
            ],
            'Sam' => [
                'intro' => "I'm Sam — here's your parliamentary ad-hoc daily update.",
                'outro' => "Goodnight, Mzansi."
            ],
            'Dan' => [
                'intro' => "I'm Dan — here's your daily update.",
                'outro' => "Goodnight, Mzansi."
            ]
        ];
        
        $anchorTaglines = $taglines[$anchorName] ?? $taglines['Dan'];
        $introLine = $anchorTaglines['intro'];
        $outroLine = $anchorTaglines['outro'];

        $rules = <<<TXT
TONE & STYLE
- Neutral, authoritative, fast-paced. No jokes, no sarcasm, no wordplay.
- Broadcast headline style. Concise, direct, fact-driven.
- Sentences under 14 words. Sharp, punchy, information-dense.
- Natural to read aloud near the target duration.

STRUCTURE
INTRO (5–8s) — EXACT line (do not change):
"{$introLine}"
Optionally add [Music fades].

MAIN STORIES (descending importance)
- Rapid-fire updates across national, politics, crime/commission, economy, society, global.
- Cover many points briefly; prefer breadth over depth.
- Each story: 1 short factual sentence. Optional second sentence for key context.

OUTRO — CONSISTENT line:
"{$outroLine}"

LENGTH & PACING (duration target)
- Target {$targetSeconds}s total. Aim for {$minWords}–{$maxWords} words (≈ {$wordsPerSecond} wps).
- Max stories: {$maxStories}. Prefer 8–12 micro-updates if content allows.

STANDARDS
- No editorialising beyond neutral qualifiers. No humour or punchlines.
- At least one verifiable fact or figure from the transcript.
TXT;

        $prompt = <<<PR
You will write a neutral, fast-paced news script using the RULES below for a target duration of {$targetSeconds} seconds.

Source transcript (summarize into many concise points, keep facts accurate):
"""
{$transcript}
"""

OUTPUT REQUIREMENTS
- Aim for {$minWords}–{$maxWords} words (≈ {$wordsPerSecond} words/second) for ~{$targetSeconds}s.
- Up to {$maxStories} stories; prefer many short updates.
- Each story: 1 short factual sentence; optional second sentence for key context.
- Keep sentences under 14 words.
- Start with the exact INTRO line:
  "{$introLine}"
- End with the exact OUTRO line.
- Do not include any explanation outside the script.
- Include at least one short verifiable fact from the transcript.

STRICT OUTPUT FORMAT (PLAIN PARAGRAPH)
- Do NOT include headings, labels, brackets, tags, numbering, or list markers.
- Output as ONE single paragraph with no line breaks and no \n characters.
- No extra whitespace or blank lines.

RULES
{$rules}
PR;

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
                            'content' => 'You are a senior broadcast news writer. You write neutral, fast-paced headline scripts with many concise updates, no humour, and strict timing.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.5,
                    'max_tokens' => 1200
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $script = trim($data['choices'][0]['message']['content'] ?? '');
            if ($script === '') {
                return 'Failed to generate script.';
            }
            // Enforce approximate length around target
            $words = preg_split('/\s+/', strip_tags($script));
            $count = is_array($words) ? count($words) : 0;
            if ($count < $minWords || $count > $maxWords) {
                // Second-pass adjustment request to hit the window
                try {
                    $rewritePrompt = "Rewrite the following as ONE paragraph, no line breaks, no numbering. Keep the exact intro and a valid outro. Aim for {$minWords}-{$maxWords} words. Keep sentences under 18 words. Max {$maxStories} stories. Script: '" . $script . "'";
                    $resp2 = $this->client->request('POST', $this->apiUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->apiKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'model' => 'gpt-4o-mini',
                            'messages' => [
                                [ 'role' => 'system', 'content' => 'You compress scripts to exact word budgets without changing specified required lines.' ],
                                [ 'role' => 'user', 'content' => $rewritePrompt ]
                            ],
                            'temperature' => 0.3,
                            'max_tokens' => 1000
                        ]
                    ]);
                    $data2 = json_decode($resp2->getContent(), true);
                    $script2 = trim($data2['choices'][0]['message']['content'] ?? '');
                    if ($script2 !== '') {
                        $words2 = preg_split('/\s+/', strip_tags($script2));
                        $count2 = is_array($words2) ? count($words2) : 0;
                        if ($count2 >= $minWords && $count2 <= $maxWords) {
                            $script = $script2;
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore second-pass errors
                }
            }
            // Final hard clamp if still too long
            $words = preg_split('/\s+/', strip_tags($script));
            if (is_array($words) && count($words) > $maxWords) {
                $script = implode(' ', array_slice($words, 0, $maxWords));
            }
            $this->logger->info('Generated video script from transcript', [ 'approx_words' => is_array($words) ? count($words) : null ]);
            return $script;
        } catch (\Exception $e) {
            $this->logger->error('OpenAI API Error (generateVideoScriptFromTranscript): ' . $e->getMessage());
            return 'Failed to generate script due to an API error.';
        }
    }
}