<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->string('analysis_locale', 2)->nullable()->after('structure_analysis');
            $table->json('analysis_translations')->nullable()->after('analysis_locale');
        });
    }

    public function down(): void
    {
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropColumn(['analysis_locale', 'analysis_translations']);
        });
    }
};
