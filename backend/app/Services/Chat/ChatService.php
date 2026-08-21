<?php

namespace App\Services\Chat;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client as AnthropicClient;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use Throwable;

/**
 * The conversational assistant behind the in-app chat. It reuses whichever model
 * client is configured — mirroring the provider-selection rules of LlmJsonService —
 * and, like the rest of the product, degrades to a deterministic reply when no key
 * is present so the chat still answers in local development.
 */
class ChatService
{
    public function __construct(
        private readonly OpenAiClient $openai,
        private readonly AnthropicClient $anthropic,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages  Full turn history, oldest first.
     */
    public function reply(User $user, array $messages): string
    {
        return match (true) {
            $this->prefersOpenAi() => $this->viaOpenAi($user, $messages),
            (bool) config('services.anthropic.api_key') => $this->viaClaude($user, $messages),
            default => $this->fallback(),
        };
    }

    private function prefersOpenAi(): bool
    {
        // Match LlmJsonService: prefer OpenAI whenever it has a key, unless Claude is
        // the explicit driver and holds a key. A missing key drops to the fallback.
        if (config('services.content_generation.driver') === 'claude' && config('services.anthropic.api_key')) {
            return false;
        }

        return (bool) config('services.openai.api_key');
    }

    /** @param list<array{role: string, content: string}> $messages */
    private function viaOpenAi(User $user, array $messages): string
    {
        try {
            $response = $this->openai->responses()->create([
                'model' => (string) config('services.openai.model'),
                'instructions' => $this->system($user),
                'input' => $messages,
                'max_output_tokens' => 1200,
            ]);

            $text = trim((string) $response->outputText);

            return $text !== '' ? $text : $this->fallback();
        } catch (Throwable $exception) {
            Log::warning('Chat reply (OpenAI) failed; using fallback.', ['exception' => $exception]);

            return $this->fallback();
        }
    }

    /** @param list<array{role: string, content: string}> $messages */
    private function viaClaude(User $user, array $messages): string
    {
        try {
            $message = $this->anthropic->beta->messages->create(
                maxTokens: 1200,
                messages: $messages,
                model: (string) config('services.anthropic.model'),
                system: $this->system($user),
            );

            foreach ($message->content as $block) {
                if ($block instanceof BetaTextBlock && trim($block->text) !== '') {
                    return trim($block->text);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Chat reply (Claude) failed; using fallback.', ['exception' => $exception]);
        }

        return $this->fallback();
    }

    private function system(User $user): string
    {
        $name = trim((string) $user->name) ?: 'the creator';
        $language = app()->getLocale() === 'fr' ? 'natural French using informal tu' : 'English';

        return <<<PROMPT
        You are Personal's in-app creative assistant, helping {$name} turn their real
        life and expertise into standout short-form content. Be warm, concrete and
        brief. Offer specific hooks, angles, captions and formats rather than generic
        advice, and ask a sharpening question when the request is vague. Keep replies
        to a few short paragraphs and never invent metrics or facts about the user.
        Reply in {$language}.
        PROMPT;
    }

    private function fallback(): string
    {
        if (app()->getLocale() === 'fr') {
            return "Je ne suis pas encore relié à un modèle dans cet environnement, mais je peux t'aider à réfléchir. Raconte-moi le moment ou l'idée que tu veux transformer en contenu et je te proposerai quelques angles.";
        }

        return "I'm not fully wired up to a model in this environment yet, but I'm happy to help you think out loud. Tell me the moment or idea you want to turn into content and I'll suggest some angles.";
    }
}
