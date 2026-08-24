<?php

namespace App\Services;

use App\Models\Remix;

class RemixBlockService
{
    public function __construct(
        private readonly ContentGenerationService $generator,
    ) {}

    public function regenerate(Remix $remix, string $block, ?int $slideIndex): Remix
    {
        $content = $remix->generated_content;
        $text = $this->generator->regenerateBlock($remix, $block, $slideIndex);

        if ($block === 'slide') {
            $content['slides'][$slideIndex]['text'] = $text;
        } else {
            $content[$block] = $text;
        }

        $remix->update(['generated_content' => $content, 'status' => 'draft']);

        return $remix->fresh();
    }
}
