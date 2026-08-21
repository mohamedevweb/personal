<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Storage;

class CreatorCatalogReportWriter
{
    /** @param list<array<string, mixed>> $rows @return array{json: string, csv: string} */
    public function write(string $prefix, array $rows, array $summary): array
    {
        $stamp = now()->format('Ymd-His');
        $directory = 'catalog-reports';
        $json = "{$directory}/{$prefix}-{$stamp}.json";
        $csv = "{$directory}/{$prefix}-{$stamp}.csv";

        Storage::disk('local')->put($json, json_encode([
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
            'entries' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        Storage::disk('local')->put($csv, $this->csv($rows));

        return ['json' => Storage::disk('local')->path($json), 'csv' => Storage::disk('local')->path($csv)];
    }

    /** @param list<array<string, mixed>> $rows */
    private function csv(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $handle = fopen('php://temp', 'r+');
        $columns = array_keys($rows[0]);
        fputcsv($handle, $columns);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn (mixed $value): mixed => is_array($value) ? implode('|', $value) : $value,
                array_replace(array_fill_keys($columns, null), $row),
            ));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }
}
