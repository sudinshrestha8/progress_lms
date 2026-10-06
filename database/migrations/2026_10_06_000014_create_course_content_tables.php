<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 16 – Course content (design v2 §6, blueprint §5G).
 *
 * Tables: course_folders, course_files.
 *
 * - course_folders uses a generated parent_folder_marker (COALESCE to 0 for
 *   NULL parents) so that UNIQUE enforces sibling-name uniqueness even among
 *   top-level folders (MySQL treats NULLs as distinct in UNIQUE).
 * - Same-class parent constraint for course_folders is enforced by trigger
 *   in Phase 22.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_folders', function (Blueprint $table) {
            $table->id('folder_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('parent_folder_id')->nullable();
            $table->string('folder_name', 200);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->unsignedBigInteger('parent_folder_marker')->storedAs('COALESCE(parent_folder_id, 0)');
            $table->unsignedBigInteger('created_by_user_id');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['class_id', 'parent_folder_marker', 'folder_name'], 'uq_course_folders_sibling');
            $table->index('parent_folder_id', 'ix_course_folders_parent');
            $table->index('created_by_user_id', 'ix_course_folders_creator');

            $table->foreign('class_id', 'fk_course_folders_class')
                ->references('class_id')
                ->on('classes')
                ->cascadeOnDelete();

            $table->foreign('parent_folder_id', 'fk_course_folders_parent')
                ->references('folder_id')
                ->on('course_folders');

            $table->foreign('created_by_user_id', 'fk_course_folders_creator')
                ->references('user_id')
                ->on('users');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE course_folders
                ADD CONSTRAINT ck_course_folders_name CHECK (CHAR_LENGTH(TRIM(folder_name)) > 0)
            SQL);
        }

        Schema::create('course_files', function (Blueprint $table) {
            $table->id('course_file_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('folder_id')->nullable();
            $table->unsignedBigInteger('file_id');
            $table->string('display_name', 200);
            $table->enum('status', ['DRAFT', 'PUBLISHED', 'ARCHIVED'])->default('DRAFT');
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('class_id', 'ix_course_files_class');
            $table->index('folder_id', 'ix_course_files_folder');
            $table->index('file_id', 'ix_course_files_file');
            $table->index('uploaded_by_user_id', 'ix_course_files_uploader');

            $table->foreign('class_id', 'fk_course_files_class')
                ->references('class_id')
                ->on('classes');

            $table->foreign('folder_id', 'fk_course_files_folder')
                ->references('folder_id')
                ->on('course_folders')
                ->nullOnDelete();

            $table->foreign('file_id', 'fk_course_files_file')
                ->references('file_id')
                ->on('stored_files');

            $table->foreign('uploaded_by_user_id', 'fk_course_files_uploader')
                ->references('user_id')
                ->on('users');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE course_files
                ADD CONSTRAINT ck_course_files_name CHECK (CHAR_LENGTH(TRIM(display_name)) > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_files');
        Schema::dropIfExists('course_folders');
    }
};
