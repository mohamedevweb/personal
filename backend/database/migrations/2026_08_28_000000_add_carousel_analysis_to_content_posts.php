<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_posts', function (Blueprint $table) {
            $table->json('carousel_analysis')->nullable()->after('transcribed_at');
            $table->string('carousel_analysis_status', 20)->default('pending')->after('carousel_analysis');
            $table->timestamp('carousel_analyzed_at')->nullable()->after('carousel_analysis_status');
            $table->index(['carousel_analysis_status', 'format']);
        });
    }

    public function down(): void
    {
        Schema::table('content_posts', function (Blueprint $table) {
            $table->dropIndex(['carousel_analysis_status', 'format']);
            $table->dropColumn(['carousel_analysis', 'carousel_analysis_status', 'carousel_analyzed_at']);
        });
    }
};
