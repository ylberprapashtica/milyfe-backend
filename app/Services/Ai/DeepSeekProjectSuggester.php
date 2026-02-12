<?php

namespace App\Services\Ai;

use App\Contracts\AiProjectSuggester;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * DeepSeek AI implementation for suggesting a project for a capture.
 */
class DeepSeekProjectSuggester implements AiProjectSuggester
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
     * {@inheritdoc}
     */
    public function suggestProject(string $content, array $projects): ?array
    {
        if (empty($projects)) {
            return null;
        }

        try {
            $prompt = $this->buildPrompt($content, $projects);
            $response = $this->callDeepSeekApi($prompt);

            return $this->parseResponse($response, $projects);
        } catch (\Exception $e) {
            Log::error('DeepSeek project suggestion error: ' . $e->getMessage(), [
                'content_length' => strlen($content),
                'projects_count' => count($projects),
            ]);

            return null;
        }
    }

    private function buildPrompt(string $content, array $projects): string
    {
        $maxLength = 3000;
        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength) . '...';
        }

        $projectsList = [];
        foreach ($projects as $p) {
            $desc = $p['description'] ?? '';
            $projectsList[] = sprintf(
                '- ID %d: "%s"%s',
                $p['id'],
                $p['name'],
                $desc ? ' - ' . $desc : ''
            );
        }

        $projectsText = implode("\n", $projectsList);

        return <<<PROMPT
Given this note content and these projects with their descriptions, pick the single best matching project.

Projects:
{$projectsText}

Note content:
{$content}

Respond ONLY with valid JSON in this exact format. If no project is a good match (confidence < 80), return null for project_id:
{"project_id": <id or null>, "confidence": <0-100>}
PROMPT;
    }

    private function callDeepSeekApi(string $prompt): array
    {
        $response = $this->client->post($this->apiUrl . '/chat/completions', [
            'json' => [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that matches notes to projects based on their descriptions. Always respond with valid JSON only. Be conservative: only suggest a project if you are confident (80%+) the note belongs there.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 100,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function parseResponse(array $response, array $projects): ?array
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            return null;
        }

        $content = $response['choices'][0]['message']['content'];
        $content = preg_replace('/```json\s*|\s*```/', '', $content);
        $content = trim($content);

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return null;
        }

        $projectId = $data['project_id'] ?? null;
        $confidence = isset($data['confidence']) ? (int) $data['confidence'] : 0;

        if ($projectId === null || $confidence < 80) {
            return null;
        }

        $validIds = array_column($projects, 'id');
        if (!in_array($projectId, $validIds)) {
            return null;
        }

        return [
            'project_id' => (int) $projectId,
            'confidence' => min(100, max(0, $confidence)),
        ];
    }
}
