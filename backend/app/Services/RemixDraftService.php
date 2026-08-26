<?php

namespace App\Services;

use App\Jobs\GenerateRemix;
use App\Models\ContentPost;
use App\Models\LifeMoment;
use App\Models\Remix;
use App\Models\User;

class RemixDraftService
{
    public function failIfStale(Remix $remix): Remix
    {
        $staleAfter = (int) config('services.content_generation.stale_after_seconds');

        if (
            $remix->status === 'generating'
            && $remix->updated_at?->lte(now()->subSeconds($staleAfter))
        ) {
            $remix->update(['status' => 'failed']);
        }

        return $remix;
    }

    /**
     * A user keeps one live draft per source, moment and format. Switching
     * shapes and coming back has to hand back the draft they already worked
     * on — regenerating it would throw their edits away and bill the tokens
     * again for a draft that already exists.
     */
    public function start(
        ContentPost $source,
        User $user,
        string $format,
        LifeMoment $moment,
        string $locale,
    ): Remix {
        $existing = $this->existingDraft($source, $user, $format, $moment);

        if ($existing) {
            return $existing->status === 'failed'
                ? $this->retry($existing, $locale)
                : $existing;
        }

        $remix = Remix::query()->create([
            'user_id' => $user->id,
            'source_content_id' => $source->id,
            'life_moment_id' => $moment->id,
            'format' => $format,
            'generated_content' => [],
            'status' => 'generating',
        ]);

        GenerateRemix::dispatch($remix->id, $locale);

        return $remix;
    }

    public function retry(Remix $remix, string $locale): Remix
    {
        $remix->update([
            'generated_content' => [],
            'status' => 'generating',
        ]);

        GenerateRemix::dispatch($remix->id, $locale);

        return $remix->fresh();
    }

    /**
     * Archived drafts are excluded: putting one away is how the user asks for
     * a fresh take on the same shape.
     */
    private function existingDraft(
        ContentPost $source,
        User $user,
        string $format,
        LifeMoment $moment,
    ): ?Remix {
        $query = $user->remixes()
            ->where('source_content_id', $source->id)
            ->where('format', $format)
            ->where('status', '!=', 'archived')
            ->latest('updated_at');

        $query->where('life_moment_id', $moment->id);

        $existing = $query->first();

        return $existing ? $this->failIfStale($existing) : null;
    }
}
