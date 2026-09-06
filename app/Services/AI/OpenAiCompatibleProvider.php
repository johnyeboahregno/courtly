<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI-compatible chat-completions provider.
 *
 * Works against any OpenAI-compatible endpoint (OpenAI, Azure OpenAI,
 * Groq, OpenRouter, DeepSeek, Ollama, etc.) by POSTing to
 * `{base_url}/chat/completions` and requesting a JSON object back.
 */
class OpenAiCompatibleProvider implements AIProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $timeoutSeconds,
        private readonly int $maxTokens,
        private readonly float $temperature,
    ) {}

    public function generateStructuredResponse(
        string $systemPrompt,
        array $input,
        array $schema
    ): array {
        if ($this->baseUrl === '') {
            throw new AIProviderException('AI_BASE_URL is not configured.');
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt."\n\nRespond with a single JSON object only — no markdown, no commentary."],
                ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
            ],
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'response_format' => ['type' => 'json_object'],
        ];

        $request = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->contentType('application/json');

        if ($this->apiKey) {
            $request = $request->withToken($this->apiKey);
        }

        try {
            $response = $request->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new AIProviderException('AI provider connection failed: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new AIProviderException(
                'AI provider returned HTTP '.$response->status().': '.mb_substr($response->body(), 0, 400)
            );
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || $content === '') {
            throw new AIProviderException('AI provider returned an unexpected response shape.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            $decoded = $this->extractJsonObject($content);
        }

        return $this->validateAgainstSchema($decoded, $schema);
    }

    /**
     * Attempt to salvage a JSON object from a text response.
     */
    private function extractJsonObject(string $content): array
    {
        if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new AIValidationException('AI provider did not return valid JSON.');
    }

    /**
     * Minimal JSON Schema validation for the object shapes Courtly uses.
     *
     * Supports: type object/string/array/integer/number/boolean, `required`
     * top-level keys, and `properties` with `type` (+ `items.type` for arrays).
     */
    private function validateAgainstSchema(array $data, array $schema): array
    {
        if (($schema['type'] ?? null) === 'object' && ! $this->isAssociative($data)) {
            throw new AIValidationException('Expected a JSON object from the AI provider.');
        }

        foreach ($schema['required'] ?? [] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new AIValidationException("AI output is missing required field: {$key}");
            }
        }

        foreach ($schema['properties'] ?? [] as $key => $rules) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $expected = $rules['type'] ?? null;
            $value = $data[$key];

            match ($expected) {
                'string' => is_string($value)
                    ? null
                    : throw new AIValidationException("AI output field '{$key}' must be a string."),
                'object' => is_array($value) && $this->isAssociative($value)
                    ? null
                    : throw new AIValidationException("AI output field '{$key}' must be an object."),
                'integer' => is_int($value)
                    ? null
                    : throw new AIValidationException("AI output field '{$key}' must be an integer."),
                'number' => is_int($value) || is_float($value)
                    ? null
                    : throw new AIValidationException("AI output field '{$key}' must be a number."),
                'boolean' => is_bool($value)
                    ? null
                    : throw new AIValidationException("AI output field '{$key}' must be a boolean."),
                'array' => $this->validateArray($key, $value, $rules),
                default => null,
            };
        }

        return $data;
    }

    private function validateArray(string $key, mixed $value, array $rules): void
    {
        if (! is_array($value)) {
            throw new AIValidationException("AI output field '{$key}' must be an array.");
        }

        $itemType = $rules['items']['type'] ?? null;
        if ($itemType !== null) {
            foreach ($value as $item) {
                $valid = match ($itemType) {
                    'string' => is_string($item),
                    'number' => is_int($item) || is_float($item),
                    'integer' => is_int($item),
                    'boolean' => is_bool($item),
                    'object' => is_array($item),
                    default => true,
                };

                if (! $valid) {
                    throw new AIValidationException("AI output field '{$key}' must be an array of {$itemType}s.");
                }
            }
        }
    }

    private function isAssociative(array $data): bool
    {
        return array_keys($data) !== range(0, count($data) - 1);
    }
}
