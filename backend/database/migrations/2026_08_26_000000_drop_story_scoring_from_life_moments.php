<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('life_moments', function (Blueprint $table) {
            $table->dropColumn(['story_score', 'story_reasons']);
        });
    }

    public function down(): void
    {
        Schema::table('life_moments', function (Blueprint $table) {
            $table->unsignedTinyInteger('story_score')->default(5);
            $table->json('story_reasons')->nullable();
        });
    }
};
