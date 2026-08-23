<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->text('voice_profile')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->dropColumn('voice_profile');
        });
    }
};
