<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('instagram_user_id')->unique();
            $table->string('username');
            $table->string('display_name')->nullable();
            $table->text('bio')->nullable();
            $table->text('profile_picture_url')->nullable();
            $table->string('account_type')->nullable();
            $table->text('access_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('follows_count')->nullable();
            $table->unsignedInteger('media_count')->nullable();
            $table->string('sync_status')->default('connecting');
            $table->text('sync_error')->nullable();
            $table->timestamp('connected_at');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('instagram_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_account_id')->constrained()->cascadeOnDelete();
            $table->string('instagram_media_id')->unique();
            $table->string('media_type');
            $table->string('media_product_type')->nullable();
            $table->text('caption')->nullable();
            $table->text('permalink')->nullable();
            $table->text('media_url')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->unsignedBigInteger('like_count')->nullable();
            $table->unsignedBigInteger('comments_count')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('synced_at');
            $table->timestamps();
        });

        Schema::create('instagram_oauth_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('state_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('creator_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('instagram_username')->nullable();
            $table->string('display_name')->nullable();
            $table->text('bio')->nullable();
            $table->string('niche')->nullable();
            $table->text('audience_description')->nullable();
            $table->text('positioning')->nullable();
            $table->json('topics')->nullable();
            $table->json('tone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_profiles');
        Schema::dropIfExists('instagram_oauth_states');
        Schema::dropIfExists('instagram_media');
        Schema::dropIfExists('instagram_accounts');
    }
};
