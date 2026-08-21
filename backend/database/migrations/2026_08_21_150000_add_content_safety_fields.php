<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $table): void {
            $table->string('safety_status')->default('allowed')->index();
            $table->json('safety_reasons')->nullable();
            $table->timestamp('safety_checked_at')->nullable();
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->string('safety_status')->default('allowed')->index();
            $table->json('safety_reasons')->nullable();
            $table->timestamp('safety_checked_at')->nullable();
        });

        // Existing catalogue rows predate this policy. Keep them out of the feed
        // until the normal measurement scheduler has checked them in bounded batches.
        DB::table('creators')->update(['safety_status' => 'pending']);
        DB::table('content_posts')->update(['safety_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropColumn(['safety_status', 'safety_reasons', 'safety_checked_at']);
        });

        Schema::table('creators', function (Blueprint $table): void {
            $table->dropColumn(['safety_status', 'safety_reasons', 'safety_checked_at']);
        });
    }
};
