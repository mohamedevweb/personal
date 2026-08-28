<?php

namespace App\Console\Commands;

use App\Models\ContentPost;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\ContentSafetyPolicy;
use Illuminate\Console\Command;

class EnforceContentSafetyPolicy extends Command
{
    protected $signature = 'personal:enforce-content-safety-policy
        {--limit=25 : Maximum posts to check in this pass}
        {--creator= : Restrict the pass to one creator handle}
        {--dry-run : Classify without writing decisions}';

    protected $description = 'Apply the current content safety policy to stored discovery posts';

    public function handle(ContentSafetyPolicy $policy): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $query = ContentPost::query()
            ->with('creator')
            ->where('safety_status', '!=', ContentSafetyDecision::BLOCKED)
            ->where('safety_policy_version', '<', ContentSafetyPolicy::VERSION)
            ->when($this->option('creator'), function ($query, string $username): void {
                $query->whereHas('creator', fn ($creator) => $creator->where('username', $username));
            })
            ->orderByDesc('published_at')
            ->limit($limit);

        $checked = 0;
        $blocked = 0;
        $pending = 0;

        foreach ($query->get() as $post) {
            $decision = $policy->storedPost($post);
            $checked++;
            $blocked += (int) ($decision->status === ContentSafetyDecision::BLOCKED);
            $pending += (int) ($decision->status === ContentSafetyDecision::PENDING);

            if ($this->option('dry-run')) {
                continue;
            }

            $attributes = [
                'safety_status' => $decision->status,
                'safety_reasons' => $decision->reasons,
                'safety_checked_at' => now(),
                'safety_policy_version' => $decision->status === ContentSafetyDecision::PENDING
                    ? $post->safety_policy_version
                    : ContentSafetyPolicy::VERSION,
            ];

            if ($decision->status !== ContentSafetyDecision::ALLOWED) {
                $attributes += [
                    'measured_at' => null,
                    'outlier_score' => 0,
                    'performance_ratio' => 0,
                    'engagement_rate' => 0,
                ];
            }

            $post->forceFill($attributes)->save();
        }

        $mode = $this->option('dry-run') ? 'Dry run' : 'Policy pass';
        $this->info("{$mode}: {$checked} checked, {$blocked} blocked, {$pending} pending.");

        return self::SUCCESS;
    }
}
