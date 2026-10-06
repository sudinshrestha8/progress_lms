<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 17 – Discussions (design v2 §6, blueprint §5G).
 *
 * Tables: discussion_threads, discussion_posts.
 *
 * - Replies (parent_post_id) must belong to the same thread.  This is
 *   enforced by trigger in Phase 22.
 * - discussion_posts.deleted_at is a tombstone marker for content moderation,
 *   not a Laravel soft-delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_threads', function (Blueprint $table) {
            $table->id('thread_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('author_user_id');
            $table->string('title', 300);
            $table->enum('status', ['OPEN', 'CLOSED', 'ARCHIVED'])->default('OPEN');
            $table->boolean('is_pinned')->default(false);
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['class_id', 'status', 'updated_at'], 'ix_threads_class_status');
            $table->index('author_user_id', 'ix_threads_author');

            $table->foreign('class_id', 'fk_threads_class')
                ->references('class_id')
                ->on('classes');

            $table->foreign('author_user_id', 'fk_threads_author')
                ->references('user_id')
                ->on('users');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE discussion_threads
                ADD CONSTRAINT ck_threads_title CHECK (CHAR_LENGTH(TRIM(title)) > 0),
                ADD CONSTRAINT ck_threads_pinned CHECK (is_pinned IN (0, 1))
            SQL);
        }

        Schema::create('discussion_posts', function (Blueprint $table) {
            $table->id('post_id');
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('author_user_id');
            $table->unsignedBigInteger('parent_post_id')->nullable();
            $table->text('body_content');
            $table->dateTime('edited_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['thread_id', 'created_at'], 'ix_posts_thread');
            $table->index('author_user_id', 'ix_posts_author');
            $table->index('parent_post_id', 'ix_posts_parent');

            $table->foreign('thread_id', 'fk_posts_thread')
                ->references('thread_id')
                ->on('discussion_threads');

            $table->foreign('author_user_id', 'fk_posts_author')
                ->references('user_id')
                ->on('users');

            $table->foreign('parent_post_id', 'fk_posts_parent')
                ->references('post_id')
                ->on('discussion_posts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_posts');
        Schema::dropIfExists('discussion_threads');
    }
};
