<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram CDN urls are signed and expire, so the media stored at discovery is
 * unusable a few days later. This records the last time a post's media was
 * refetched, which is both the freshness clock and the guard that stops a
 * provider credit being spent twice on the same post.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->timestamp('media_refreshed_at')->nullable()->after('media_urls');
        });
    }

    public function down(): void
    {
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropColumn('media_refreshed_at');
        });
    }
};
