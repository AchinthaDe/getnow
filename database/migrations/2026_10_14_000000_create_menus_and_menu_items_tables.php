<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('key', 64)->unique();
            $table->string('label', 80);
            $table->string('title', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label', 80);
            $table->string('url', 2048);
            $table->string('icon', 64)->nullable();
            $table->boolean('opens_in_new_tab')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });

        DB::statement('ALTER TABLE menu_items ADD CONSTRAINT menu_items_sort_order_check CHECK (sort_order >= 0)');

        // Provision the five code-owned menus idempotently
        $now = Carbon::now('UTC');
        $menus = [
            ['key' => 'main_nav', 'label' => 'Main Navigation'],
            ['key' => 'mobile_nav', 'label' => 'Mobile Navigation'],
            ['key' => 'footer_column_1', 'label' => 'Footer Column 1'],
            ['key' => 'footer_column_2', 'label' => 'Footer Column 2'],
            ['key' => 'footer_column_3', 'label' => 'Footer Column 3'],
        ];

        foreach ($menus as $menu) {
            $exists = DB::table('menus')->where('key', $menu['key'])->exists();
            if (! $exists) {
                DB::table('menus')->insert([
                    'public_id' => Str::lower((string) Str::ulid()),
                    'key' => $menu['key'],
                    'label' => $menu['label'],
                    'title' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
