<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\LifeMoment;
use App\Models\Remix;
use App\Models\User;

interface ContentGenerationService
{
    /** @return array<string, mixed> */
    public function generate(ContentPost $source, User $user, string $format, ?LifeMoment $moment = null): array;

    public function regenerateBlock(Remix $remix, string $block, ?int $slideIndex = null): string;
}
