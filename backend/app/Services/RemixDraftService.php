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

    public function start(
        ContentPost $source,
        User $user,
        string $format,
        ?LifeMoment $moment,
        string $locale,
    ): Remix {
        $remix = Remix::query()->create([
            'user_id' => $user->id,
            'source_content_id' => $source->id,
            'life_moment_id' => $moment?->id,
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
}
