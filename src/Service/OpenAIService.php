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
}