<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table) {
            $table->json('current_projects')->nullable();
            $table->json('goals')->nullable();
            $table->json('content_strengths')->nullable();
        });

        Schema::create('creators', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('display_name');
            $table->text('avatar_url')->nullable();
            $table->string('niche');
            $table->unsignedBigInteger('followers');
            $table->unsignedBigInteger('average_views');
            $table->unsignedBigInteger('average_likes');
            $table->timestamps();
        });

        Schema::create('content_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $table->string('platform')->default('instagram');
            $table->string('format');
            $table->string('hook');
            $table->text('caption');
            $table->text('thumbnail_url')->nullable();
            $table->unsignedBigInteger('views');
            $table->unsignedBigInteger('likes');
            $table->unsignedBigInteger('comments');
            $table->timestamp('published_at')->index();
            $table->decimal('performance_ratio', 6, 2);
            $table->json('tags')->nullable();
            $table->text('why_it_works');
            $table->text('hook_analysis');
            $table->text('structure_analysis');
            $table->timestamps();
        });

        Schema::create('life_moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('category');
            $table->date('happened_at')->nullable();
            $table->date('upcoming_at')->nullable();
            $table->unsignedTinyInteger('story_score');
            $table->json('story_reasons')->nullable();
            $table->timestamps();
        });

        Schema::create('content_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('life_moment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('explanation');
            $table->unsignedTinyInteger('relevance_score');
            $table->string('origin')->default('trending_content');
            $table->timestamps();
        });

        Schema::create('saved_content', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'content_post_id']);
        });

        Schema::create('dismissed_content', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'content_post_id']);
        });

        Schema::create('remixes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_content_id')->constrained('content_posts')->cascadeOnDelete();
            $table->foreignId('life_moment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format');
            $table->json('generated_content');
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remixes');
        Schema::dropIfExists('dismissed_content');
        Schema::dropIfExists('saved_content');
        Schema::dropIfExists('content_opportunities');
        Schema::dropIfExists('life_moments');
        Schema::dropIfExists('content_posts');
        Schema::dropIfExists('creators');

        Schema::table('creator_profiles', function (Blueprint $table) {
            $table->dropColumn(['current_projects', 'goals', 'content_strengths']);
        });
    }
};
