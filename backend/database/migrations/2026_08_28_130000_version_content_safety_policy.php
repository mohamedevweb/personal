<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $table): void {
            $table->unsignedInteger('safety_policy_version')->default(0)->index();
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->unsignedInteger('safety_policy_version')->default(0)->index();
        });
    }

    public function down(): void
    {
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropColumn('safety_policy_version');
        });

        Schema::table('creators', function (Blueprint $table): void {
            $table->dropColumn('safety_policy_version');
        });
    }
};
