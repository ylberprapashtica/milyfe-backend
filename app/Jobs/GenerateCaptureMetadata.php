<?php

namespace App\Jobs;

use App\Contracts\AiMetadataGenerator;
use App\Models\Capture;
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
                
                // Also regenerate slug from the new title
                $updateData['slug'] = Capture::generateSlug($metadata['title']);
            }

            if ($this->generateTags && !empty($metadata['tags'])) {
                $updateData['tags'] = $metadata['tags'];
            }

            // Update the capture if we have data
            if (!empty($updateData)) {
                $this->capture->update($updateData);

                Log::info('GenerateCaptureMetadata: Success', [
                    'capture_id' => $this->capture->id,
                    'updated_fields' => array_keys($updateData),
                    'title' => $updateData['title'] ?? null,
                    'tags' => $updateData['tags'] ?? null,
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
