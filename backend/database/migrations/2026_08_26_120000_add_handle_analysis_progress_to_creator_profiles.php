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
            // What the onboarding loader reads: where the public-profile analysis
            // is, what it already found, and why it stopped when it did.
            $table->string('analysis_status', 32)->nullable()->index();
            $table->string('analysis_error', 64)->nullable();
            $table->timestamp('analysis_started_at')->nullable();
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedInteger('analyzed_posts_count')->nullable();
        });

        // Creators who gave their handle before this existed already went through
        // the analysis, so the loader must not send them back to a step they
        // finished.
        DB::table('creator_profiles')
            ->whereNotNull('instagram_username')
            ->update(['analysis_status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'analysis_status',
                'analysis_error',
                'analysis_started_at',
                'followers_count',
                'analyzed_posts_count',
            ]);
        });
    }
};
