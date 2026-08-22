<?php

namespace App\Services;

use App\Exceptions\ContentGenerationException;
use App\Models\ContentPost;
use App\Models\LifeMoment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Exceptions\ServerException;
use OpenAI\Exceptions\TransporterException;
use OpenAI\Exceptions\UnserializableResponse;
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Responses\Responses\Output\OutputMessage;
use OpenAI\Responses\Responses\Output\OutputMessageContentRefusal;

/**
 * Drafts content with OpenAI, through the Responses API with a strict JSON schema.
 * The prompt, the schema and the assembly of the final payload are shared with
 * every other provider — only the transport lives here.
 */
class OpenAiContentGenerationService implements ContentGenerationService
{
    public function __construct(
        private readonly ClientContract $client,
        private readonly ContentDraftBlueprint $blueprint,
        private readonly ContentDraftAssembler $assembler,
    ) {}

    public function generate(ContentPost $source, User $user, string $format, ?LifeMoment $moment = null): array
    {
        $response = $this->request(
            $this->blueprint->brief($source, $user, $format, $moment),
            $this->blueprint->schema($format),
        );

        $this->guardAgainstUnusableResponse($response);

        return $this->assembler->assemble($response->outputText, $source, $user, $format, $moment);
    }

    /** @param array<string, mixed> $schema */
    private function request(string $brief, array $schema): CreateResponse
    {
        $parameters = [
            'model' => (string) config('services.openai.remix_model'),
            'instructions' => $this->blueprint->system(),
            'input' => $brief,
            'max_output_tokens' => (int) config('services.openai.remix_max_output_tokens'),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'content_draft',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        // Only reasoning models accept this parameter, so it stays out of the
        // request unless it has been configured.
        if ($effort = config('services.openai.remix_reasoning_effort')) {
            $parameters['reasoning'] = ['effort' => $effort];
        }

        // The SDK's exceptions all extend Exception directly, so every failure mode
        // is named rather than swallowed behind a broad catch.
        try {
            return $this->client->responses()->create($parameters);
        } catch (
            ErrorException
            |RateLimitException
            |ServerException
            |TransporterException
            |UnserializableResponse $exception
        ) {
            Log::error('OpenAI content generation failed.', ['exception' => $exception]);

            throw new ContentGenerationException(
                'Personal could not draft this right now. Please try again in a moment.',
                previous: $exception,
            );
        }
    }

    private function guardAgainstUnusableResponse(CreateResponse $response): void
    {
        foreach ($response->output as $item) {
            if (! $item instanceof OutputMessage) {
                continue;
            }

            foreach ($item->content as $content) {
                if ($content instanceof OutputMessageContentRefusal) {
                    throw new ContentGenerationException(
                        'Personal declined to draft this. Try a different moment or format.'
                    );
                }
            }
        }

        // A truncated answer is not partially usable: the JSON will not parse, so
        // it is reported as a capacity problem rather than a malformed draft.
        if ($response->status === 'incomplete') {
            Log::warning('OpenAI content generation was cut short.', [
                'reason' => $response->incompleteDetails?->reason,
            ]);

            throw new ContentGenerationException(
                'Personal ran out of room drafting this. Try a shorter moment, or raise OPENAI_REMIX_MAX_OUTPUT_TOKENS.'
            );
        }
    }
}
