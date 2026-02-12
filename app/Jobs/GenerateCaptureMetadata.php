<?php

namespace App\Jobs;

use App\Contracts\AiMetadataGenerator;
use App\Models\Capture;
use App\Models\CaptureType;
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
        public bool $generateTags = false,
        public bool $generateType = false
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AiMetadataGenerator $generator): void
    {
        // Skip if nothing to generate
        if (!$this->generateTitle && !$this->generateTags && !$this->generateType) {
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
                'generate_type' => $this->generateType,
            ]);

            $captureTypes = null;
            if ($this->generateType && strlen(trim($this->capture->content)) >= 10) {
                $captureTypes = CaptureType::all(['id', 'name', 'symbol', 'description'])->toArray();
            }

            // Call the AI to generate metadata
            $metadata = $generator->generateMetadata($this->capture->content, $captureTypes);

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

                $currentTagIds = $this->capture->tagRelations()->get()->pluck('id')->toArray();
                $mergedTagIds = array_values(array_unique(array_merge($currentTagIds, $existingTagIds)));
                $this->capture->tagRelations()->sync($mergedTagIds);
            }

            if ($this->generateType && array_key_exists('capture_type_id', $metadata) && $metadata['capture_type_id'] !== null) {
                $updateData['capture_type_id'] = $metadata['capture_type_id'];
            }

            // Only update actual capture table columns (never 'tags' - those are synced via tagRelations above).
            // Use a direct query so the Capture model cannot inject virtual attributes (e.g. appended 'tags') into the update.
            $allowedColumns = ['title', 'slug', 'capture_type_id'];
            $updateData = array_intersect_key($updateData, array_flip($allowedColumns));

            if (!empty($updateData)) {
                Capture::where('id', $this->capture->id)->update($updateData);
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
