<?php

namespace App\Contracts;

/**
 * Interface for AI providers that suggest a project for a capture based on content and project descriptions.
 */
interface AiProjectSuggester
{
    /**
     * Suggest a project for the given content based on project descriptions.
     *
     * @param string $content The capture content to analyze
     * @param array<int, array{id: int, name: string, description: string|null}> $projects List of projects with id, name, description
     * @return array{project_id: int, confidence: int}|null Returns { project_id, confidence } or null if no suggestion
     * @throws \Exception If the AI provider fails
     */
    public function suggestProject(string $content, array $projects): ?array;
}
