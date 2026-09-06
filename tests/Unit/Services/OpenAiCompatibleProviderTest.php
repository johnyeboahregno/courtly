<?php

declare(strict_types=1);

use App\Services\AI\AIProviderException;
use App\Services\AI\AIValidationException;
use App\Services\AI\OpenAiCompatibleProvider;
use Illuminate\Support\Facades\Http;

function makeProvider(): OpenAiCompatibleProvider
{
    return new OpenAiCompatibleProvider(
        baseUrl: 'https://example.test/v1',
        apiKey: 'test-key',
        model: 'test-model',
        timeoutSeconds: 5,
        maxTokens: 100,
        temperature: 0.2,
    );
}

it('posts to the chat completions endpoint and parses JSON', function () {
    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode(['explanation' => 'A great match'])]],
            ],
        ], 200),
    ]);

    $result = makeProvider()->generateStructuredResponse(
        'system',
        ['players' => []],
        [
            'type' => 'object',
            'required' => ['explanation'],
            'properties' => ['explanation' => ['type' => 'string']],
        ],
    );

    expect($result['explanation'])->toBe('A great match');
    Http::assertSentCount(1);
});

it('throws AIProviderException on HTTP errors', function () {
    Http::fake(['*/chat/completions' => Http::response(['error' => 'bad request'], 500)]);

    expect(fn () => makeProvider()->generateStructuredResponse('sys', [], ['type' => 'object']))
        ->toThrow(AIProviderException::class);
});

it('throws AIValidationException when required fields are missing', function () {
    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['other' => 'x'])]]],
        ], 200),
    ]);

    expect(fn () => makeProvider()->generateStructuredResponse('sys', [], [
        'type' => 'object',
        'required' => ['explanation'],
    ]))->toThrow(AIValidationException::class);
});

it('throws AIValidationException when the content is not JSON', function () {
    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'plain text, no JSON']]],
        ], 200),
    ]);

    expect(fn () => makeProvider()->generateStructuredResponse('sys', [], ['type' => 'object']))
        ->toThrow(AIValidationException::class);
});

it('throws AIProviderException when no base URL is configured', function () {
    $provider = new OpenAiCompatibleProvider('', null, 'm', 5, 100, 0.2);

    expect(fn () => $provider->generateStructuredResponse('sys', [], ['type' => 'object']))
        ->toThrow(AIProviderException::class);
});
