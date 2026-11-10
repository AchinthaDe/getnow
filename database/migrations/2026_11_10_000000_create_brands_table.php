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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_nav')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX brands_lower_name_unique ON brands (lower(name))');
        DB::statement("ALTER TABLE brands ADD CONSTRAINT brands_slug_format_check CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS brands_lower_name_unique');
        Schema::dropIfExists('brands');
    }
};
