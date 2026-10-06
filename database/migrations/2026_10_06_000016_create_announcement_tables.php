<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18 – Announcements (design v2 §6, blueprint §5G).
 *
 * Tables: announcements, announcement_target_roles, announcement_target_programs,
 *         announcement_target_batches.
 *
 * Audience-type semantics (when audience_type = 'TARGETED'):
 *   - A target dimension with no rows = unrestricted on that dimension.
 *   - Populated dimensions combine with logical AND.
 *   - This design avoids Cartesian-product target rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id('announcement_id');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('sender_user_id');
            $table->string('title', 300);
            $table->text('content');
            $table->enum('priority', ['LOW', 'NORMAL', 'HIGH', 'URGENT'])->default('NORMAL');
            $table->enum('audience_type', ['ALL', 'CLASS', 'TARGETED'])->default('ALL');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['class_id', 'published_at'], 'ix_announcements_class');
            $table->index('sender_user_id', 'ix_announcements_sender');
            $table->index(['published_at', 'expires_at'], 'ix_announcements_pub_exp');

            $table->foreign('class_id', 'fk_announcements_class')
                ->references('class_id')
                ->on('classes')
                ->nullOnDelete();

            $table->foreign('sender_user_id', 'fk_announcements_sender')
                ->references('user_id')
                ->on('users');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE announcements
                ADD CONSTRAINT ck_announcements_expiry CHECK (
                    expires_at IS NULL OR published_at IS NULL OR expires_at > published_at
                ),
                ADD CONSTRAINT ck_announcements_title CHECK (CHAR_LENGTH(TRIM(title)) > 0)
            SQL);
        }

        Schema::create('announcement_target_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('role_id');

            $table->primary(['announcement_id', 'role_id']);
            $table->index('role_id', 'ix_ann_target_roles_role');

            $table->foreign('announcement_id', 'fk_ann_target_roles_ann')
                ->references('announcement_id')
                ->on('announcements')
                ->cascadeOnDelete();

            $table->foreign('role_id', 'fk_ann_target_roles_role')
                ->references('role_id')
                ->on('roles')
                ->cascadeOnDelete();
        });

        Schema::create('announcement_target_programs', function (Blueprint $table) {
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('program_id');

            $table->primary(['announcement_id', 'program_id']);
            $table->index('program_id', 'ix_ann_target_progs_prog');

            $table->foreign('announcement_id', 'fk_ann_target_progs_ann')
                ->references('announcement_id')
                ->on('announcements')
                ->cascadeOnDelete();

            $table->foreign('program_id', 'fk_ann_target_progs_prog')
                ->references('program_id')
                ->on('programs')
                ->cascadeOnDelete();
        });

        Schema::create('announcement_target_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('batch_id');

            $table->primary(['announcement_id', 'batch_id']);
            $table->index('batch_id', 'ix_ann_target_batches_batch');

            $table->foreign('announcement_id', 'fk_ann_target_batches_ann')
                ->references('announcement_id')
                ->on('announcements')
                ->cascadeOnDelete();

            $table->foreign('batch_id', 'fk_ann_target_batches_batch')
                ->references('batch_id')
                ->on('batches')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_target_batches');
        Schema::dropIfExists('announcement_target_programs');
        Schema::dropIfExists('announcement_target_roles');
        Schema::dropIfExists('announcements');
    }
};
