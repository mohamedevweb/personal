<?php

namespace App\Services\Llm;

use Anthropic\Beta\Messages\BetaJSONOutputFormat;
use Anthropic\Beta\Messages\BetaOutputConfig;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client as AnthropicClient;
use Illuminate\Support\Facades\Log;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use Throwable;

/**
 * A single, best-effort JSON call to whichever language model is configured. It is
 * used by the auxiliary features (niche expansion, post analysis) that must never
 * break the request: every failure returns null so the caller falls back to a
 * heuristic. It deliberately does not share the strict content-draft pipeline.
 */
class LlmJsonService
{
    public function __construct(
        private readonly OpenAiClient $openai,
        private readonly AnthropicClient $anthropic,
    ) {}

    /**
     * @param  array<string, mixed>  $schema  JSON schema for the expected object.
     * @return array<string, mixed>|null
     */
    public function object(string $instructions, string $input, array $schema): ?array
    {
        return match (true) {
            $this->prefersOpenAi() => $this->viaOpenAi($instructions, $input, $schema),
            (bool) config('services.anthropic.api_key') => $this->viaClaude($instructions, $input, $schema),
            default => null,
        };
    }

    private function prefersOpenAi(): bool
    {
        // Default to OpenAI whenever it has a key; only prefer Claude when it is the
        // explicit driver. Either way a missing key drops through to the fallback.
        if (config('services.content_generation.driver') === 'claude' && config('services.anthropic.api_key')) {
            return false;
        }

        return (bool) config('services.openai.api_key');
    }

    /** @param array<string, mixed> $schema @return array<string, mixed>|null */
    private function viaOpenAi(string $instructions, string $input, array $schema): ?array
    {
        try {
            $parameters = [
                'model' => (string) config('services.openai.model'),
                'instructions' => $this->instructions($instructions),
                'input' => $input,
                'max_output_tokens' => (int) config('services.openai.analysis_max_output_tokens'),
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'result',
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
            ];

            if ($effort = config('services.openai.analysis_reasoning_effort')) {
                $parameters['reasoning'] = ['effort' => $effort];
            }

            $response = $this->openai->responses()->create($parameters);

            return $this->decode($response->outputText);
        } catch (Throwable $exception) {
            Log::warning('LLM JSON call (OpenAI) failed; using fallback.', ['exception' => $exception]);

            return null;
        }
    }

    /** @param array<string, mixed> $schema @return array<string, mixed>|null */
    private function viaClaude(string $instructions, string $input, array $schema): ?array
    {
        try {
            $message = $this->anthropic->beta->messages->create(
                maxTokens: 2000,
                messages: [['role' => 'user', 'content' => $input]],
                model: (string) config('services.anthropic.model'),
                outputConfig: BetaOutputConfig::with(
                    format: BetaJSONOutputFormat::with(schema: $schema),
                ),
                system: $this->instructions($instructions),
            );

            foreach ($message->content as $block) {
                if ($block instanceof BetaTextBlock) {
                    return $this->decode($block->text);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('LLM JSON call (Claude) failed; using fallback.', ['exception' => $exception]);
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function decode(?string $text): ?array
    {
        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? GeneratedText::normalizeArray($decoded) : null;
    }

    private function instructions(string $instructions): string
    {
        return rtrim($instructions)."\n\n".GeneratedText::STYLE_RULE;
    }
}
