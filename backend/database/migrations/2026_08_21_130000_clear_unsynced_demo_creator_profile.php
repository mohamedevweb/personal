<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->demoProfileQuery()->update([
            'bio' => null,
            'niche' => null,
            'audience_description' => null,
            'positioning' => null,
            'topics' => json_encode([], JSON_THROW_ON_ERROR),
            'tone' => json_encode([], JSON_THROW_ON_ERROR),
            'current_projects' => json_encode([], JSON_THROW_ON_ERROR),
            'goals' => json_encode([], JSON_THROW_ON_ERROR),
            'content_strengths' => json_encode([], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('creator_profiles')
            ->whereIn('user_id', fn ($query) => $query
                ->select('id')
                ->from('users')
                ->where('email', 'creator@personal.local'))
            ->whereNull('niche')
            ->whereNull('audience_description')
            ->whereNull('positioning')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('instagram_accounts')
                ->whereColumn('instagram_accounts.user_id', 'creator_profiles.user_id')
                ->where('instagram_accounts.sync_status', 'completed'))
            ->update([
                'bio' => 'Building products for creators and sharing the founder journey.',
                'niche' => 'Entrepreneurship / SaaS',
                'audience_description' => 'Founders, creators and entrepreneurs building internet businesses.',
                'positioning' => 'Building products at the intersection of SaaS and the creator economy.',
                'topics' => json_encode(['Building products', 'Creator economy', 'Entrepreneurship', 'Founder journey'], JSON_THROW_ON_ERROR),
                'tone' => json_encode(['Direct', 'Personal', 'Transparent', 'Educational'], JSON_THROW_ON_ERROR),
                'current_projects' => json_encode(['Personal'], JSON_THROW_ON_ERROR),
                'goals' => json_encode(['Build a personal brand', 'Grow an audience', 'Launch Personal'], JSON_THROW_ON_ERROR),
                'content_strengths' => json_encode(['Founder stories', 'Personal lessons', 'Behind the scenes'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    private function demoProfileQuery(): Builder
    {
        return DB::table('creator_profiles')
            ->whereIn('user_id', fn ($query) => $query
                ->select('id')
                ->from('users')
                ->where('email', 'creator@personal.local'))
            ->where('niche', 'Entrepreneurship / SaaS')
            ->where('audience_description', 'Founders, creators and entrepreneurs building internet businesses.')
            ->where('positioning', 'Building products at the intersection of SaaS and the creator economy.')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('instagram_accounts')
                ->whereColumn('instagram_accounts.user_id', 'creator_profiles.user_id')
                ->where('instagram_accounts.sync_status', 'completed'));
    }
};
