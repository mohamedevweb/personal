<?php

use App\Jobs\MeasureAccountEngagement;
use App\Jobs\SyncInstagramAccount;
use App\Models\Creator;
use App\Models\InstagramAccount;
use App\Models\InstagramOauthState;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    InstagramAccount::query()
        ->where(function ($query): void {
            $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<=', now()->subDay());
        })
        ->eachById(fn (InstagramAccount $account) => SyncInstagramAccount::dispatch($account->id));

    InstagramOauthState::query()
        ->where('expires_at', '<', now()->subDay())
        ->delete();
})->daily()->name('sync-instagram-accounts')->withoutOverlapping();

// Re-measure the accounts already in the pool. A baseline goes stale as a creator
// grows, and re-scraping a profile is also how the feed learns about their new
// posts between hashtag runs. The job re-checks the cooldown and caps the batch
// itself, so this cannot outspend DISCOVERY_MEASURE_BATCH per day.
Schedule::call(function (): void {
    $stale = Creator::query()
        ->when(config('creator_catalog.curated_only'), fn ($query) => $query->where('curation_status', 'approved'))
        ->where('safety_status', '!=', 'blocked')
        ->where(function ($query): void {
            $query->whereNull('last_measured_at')
                ->orWhere('safety_status', 'pending')
                ->orWhere('last_measured_at', '<', now()->subDays((int) config('services.discovery.measure_cooldown_days')));
        })
        ->orderByRaw('last_measured_at is not null, last_measured_at')
        ->limit((int) config('services.discovery.measure_batch'))
        ->pluck('username')
        ->all();

    if ($stale !== []) {
        MeasureAccountEngagement::dispatch($stale);
    }
})->daily()->name('measure-tracked-accounts')->withoutOverlapping();

Schedule::command('personal:prune-discovery-content')
    ->daily()
    ->name('prune-discovery-content')
    ->withoutOverlapping();
