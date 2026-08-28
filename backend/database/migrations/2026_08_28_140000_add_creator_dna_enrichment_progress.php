<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->string('media_enrichment_status', 32)->default('idle')->index();
            $table->string('media_enrichment_error', 64)->nullable();
            $table->timestamp('media_enrichment_started_at')->nullable();
            $table->timestamp('media_enrichment_completed_at')->nullable();
            $table->timestamp('analysis_stage_started_at')->nullable();
            $table->timestamp('analysis_completed_at')->nullable();
            $table->json('analysis_timings')->nullable();
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->timestamp('transcription_started_at')->nullable();
            $table->unsignedInteger('transcription_duration_ms')->nullable();
            $table->timestamp('carousel_analysis_started_at')->nullable();
            $table->unsignedInteger('carousel_analysis_duration_ms')->nullable();
        });

        // The old contract used the initial DNA status for the optional media
        // pass. Existing creators already have a usable caption-based DNA.
        DB::table('creator_profiles')
            ->where('analysis_status', 'transcribing_reels')
            ->update([
                'analysis_status' => 'completed',
                'media_enrichment_status' => 'processing',
            ]);
    }

    public function down(): void
    {
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropColumn([
                'transcription_started_at',
                'transcription_duration_ms',
                'carousel_analysis_started_at',
                'carousel_analysis_duration_ms',
            ]);
        });

        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'media_enrichment_status',
                'media_enrichment_error',
                'media_enrichment_started_at',
                'media_enrichment_completed_at',
                'analysis_stage_started_at',
                'analysis_completed_at',
                'analysis_timings',
            ]);
        });
    }
};
