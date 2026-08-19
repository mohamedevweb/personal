<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A shared ledger of when each hashtag was last scraped. Discovery skips a
        // hashtag still within its cooldown, so scraping cost scales with the number
        // of distinct niches across all users — not with the number of users.
        Schema::create('discovered_hashtags', function (Blueprint $table): void {
            $table->id();
            $table->string('tag')->unique();
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovered_hashtags');
    }
};
