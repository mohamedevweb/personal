<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $table) {
            // Average engagement rate across the account's recent posts:
            // (likes + comments) / followers * 100. Comparable across every
            // account, unlike a post's performance_ratio which is only
            // meaningful within its own creator.
            $table->decimal('avg_engagement_rate', 6, 2)->default(0)->index();
            // When the profile was last re-scraped, so measurement cost scales
            // with the number of tracked accounts, not the number of syncs.
            $table->timestamp('last_measured_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('creators', function (Blueprint $table) {
            $table->dropColumn(['avg_engagement_rate', 'last_measured_at']);
        });
    }
};
