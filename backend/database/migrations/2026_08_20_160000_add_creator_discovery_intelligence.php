<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->json('creator_dna')->nullable();
            $table->timestamp('dna_analyzed_at')->nullable();
            $table->json('discovery_queries')->nullable();
        });

        Schema::table('creators', function (Blueprint $table): void {
            $table->string('instagram_user_id')->nullable()->unique();
            $table->text('bio')->nullable();
            $table->json('metadata')->nullable();
            $table->json('performance_baselines')->nullable();
            $table->timestamp('discovered_at')->nullable()->index();
            $table->timestamp('last_fetched_at')->nullable()->index();
            $table->timestamp('metrics_updated_at')->nullable()->index();
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->string('instagram_media_id')->nullable()->unique();
            $table->unsignedBigInteger('shares')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('last_fetched_at')->nullable()->index();
            $table->timestamp('metrics_updated_at')->nullable()->index();
        });

        Schema::create('discovery_queries', function (Blueprint $table): void {
            $table->id();
            $table->string('query')->unique();
            $table->timestamp('last_searched_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('niches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('niches')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('creator_niches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('niche_id')->constrained()->cascadeOnDelete();
            $table->decimal('relevance_score', 5, 4)->default(1);
            $table->string('source')->default('analysis');
            $table->timestamps();
            $table->unique(['creator_id', 'niche_id']);
        });

        Schema::create('creator_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_creator_id')->constrained('creators')->cascadeOnDelete();
            $table->foreignId('related_creator_id')->constrained('creators')->cascadeOnDelete();
            $table->string('relationship_type');
            $table->decimal('relevance_score', 5, 4)->default(1);
            $table->timestamp('discovered_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['source_creator_id', 'related_creator_id', 'relationship_type'], 'creator_relationship_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_relationships');
        Schema::dropIfExists('creator_niches');
        Schema::dropIfExists('niches');
        Schema::dropIfExists('discovery_queries');

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropUnique(['instagram_media_id']);
            $table->dropColumn(['instagram_media_id', 'shares', 'metadata', 'last_fetched_at', 'metrics_updated_at']);
        });

        Schema::table('creators', function (Blueprint $table): void {
            $table->dropUnique(['instagram_user_id']);
            $table->dropColumn([
                'instagram_user_id',
                'bio',
                'metadata',
                'performance_baselines',
                'discovered_at',
                'last_fetched_at',
                'metrics_updated_at',
            ]);
        });

        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->dropColumn(['creator_dna', 'dna_analyzed_at', 'discovery_queries']);
        });
    }
};
