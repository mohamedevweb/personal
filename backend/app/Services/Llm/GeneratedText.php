<?php

namespace App\Services\Llm;

final class GeneratedText
{
    public const STYLE_RULE = 'Never use em dashes or en dashes. Use commas, full stops, parentheses, or words instead.';

    public static function normalize(string $text): string
    {
        $text = str_replace(['‐', '‑'], '-', $text);

        return (string) preg_replace('/\s*[–—―]\s*/u', ', ', $text);
    }

    /** @param array<string|int, mixed> $values @return array<string|int, mixed> */
    public static function normalizeArray(array $values): array
    {
        return array_map(fn (mixed $value): mixed => match (true) {
            is_string($value) => self::normalize($value),
            is_array($value) => self::normalizeArray($value),
            default => $value,
        }, $values);
    }
}
