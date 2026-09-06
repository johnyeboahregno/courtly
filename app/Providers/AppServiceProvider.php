<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AI\AIProviderInterface;
use App\Services\AI\OpenAiCompatibleProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function () {
            return new OpenAiCompatibleProvider(
                baseUrl: (string) config('courtly.ai.base_url', ''),
                apiKey: config('courtly.ai.api_key') ?: null,
                model: (string) config('courtly.ai.model', ''),
                timeoutSeconds: (int) config('courtly.ai.timeout_seconds', 30),
                maxTokens: (int) config('courtly.ai.max_tokens', 2000),
                temperature: (float) config('courtly.ai.temperature', 0.2),
            );
        });
    }
}
