<?php

namespace App\Services\Ai;

use App\Contracts\AiMetadataGenerator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * DeepSeek AI implementation for generating capture metadata
 * 
 * Uses the DeepSeek API to generate titles and tags from capture content.
 */
class DeepSeekMetadataGenerator implements AiMetadataGenerator
{
    private Client $client;
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.ai.deepseek.api_key');
        $this->apiUrl = config('services.ai.deepseek.api_url');
        $this->model = config('services.ai.deepseek.model');

        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Generate metadata (title and tags) from content using DeepSeek API
     * 
     * @param string $content The content to analyze
     * @return array{title: string, tags: array<string>}
     * @throws \Exception If the API request fails or response is invalid
     */
    public function generateMetadata(string $content): array
    {
        try {
            $prompt = $this->buildPrompt($content);
            $response = $this->callDeepSeekApi($prompt);
            
            return $this->parseResponse($response);
        } catch (\Exception $e) {
            Log::error('DeepSeek API error: ' . $e->getMessage(), [
                'content_length' => strlen($content),
                'error' => $e->getMessage(),
            ]);
            
            throw new \Exception('Failed to generate metadata with DeepSeek: ' . $e->getMessage());
        }
    }

    /**
     * Build the prompt for the AI
     */
    private function buildPrompt(string $content): string
    {
        // Truncate very long content to avoid token limits
        $maxLength = 4000;
        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength) . '...';
        }

        return <<<PROMPT
Analyze this note content and generate:
1. A concise title (max 50 characters)
2. Relevant tags (1-5 tags, lowercase, single words or short phrases)

Content:
{$content}

Respond ONLY with valid JSON in this exact format:
{"title": "your title here", "tags": ["tag1", "tag2", "tag3"]}
PROMPT;
    }

    /**
     * Call the DeepSeek API
     * 
     * @throws GuzzleException
     */
    private function callDeepSeekApi(string $prompt): array
    {
        $response = $this->client->post($this->apiUrl . '/chat/completions', [
            'json' => [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that analyzes note content and generates concise titles and relevant tags. Always respond with valid JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 200,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Parse the API response and extract title and tags
     * 
     * @throws \Exception If response is invalid
     */
    private function parseResponse(array $response): array
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid response structure from DeepSeek API');
        }

        $content = $response['choices'][0]['message']['content'];
        
        // Try to extract JSON from the response
        // Sometimes the AI might wrap the JSON in markdown code blocks
        $content = preg_replace('/```json\s*|\s*```/', '', $content);
        $content = trim($content);
        
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse DeepSeek response as JSON', [
                'response' => $content,
                'error' => json_last_error_msg(),
            ]);
            throw new \Exception('Failed to parse AI response as JSON');
        }

        // Validate the response structure
        if (!isset($data['title']) || !isset($data['tags'])) {
            throw new \Exception('Response missing required fields (title or tags)');
        }

        if (!is_string($data['title'])) {
            throw new \Exception('Title must be a string');
        }

        if (!is_array($data['tags'])) {
            throw new \Exception('Tags must be an array');
        }

        // Clean and validate tags
        $tags = array_filter(array_map(function($tag) {
            return is_string($tag) ? strtolower(trim($tag)) : null;
        }, $data['tags']));

        return [
            'title' => trim($data['title']),
            'tags' => array_values($tags),
        ];
    }
}
