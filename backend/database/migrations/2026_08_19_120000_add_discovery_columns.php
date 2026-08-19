<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The niche hashtags an LLM derived for the creator, cached so discovery
        // does not pay for an expansion call on every run.
        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->json('discovery_hashtags')->nullable();
            $table->timestamp('discovery_refreshed_at')->nullable();
        });

        // Discovery upserts posts by their Instagram URL, so re-running the job
        // refreshes metrics instead of duplicating rows. Seeded benchmark posts
        // keep a null source_url.
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->string('source_url')->nullable()->unique()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->dropColumn(['discovery_hashtags', 'discovery_refreshed_at']);
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropUnique(['source_url']);
            $table->dropColumn('source_url');
        });
    }
};
