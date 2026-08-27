<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_posts', function (Blueprint $table) {
            // The spoken script is permanent; video_url next to it is a signed CDN
            // link that expires within days and is refreshed at every measure.
            $table->text('transcript')->nullable()->after('video_url');
            $table->string('transcript_status', 20)->default('pending')->after('transcript');
            $table->timestamp('transcribed_at')->nullable()->after('transcript_status');
            $table->index(['transcript_status', 'format']);
        });
    }

    public function down(): void
    {
        Schema::table('content_posts', function (Blueprint $table) {
            $table->dropIndex(['transcript_status', 'format']);
            $table->dropColumn(['transcript', 'transcript_status', 'transcribed_at']);
        });
    }
};
