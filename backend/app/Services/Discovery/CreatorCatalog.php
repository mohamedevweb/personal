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
        if (count($entries) !== 120) {
            throw new InvalidArgumentException('Creator catalog must contain exactly 120 entries.');
        }

        $handles = array_map(fn (array $entry): string => strtolower(ltrim((string) ($entry['handle'] ?? ''), '@')), $entries);

        if (count(array_unique($handles)) !== 120 || in_array('', $handles, true)) {
            throw new InvalidArgumentException('Creator catalog handles must be present and unique.');
        }

        $verticals = array_keys((array) config('creator_catalog.verticals'));
        $markets = (array) config('creator_catalog.markets');
        $tiers = (array) config('creator_catalog.recognition_tiers');

        foreach ($entries as $entry) {
            foreach (['handle', 'market', 'vertical', 'topics', 'recognition_tier', 'rationale', 'status'] as $key) {
                if (! array_key_exists($key, $entry)) {
                    throw new InvalidArgumentException("Creator catalog entry is missing [{$key}].");
                }
            }

            if (! preg_match('/^[a-z0-9._]{1,30}$/i', ltrim((string) $entry['handle'], '@'))
                || ! is_array($entry['topics']) || $entry['topics'] === []
                || trim((string) $entry['rationale']) === '') {
                throw new InvalidArgumentException("Creator catalog entry [{$entry['handle']}] has incomplete editorial data.");
            }

            if (! in_array($entry['vertical'], $verticals, true) || ! in_array($entry['market'], $markets, true) || ! in_array($entry['recognition_tier'], $tiers, true) || ! in_array($entry['status'], config('creator_catalog.statuses'), true)) {
                throw new InvalidArgumentException("Creator catalog entry [{$entry['handle']}] contains an unsupported classification.");
            }
        }

        foreach ($verticals as $vertical) {
            $group = array_values(array_filter($entries, fn (array $entry): bool => $entry['vertical'] === $vertical));
            $this->assertCounts($group, 'market', ['FR' => 10, 'GB' => 5, 'US' => 5], $vertical);
            $this->assertCounts($group, 'recognition_tier', ['leader' => 4, 'established' => 10, 'expert' => 6], $vertical);
        }
    }

    private function assertCounts(array $entries, string $key, array $expected, string $vertical): void
    {
        $actual = array_count_values(array_column($entries, $key));

        foreach ($expected as $value => $count) {
            if (($actual[$value] ?? 0) !== $count) {
                throw new InvalidArgumentException("Creator catalog [{$vertical}] requires {$count} {$key} [{$value}].");
            }
        }
    }
}
