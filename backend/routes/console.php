<?php

use App\Jobs\SyncInstagramAccount;
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
