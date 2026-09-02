<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_catalog_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('url');
            $table->string('creator_username')->nullable();
            $table->string('vertical');
            $table->string('country_code', 2);
            $table->string('status')->default('queued')->index();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_catalog_imports');
    }
};
