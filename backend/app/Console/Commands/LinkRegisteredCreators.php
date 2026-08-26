<?php

namespace App\Console\Commands;

use App\Models\InstagramAccount;
use App\Services\Creator\RegisteredCreatorService;
use Illuminate\Console\Command;

class LinkRegisteredCreators extends Command
{
    protected $signature = 'personal:link-registered-creators';

    protected $description = 'Link synced Personal members to their public creator identities without provider calls';

    public function handle(RegisteredCreatorService $creators): int
    {
        $linked = 0;
        $skipped = 0;

        InstagramAccount::query()
            ->with(['media', 'user.creatorProfile'])
            ->eachById(function (InstagramAccount $account) use ($creators, &$linked, &$skipped): void {
                $profile = $account->user?->creatorProfile;

                if (! $profile) {
                    $skipped++;

                    return;
                }

                $creators->sync($account, $profile);
                $linked++;
            });

        $this->info("Linked {$linked} registered creators; skipped {$skipped} accounts without a profile.");

        return self::SUCCESS;
    }
}
