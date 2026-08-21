<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $table): void {
            $table->string('market', 2)->nullable()->index();
            $table->string('primary_language', 8)->default('unknown')->index();
            $table->string('curation_status')->default('discovered')->index();
            $table->string('recognition_tier')->nullable()->index();
            $table->boolean('is_catalog_seed')->default(false)->index();
        });

        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->string('market', 2)->nullable()->index();
            $table->decimal('market_confidence', 5, 4)->nullable();
            $table->string('primary_vertical')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->dropColumn(['market', 'market_confidence', 'primary_vertical']);
        });

        Schema::table('creators', function (Blueprint $table): void {
            $table->dropColumn(['market', 'primary_language', 'curation_status', 'recognition_tier', 'is_catalog_seed']);
        });
    }
};
