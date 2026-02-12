<?php

namespace App\Jobs;

use App\Contracts\AiMetadataGenerator;
use App\Models\Capture;
use App\Models\Tag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to generate metadata (title and tags) for a capture using AI
 * 
 * This job runs asynchronously to generate missing title and/or tags
 * for a capture using the configured AI provider (DeepSeek, OpenAI, etc.)
 */
class GenerateCaptureMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Capture $capture,
        public bool $generateTitle = false,
        public bool $generateTags = false
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AiMetadataGenerator $generator): void
    {
        // Skip if nothing to generate
        if (!$this->generateTitle && !$this->generateTags) {
            Log::info('GenerateCaptureMetadata: Nothing to generate', [
                'capture_id' => $this->capture->id,
            ]);
            return;
        }

        try {
            Log::info('GenerateCaptureMetadata: Starting', [
                'capture_id' => $this->capture->id,
                'generate_title' => $this->generateTitle,
                'generate_tags' => $this->generateTags,
            ]);

            // Call the AI to generate metadata
            $metadata = $generator->generateMetadata($this->capture->content);

            // Prepare update data
            $updateData = [];

            if ($this->generateTitle && !empty($metadata['title'])) {
                $updateData['title'] = $metadata['title'];
                $updateData['slug'] = Capture::generateSlug($metadata['title']);
            }

            if ($this->generateTags && !empty($metadata['tags'])) {
                $tagNames = array_map(fn ($t) => is_string($t) ? strtolower(trim($t)) : null, $metadata['tags']);
                $tagNames = array_filter(array_unique($tagNames));

                // Only attach tags that already exist (AI must not create new tags)
                $existingTagIds = Tag::where('user_id', $this->capture->user_id)
                    ->whereIn('name', $tagNames)
                    ->pluck('id')
                    ->toArray();

                $currentTagIds = $this->capture->tagRelations()->pluck('id')->toArray();
                $mergedTagIds = array_values(array_unique(array_merge($currentTagIds, $existingTagIds)));
                $this->capture->tagRelations()->sync($mergedTagIds);
            }

            if (isset($updateData['title'])) {
                $this->capture->update([
                    'title' => $updateData['title'],
                    'slug' => $updateData['slug'],
                ]);
            }

            $hasUpdates = !empty($updateData) || ($this->generateTags && !empty($metadata['tags'] ?? []));
            if ($hasUpdates) {
                Log::info('GenerateCaptureMetadata: Success', [
                    'capture_id' => $this->capture->id,
                    'updated_fields' => array_keys($updateData),
                    'title' => $updateData['title'] ?? null,
                ]);
            } else {
                Log::warning('GenerateCaptureMetadata: No metadata generated', [
                    'capture_id' => $this->capture->id,
                    'metadata' => $metadata,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('GenerateCaptureMetadata: Failed', [
                'capture_id' => $this->capture->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateCaptureMetadata: Job failed permanently', [
            'capture_id' => $this->capture->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
