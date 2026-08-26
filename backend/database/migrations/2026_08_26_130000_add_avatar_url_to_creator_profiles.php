<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table): void {
            // A creator who only gave their handle has no connected account to
            // take a picture from, so the one read off their public profile is
            // kept here. The URL is an Instagram CDN one and is only ever served
            // back through the signed media proxy.
            $table->text('avatar_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table): void {
            $table->dropColumn('avatar_url');
        });
    }
};
