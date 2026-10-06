<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module A – Identity & access (design v2 §6).
 * roles, permissions, role_permissions, users, user_roles, user_phones,
 * user_auth_tokens, stored_files.
 *
 * Supports dynamic role-based access control (RBAC). Core system roles
 * (super_admin, admin, teacher, student) are seeded with is_system=true,
 * while custom roles can be created, configured, and deleted dynamically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id('role_id');
            $table->string('role_code', 30)->unique('uq_roles_code');
            $table->string('role_name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('is_system', 'ix_roles_is_system');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE roles ADD CONSTRAINT ck_roles_code CHECK (REGEXP_LIKE(role_code, '^[a-z][a-z_]{1,29}$', 'c'))
            SQL);
        }

        DB::table('roles')->insert([
            ['role_code' => 'super_admin', 'role_name' => 'Super Administrator', 'description' => 'Full root access and complete system control', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['role_code' => 'admin',       'role_name' => 'Administrator',       'description' => 'General system and user administration', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['role_code' => 'teacher',     'role_name' => 'Teacher',             'description' => 'Requires a teachers profile row', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['role_code' => 'student',     'role_name' => 'Student',             'description' => 'Requires a students profile row', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('permissions', function (Blueprint $table) {
            $table->id('permission_id');
            $table->string('permission_code', 100)->unique('uq_permissions_code');
            $table->string('module', 50)->nullable();
            $table->string('description', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('module', 'ix_permissions_module');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE permissions ADD CONSTRAINT ck_permissions_code CHECK (REGEXP_LIKE(permission_code, '^[a-z][a-z_]*([.][a-z_]+)+$', 'c'))
            SQL);
        }

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->dateTime('created_at')->useCurrent();

            $table->primary(['role_id', 'permission_id']);
            $table->index('permission_id', 'ix_role_permissions_permission');

            $table->foreign('role_id', 'fk_role_permissions_role')
                ->references('role_id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->foreign('permission_id', 'fk_role_permissions_permission')
                ->references('permission_id')
                ->on('permissions')
                ->cascadeOnDelete();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('email', 255)->unique('uq_users_email');
            $table->string('password_hash', 255);
            $table->string('full_name', 150);
            $table->enum('status', ['ACTIVE', 'SUSPENDED', 'ANONYMIZED'])->default('ACTIVE');
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('anonymized_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('status', 'ix_users_status');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE users
                ADD CONSTRAINT ck_users_email CHECK (REGEXP_LIKE(email, '^[^@ ]+@[^@ ]+$')),
                ADD CONSTRAINT ck_users_full_name CHECK (CHAR_LENGTH(TRIM(full_name)) > 0),
                ADD CONSTRAINT ck_users_anonymized CHECK ((status = 'ANONYMIZED') = (anonymized_at IS NOT NULL))
            SQL);
        }

        Schema::create('user_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->dateTime('granted_at')->useCurrent();

            $table->primary(['user_id', 'role_id']);
            $table->index('role_id', 'ix_user_roles_role');

            $table->foreign('user_id', 'fk_user_roles_user')
                ->references('user_id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('role_id', 'fk_user_roles_role')
                ->references('role_id')
                ->on('roles')
                ->restrictOnDelete();
        });

        Schema::create('user_phones', function (Blueprint $table) {
            $markerExpr = DB::connection()->getDriverName() === 'mysql'
                ? 'IF(is_primary = 1, 1, NULL)'
                : 'CASE WHEN is_primary = 1 THEN 1 ELSE NULL END';

            $table->id('phone_id');
            $table->unsignedBigInteger('user_id');
            $table->string('phone_number', 20);
            $table->enum('phone_type', ['MOBILE', 'HOME', 'WORK'])->default('MOBILE');
            $table->tinyInteger('is_primary')->default(0);
            $table->tinyInteger('primary_marker')->storedAs($markerExpr)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['user_id', 'phone_number'], 'uq_user_phones_number');
            $table->unique(['user_id', 'primary_marker'], 'uq_user_phones_primary');

            $table->foreign('user_id', 'fk_user_phones_user')
                ->references('user_id')
                ->on('users')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE user_phones
                ADD CONSTRAINT ck_user_phones_number CHECK (REGEXP_LIKE(phone_number, '^[+]?[0-9]{6,15}$')),
                ADD CONSTRAINT ck_user_phones_is_primary CHECK (is_primary IN (0, 1))
            SQL);
        }

        Schema::create('user_auth_tokens', function (Blueprint $table) {
            $table->id('token_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('token_type', ['PASSWORD_RESET', 'EMAIL_VERIFICATION', 'API']);
            $table->char('token_hash', 64)->unique('uq_user_auth_tokens_hash');
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['user_id', 'token_type'], 'ix_user_auth_tokens_user');

            $table->foreign('user_id', 'fk_user_auth_tokens_user')
                ->references('user_id')
                ->on('users')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE user_auth_tokens
                ADD CONSTRAINT ck_user_auth_tokens_hash CHECK (REGEXP_LIKE(token_hash, '^[0-9a-f]{64}$', 'c')),
                ADD CONSTRAINT ck_user_auth_tokens_expiry CHECK (expires_at > created_at),
                ADD CONSTRAINT ck_user_auth_tokens_used CHECK (used_at IS NULL OR used_at >= created_at)
            SQL);
        }

        Schema::create('stored_files', function (Blueprint $table) {
            $table->id('file_id');
            $table->string('storage_disk', 50);
            $table->string('storage_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->unsignedBigInteger('uploaded_by');
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['storage_disk', 'storage_path'], 'uq_stored_files_location');
            $table->index('checksum_sha256', 'ix_stored_files_checksum');
            $table->index('uploaded_by', 'ix_stored_files_uploader');

            $table->foreign('uploaded_by', 'fk_stored_files_uploader')
                ->references('user_id')
                ->on('users')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE stored_files
                ADD CONSTRAINT ck_stored_files_checksum CHECK (REGEXP_LIKE(checksum_sha256, '^[0-9a-f]{64}$', 'c')),
                ADD CONSTRAINT ck_stored_files_name CHECK (CHAR_LENGTH(TRIM(original_name)) > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
        Schema::dropIfExists('user_auth_tokens');
        Schema::dropIfExists('user_phones');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
