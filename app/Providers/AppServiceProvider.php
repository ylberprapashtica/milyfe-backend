<?php

namespace App\Providers;

use App\Contracts\AiMetadataGenerator;
use App\Services\Ai\DeepSeekMetadataGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind AI metadata generator interface to concrete implementation
        $this->app->bind(AiMetadataGenerator::class, function ($app) {
            $provider = config('services.ai.default_provider', 'deepseek');
            
            return match($provider) {
                'deepseek' => new DeepSeekMetadataGenerator(),
                // Add more providers here in the future:
                // 'openai' => new OpenAiMetadataGenerator(),
                // 'claude' => new ClaudeMetadataGenerator(),
                default => new DeepSeekMetadataGenerator(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
