<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dismissed_content', function (Blueprint $table): void {
            $table->string('reason', 20)->nullable()->after('content_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('dismissed_content', function (Blueprint $table): void {
            $table->dropColumn('reason');
        });
    }
};
