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
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('kind', 32)->default('link');
            $table->ulid('category_public_id')->nullable();
            $table->string('url', 2048)->nullable()->change();
            $table->string('label', 80)->nullable()->change();

            $table->foreign('category_public_id')
                ->references('public_id')
                ->on('categories')
                ->restrictOnDelete();

            $table->index('category_public_id');
        });

        DB::statement("ALTER TABLE menu_items ADD CONSTRAINT menu_items_kind_check CHECK (kind IN ('link', 'category', 'category_tree'))");

        DB::statement("ALTER TABLE menu_items ADD CONSTRAINT menu_items_kind_shape_check CHECK (
            (kind = 'link' AND url IS NOT NULL AND category_public_id IS NULL AND label IS NOT NULL)
            OR
            (kind = 'category' AND url IS NULL AND category_public_id IS NOT NULL)
            OR
            (kind = 'category_tree' AND url IS NULL)
        )");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE menu_items DROP CONSTRAINT IF EXISTS menu_items_kind_shape_check');
        DB::statement('ALTER TABLE menu_items DROP CONSTRAINT IF EXISTS menu_items_kind_check');

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropForeign(['category_public_id']);
            $table->dropIndex(['category_public_id']);
        });

        // Rollback Data Loss: Permanent deletion of non-link rows and rows with null url/label
        // to permit altering url and label back to NOT NULL.
        // Because menu_items.parent_id has cascadeOnDelete(), deleting a typed parent item
        // automatically deletes any child items under it before column alteration.
        DB::table('menu_items')
            ->where('kind', '<>', 'link')
            ->orWhereNull('url')
            ->orWhereNull('label')
            ->delete();

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('url', 2048)->nullable(false)->change();
            $table->string('label', 80)->nullable(false)->change();
            $table->dropColumn(['category_public_id', 'kind']);
        });
    }
};
