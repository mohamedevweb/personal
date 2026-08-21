<?php

namespace App\Services\Discovery;

use InvalidArgumentException;

class CreatorCatalog
{
    /** @return list<array<string, mixed>> */
    public function entries(): array
    {
        $path = (string) config('creator_catalog.manifest');

        if (! is_file($path)) {
            throw new InvalidArgumentException("Creator catalog manifest not found at [{$path}].");
        }

        $entries = require $path;

        if (! is_array($entries)) {
            throw new InvalidArgumentException('Creator catalog manifest must return an array.');
        }

        $this->validate($entries);

        return array_values($entries);
    }

    /** @return list<array<string, mixed>> */
    public function approved(): array
    {
        return array_values(array_filter($this->entries(), fn (array $entry): bool => $entry['status'] === 'approved'));
    }

    /** @param list<array<string, mixed>> $entries */
    public function validate(array $entries): void
    {
        $targetTotal = (int) config('creator_catalog.target_total');

        if (count($entries) !== $targetTotal) {
            throw new InvalidArgumentException("Creator catalog must contain exactly {$targetTotal} entries.");
        }

        $handles = array_map(fn (array $entry): string => strtolower(ltrim((string) ($entry['handle'] ?? ''), '@')), $entries);

        if (count(array_unique($handles)) !== $targetTotal || in_array('', $handles, true)) {
            throw new InvalidArgumentException('Creator catalog handles must be present and unique.');
        }

        $verticals = array_keys((array) config('creator_catalog.verticals'));
        $markets = (array) config('creator_catalog.markets');

        foreach ($entries as $entry) {
            foreach (['handle', 'instagram_url', 'market', 'vertical', 'topics', 'rationale', 'source_urls', 'editorially_verified_at', 'status'] as $key) {
                if (! array_key_exists($key, $entry)) {
                    throw new InvalidArgumentException("Creator catalog entry is missing [{$key}].");
                }
            }

            if (! preg_match('/^[a-z0-9._]{1,30}$/i', ltrim((string) $entry['handle'], '@'))
                || ! is_array($entry['topics']) || $entry['topics'] === []
                || ! is_array($entry['source_urls']) || count($entry['source_urls']) < 2
                || trim((string) $entry['rationale']) === '') {
                throw new InvalidArgumentException("Creator catalog entry [{$entry['handle']}] has incomplete editorial data.");
            }

            $instagramUrl = 'https://www.instagram.com/'.ltrim((string) $entry['handle'], '@').'/';
            if ($entry['instagram_url'] !== $instagramUrl || ! in_array($instagramUrl, $entry['source_urls'], true)) {
                throw new InvalidArgumentException("Creator catalog entry [{$entry['handle']}] must use its exact Instagram URL.");
            }

            if (! in_array($entry['vertical'], $verticals, true) || ! in_array($entry['market'], $markets, true) || $entry['market'] !== 'FR' || ! in_array($entry['status'], config('creator_catalog.statuses'), true)) {
                throw new InvalidArgumentException("Creator catalog entry [{$entry['handle']}] contains an unsupported classification.");
            }
        }

        foreach ($verticals as $vertical) {
            $group = array_values(array_filter($entries, fn (array $entry): bool => $entry['vertical'] === $vertical));
            $expected = (int) config('creator_catalog.target_per_vertical');
            if (count($group) !== $expected) {
                throw new InvalidArgumentException("Creator catalog [{$vertical}] requires {$expected} entries.");
            }
        }
    }
}
