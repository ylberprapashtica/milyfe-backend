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
     * Generate metadata (title, tags, and optionally type) from content using DeepSeek API
     *
     * @param string $content The content to analyze
     * @param array<int, array{id: int, name: string, symbol: string, description: string|null}>|null $captureTypes Optional list of capture types for classification
     * @return array{title: string, tags: array<string>, capture_type_id?: int|null}
     * @throws \Exception If the API request fails or response is invalid
     */
    public function generateMetadata(string $content, ?array $captureTypes = null): array
    {
        try {
            $prompt = $this->buildPrompt($content, $captureTypes);
            $response = $this->callDeepSeekApi($prompt, $captureTypes !== null);
            
            return $this->parseResponse($response, $captureTypes);
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
     *
     * @param array<int, array{id: int, name: string, symbol: string, description: string|null}>|null $captureTypes
     */
    private function buildPrompt(string $content, ?array $captureTypes = null): string
    {
        // Truncate very long content to avoid token limits
        $maxLength = 4000;
        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength) . '...';
        }

        $typeBlock = '';
        $jsonFormat = '{"title": "your title here", "tags": ["tag1", "tag2", "tag3"]}';

        if ($captureTypes !== null && count($captureTypes) > 0 && strlen(trim($content)) >= 10) {
            $typeLines = array_map(function ($t) {
                $desc = isset($t['description']) && $t['description'] !== null ? ': ' . $t['description'] : '';
                return '- ' . $t['name'] . ' (' . $t['symbol'] . ')' . $desc;
            }, $captureTypes);
            $typeBlock = "\n3. The single best-fitting capture type from this list (use exact name):\n" . implode("\n", $typeLines) . "\n\nChoose the type that best matches the note's primary purpose.";
            $jsonFormat = '{"title": "your title here", "tags": ["tag1", "tag2", "tag3"], "capture_type_name": "exact_type_name"}';
        }

        return <<<PROMPT
Analyze this note content and generate:
1. A concise title (max 50 characters)
2. Relevant tags (1-5 tags, lowercase, single words or short phrases){$typeBlock}

Content:
{$content}

Respond ONLY with valid JSON in this exact format:
{$jsonFormat}
PROMPT;
    }

    /**
     * Call the DeepSeek API
     *
     * @param bool $includeType Whether the prompt asks for capture type classification
     * @throws GuzzleException
     */
    private function callDeepSeekApi(string $prompt, bool $includeType = false): array
    {
        $systemContent = 'You are a helpful assistant that analyzes note content and generates concise titles and relevant tags.';
        if ($includeType) {
            $systemContent .= ' When asked, choose exactly one capture type from the given list that best fits the content. Use the exact type name from the list.';
        }
        $systemContent .= ' Always respond with valid JSON only.';

        $response = $this->client->post($this->apiUrl . '/chat/completions', [
            'json' => [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemContent,
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
     * Parse the API response and extract title, tags, and optionally capture_type_id
     *
     * @param array<int, array{id: int, name: string, symbol: string, description: string|null}>|null $captureTypes
     * @throws \Exception If response is invalid
     */
    private function parseResponse(array $response, ?array $captureTypes = null): array
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

        $result = [
            'title' => trim($data['title']),
            'tags' => array_values($tags),
        ];

        // Map capture_type_name to capture_type_id when types were requested
        if ($captureTypes !== null && isset($data['capture_type_name']) && is_string($data['capture_type_name'])) {
            $name = strtolower(trim($data['capture_type_name']));
            foreach ($captureTypes as $type) {
                if (strtolower((string) $type['name']) === $name) {
                    $result['capture_type_id'] = (int) $type['id'];
                    break;
                }
            }
            if (!isset($result['capture_type_id'])) {
                $result['capture_type_id'] = null;
            }
        }

        return $result;
    }
}
