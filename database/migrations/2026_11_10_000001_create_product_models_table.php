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
        Schema::create('product_models', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name', 80);
            $table->string('slug', 120)->unique();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['brand_id', 'category_id', 'is_active'], 'product_models_brand_category_active_idx');
        });

        DB::statement('CREATE UNIQUE INDEX product_models_brand_lower_name_unique ON product_models (brand_id, lower(name))');
        DB::statement("ALTER TABLE product_models ADD CONSTRAINT product_models_slug_format_check CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS product_models_brand_lower_name_unique');
        Schema::dropIfExists('product_models');
    }
};
