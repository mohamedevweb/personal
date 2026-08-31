<?php

namespace App\Services\Creator;

use App\Jobs\Discovery\AnalyzeCreatorHandle;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Services\Discovery\CanonicalCreatorVerticals;

class OnboardingCompletionService
{
    public function __construct(private readonly CanonicalCreatorVerticals $verticals) {}

    public function completeFor(
        User $user,
        ?InstagramAccount $account,
        ?CreatorProfile $profile,
    ): bool {
        if ($user->onboarding_completed_at) {
            return true;
        }

        if ($this->verticals->canonical($profile?->primary_vertical) === null) {
            return false;
        }

        $completed = $account
            ? $account->sync_status === 'completed'
            : $this->publicProfileIsComplete($profile);

        if ($completed) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return $completed;
    }

    private function publicProfileIsComplete(?CreatorProfile $profile): bool
    {
        if (! filled($profile?->instagram_username)) {
            return false;
        }

        $analysisRunning = in_array($profile?->analysis_status, [
            'queued',
            ...AnalyzeCreatorHandle::STAGES,
        ], true);

        // An analysis contract can be refreshed after onboarding. Existing DNA
        // proves the creator already cleared the gate, even while that refresh runs.
        return ! $analysisRunning || $profile?->dna_analyzed_at !== null;
    }
}
