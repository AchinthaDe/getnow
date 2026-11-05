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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('show_in_nav')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        DB::statement('CREATE UNIQUE INDEX categories_parent_id_lower_name_unique ON categories (COALESCE(parent_id, 0), lower(name))');
        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_sort_order_check CHECK (sort_order >= 0)');
        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_parent_id_not_self_check CHECK (parent_id <> id)');
        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_slug_format_check CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
