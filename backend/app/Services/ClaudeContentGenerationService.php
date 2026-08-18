<?php

namespace App\Services;

use Anthropic\Beta\Messages\BetaJSONOutputFormat;
use Anthropic\Beta\Messages\BetaOutputConfig;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Exceptions\ContentGenerationException;
use App\Models\ContentPost;
use App\Models\LifeMoment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Drafts content with Claude. The prompt, the schema and the assembly of the final
 * payload are shared with every other provider — only the transport lives here.
 */
class ClaudeContentGenerationService implements ContentGenerationService
{
    public function __construct(
        private readonly Client $client,
        private readonly ContentDraftBlueprint $blueprint,
        private readonly ContentDraftAssembler $assembler,
    ) {}

    public function generate(ContentPost $source, User $user, string $format, ?LifeMoment $moment = null): array
    {
        $text = $this->request(
            $this->blueprint->brief($source, $user, $format, $moment),
            $this->blueprint->schema($format),
        );

        return $this->assembler->assemble($text, $source, $user, $format, $moment);
    }

    /** @param array<string, mixed> $schema */
    private function request(string $brief, array $schema): ?string
    {
        try {
            $message = $this->client->beta->messages->create(
                maxTokens: (int) config('services.anthropic.max_tokens'),
                messages: [['role' => 'user', 'content' => $brief]],
                model: (string) config('services.anthropic.model'),
                // A policy decline is re-served by Anthropic's recommended fallback
                // model inside the same call instead of failing the request.
                fallbacks: 'default',
                outputConfig: BetaOutputConfig::with(
                    effort: (string) config('services.anthropic.effort'),
                    format: BetaJSONOutputFormat::with(schema: $schema),
                ),
                system: $this->blueprint->system(),
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (APIException $exception) {
            Log::error('Claude content generation failed.', ['exception' => $exception]);

            throw new ContentGenerationException(
                'Personal could not draft this right now. Please try again in a moment.',
                previous: $exception,
            );
        }

        if ($message->stopReason === 'refusal') {
            throw new ContentGenerationException(
                'Personal declined to draft this. Try a different moment or format.'
            );
        }

        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                return $block->text;
            }
        }

        return null;
    }
}
