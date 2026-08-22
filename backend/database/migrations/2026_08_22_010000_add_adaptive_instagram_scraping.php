<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $table): void {
            $table->timestamp('last_scraped_at')->nullable()->index();
            // Existing and newly inserted creators start due. Every successful or
            // failed attempt then advances this non-null scheduling boundary.
            $table->timestamp('next_scrape_at')->useCurrent()->index();
            $table->timestamp('last_post_at')->nullable()->index();
            $table->decimal('scrape_priority', 5, 2)->default(0)->index();
            $table->string('scrape_status')->default('cold')->index();
            $table->unsignedSmallInteger('scrape_failures')->default(0);
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->string('tracking_status')->default('active')->index();
            $table->timestamp('last_metrics_scraped_at')->nullable()->index();
            $table->timestamp('next_metrics_scrape_at')->nullable()->index();
            $table->decimal('views_velocity', 16, 2)->default(0);
            $table->decimal('views_acceleration', 16, 4)->default(0);
            $table->decimal('metrics_growth_rate', 10, 4)->default(0);
            $table->timestamp('tracking_stopped_at')->nullable();
        });

        Schema::create('content_post_metric_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->unsignedBigInteger('views');
            $table->unsignedBigInteger('likes');
            $table->unsignedBigInteger('comments');
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('views_delta')->default(0);
            $table->decimal('elapsed_hours', 10, 4)->nullable();
            $table->decimal('views_velocity', 16, 2)->default(0);
            $table->decimal('views_acceleration', 16, 4)->default(0);

            $table->index(['content_post_id', 'captured_at'], 'post_metric_snapshot_timeline');
            $table->index('captured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_post_metric_snapshots');

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropColumn([
                'tracking_status',
                'last_metrics_scraped_at',
                'next_metrics_scrape_at',
                'views_velocity',
                'views_acceleration',
                'metrics_growth_rate',
                'tracking_stopped_at',
            ]);
        });

        Schema::table('creators', function (Blueprint $table): void {
            $table->dropColumn([
                'last_scraped_at',
                'next_scrape_at',
                'last_post_at',
                'scrape_priority',
                'scrape_status',
                'scrape_failures',
            ]);
        });
    }
};
