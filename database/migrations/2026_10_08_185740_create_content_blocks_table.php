<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('placement', 64);
            $table->string('type', 64);
            $table->smallInteger('schema_version')->default(1);
            $table->jsonb('payload')->default('{}');
            $table->string('status', 16);
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['placement', 'status', 'is_enabled', 'sort_order']);
        });

        DB::statement('ALTER TABLE content_blocks ADD CONSTRAINT content_blocks_schema_version_check CHECK (schema_version > 0)');
        DB::statement("ALTER TABLE content_blocks ADD CONSTRAINT content_blocks_status_check CHECK (status IN ('draft', 'published', 'archived'))");
        DB::statement('ALTER TABLE content_blocks ADD CONSTRAINT content_blocks_sort_order_check CHECK (sort_order >= 0)');
        DB::statement('ALTER TABLE content_blocks ADD CONSTRAINT content_blocks_dates_check CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
    }
};
