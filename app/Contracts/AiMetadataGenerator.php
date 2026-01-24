<?php

namespace App\Contracts;

/**
 * Interface for AI providers that generate metadata for captures
 * 
 * This interface allows different AI providers (DeepSeek, OpenAI, Claude, etc.)
 * to be used interchangeably for generating titles and tags from content.
 */
interface AiMetadataGenerator
{
    /**
     * Generate metadata (title and tags) from content
     * 
     * Takes the capture content and uses AI to generate:
     * - A concise, descriptive title
     * - Relevant tags for categorization
     * 
     * @param string $content The content to analyze
     * @return array{title: string, tags: array<string>} Array with 'title' (string) and 'tags' (array of strings)
     * @throws \Exception If the AI provider fails to generate metadata
     */
    public function generateMetadata(string $content): array;
}
