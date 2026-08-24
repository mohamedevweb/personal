<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remixes', function (Blueprint $table): void {
            $table->unsignedInteger('copy_count')->default(0)->after('status');
            $table->timestamp('last_copied_at')->nullable()->after('copy_count');
        });
    }

    public function down(): void
    {
        Schema::table('remixes', function (Blueprint $table): void {
            $table->dropColumn(['copy_count', 'last_copied_at']);
        });
    }
};
