<?php

declare(strict_types=1);

namespace App\Services\AI;

interface AIProviderInterface
{
    /**
     * Send structured input to the AI provider and receive validated structured output.
     *
     * @param  string  $systemPrompt  System-level instructions for the AI
     * @param  array   $input         Structured input data
     * @param  array   $schema        Expected JSON Schema for output validation
     * @return array                  Validated structured response
     *
     * @throws AIProviderException   On provider/network error
     * @throws AIValidationException On schema validation failure
     */
    public function generateStructuredResponse(
        string $systemPrompt,
        array $input,
        array $schema
    ): array;
}

class AIProviderException extends \RuntimeException {}
class AIValidationException extends \RuntimeException {}
