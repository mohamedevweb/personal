<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The profile link (bio website) is one of the strongest niche signals, so
        // it is fetched and stored alongside the bio.
        Schema::table('instagram_accounts', function (Blueprint $table): void {
            $table->text('website')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_accounts', function (Blueprint $table): void {
            $table->dropColumn('website');
        });
    }
};
