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
     * Generate metadata (title, tags, and optionally type) from content
     *
     * Takes the capture content and uses AI to generate:
     * - A concise, descriptive title
     * - Relevant tags for categorization
     * - When $captureTypes is provided: the single best-fitting capture type
     *
     * @param string $content The content to analyze
     * @param array<int, array{id: int, name: string, symbol: string, description: string|null}>|null $captureTypes Optional list of capture types (id, name, symbol, description) for classification
     * @return array{title: string, tags: array<string>, capture_type_id?: int|null}
     * @throws \Exception If the AI provider fails to generate metadata
     */
    public function generateMetadata(string $content, ?array $captureTypes = null): array;
}
