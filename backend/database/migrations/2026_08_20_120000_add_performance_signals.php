<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $table): void {
            // Median (likes + comments) across the account's recent posts. Every one
            // of its posts is scored against this, so "outperforming" means beating
            // *this account*, not the scrape batch it happened to land in.
            $table->unsignedBigInteger('baseline_engagement')->default(0);
            // The topics the account itself covers, read from its bio and captions.
            // `niche` used to be whatever the discovering user's niche was, which
            // described the searcher rather than the creator.
            $table->json('niche_topics')->nullable();
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            // (likes + comments) / followers * 100 — comparable between a 20k and a
            // 2M account, unlike raw engagement.
            $table->decimal('engagement_rate', 8, 3)->default(0)->index();
            // Engagement over the creator's baseline. 1.0 is an ordinary post for
            // that account; the feed ranks on how far above 1.0 it lands.
            $table->decimal('outlier_score', 8, 2)->default(0)->index();
            // Null until the creator's baseline is known. A hashtag scrape cannot
            // score a post on its own, so those rows stay unmeasured and the feed
            // keeps them out of the ranked set.
            $table->timestamp('measured_at')->nullable()->index();
            // Discovery no longer invents a ratio at insert time, so the column
            // needs a default for rows written before their creator is measured.
            $table->decimal('performance_ratio', 6, 2)->default(0)->change();
            // Written by measurement and by the lazy LLM breakdown, never by the
            // hashtag scrape — which would otherwise blank an existing analysis
            // every time it rediscovered the same post.
            $table->text('why_it_works')->default('')->change();
            $table->text('hook_analysis')->default('')->change();
            $table->text('structure_analysis')->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('creators', function (Blueprint $table): void {
            $table->dropColumn(['baseline_engagement', 'niche_topics']);
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropColumn(['engagement_rate', 'outlier_score', 'measured_at']);
            $table->decimal('performance_ratio', 6, 2)->change();
            $table->text('why_it_works')->change();
            $table->text('hook_analysis')->change();
            $table->text('structure_analysis')->change();
        });
    }
};
