<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'public_id')) {
                $table->ulid('public_id')->nullable();
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 16)->nullable();
            }
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $updates = [];
                if (empty($user->public_id)) {
                    $updates['public_id'] = (string) Str::ulid();
                }
                if (empty($user->role)) {
                    $updates['role'] = ($user->is_platform_admin ?? false) ? 'superadmin' : 'customer';
                }
                if ($updates !== []) {
                    DB::table('users')->where('id', $user->id)->update($updates);
                }
            }
        });

        DB::statement('ALTER TABLE users ALTER COLUMN public_id SET NOT NULL;');
        DB::statement('CREATE UNIQUE INDEX users_public_id_unique ON users (public_id);');
        DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'customer';");
        DB::statement('ALTER TABLE users ALTER COLUMN role SET NOT NULL;');
        DB::statement('CREATE INDEX users_role_index ON users (role);');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('superadmin', 'retailer', 'customer'));");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;');
        DB::statement('DROP INDEX IF EXISTS users_role_index;');
        DB::statement('DROP INDEX IF EXISTS users_public_id_unique;');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            $columnsToDrop = [];
            if (Schema::hasColumn('users', 'public_id')) {
                $columnsToDrop[] = 'public_id';
            }
            if (Schema::hasColumn('users', 'role')) {
                $columnsToDrop[] = 'role';
            }
            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
