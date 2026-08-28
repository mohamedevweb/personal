<?php

namespace Tests\Unit;

use App\Services\Llm\GeneratedText;
use PHPUnit\Framework\TestCase;

class GeneratedTextTest extends TestCase
{
    public function test_it_removes_long_dashes_from_nested_generated_text(): void
    {
        $result = GeneratedText::normalizeArray([
            'positioning' => 'Clear positioning—with a concrete promise—for founders.',
            'topics' => ['Early‑career finance', 'Finance–crypto overlap'],
            'confidence' => 0.9,
        ]);

        $this->assertSame('Clear positioning, with a concrete promise, for founders.', $result['positioning']);
        $this->assertSame(['Early-career finance', 'Finance, crypto overlap'], $result['topics']);
        $this->assertSame(0.9, $result['confidence']);
    }
}
