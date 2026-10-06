<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 09 – Assessments (design v2 §6, blueprint §5D).
 *
 * Tables: assessments, assessment_exceptions, quizzes.
 *
 * - assessments uses composite FK (class_id, category_id) → grade_categories
 *   to guarantee the category belongs to the same class.
 * - assessments exposes UNIQUE (assessment_id, class_id) as a parent key for
 *   submissions and assessment_exceptions.
 * - assessment_exceptions uses two composite FKs: one into assessments (same
 *   class) and one into enrollments (enrolled student in that class).
 * - row_version is auto-incremented by the Phase 22 trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id('assessment_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('category_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('assessment_type', ['ASSIGNMENT', 'QUIZ', 'EXAM', 'PROJECT', 'PRESENTATION', 'LAB']);
            $table->decimal('max_marks', 8, 2);
            $table->dateTime('due_at');
            $table->dateTime('cutoff_at')->nullable();
            $table->decimal('late_penalty_pct_per_day', 5, 2)->default(0.00);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->enum('attempt_policy', ['LATEST', 'HIGHEST'])->default('LATEST');
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['assessment_id', 'class_id'], 'uq_assessments_id_class');
            $table->index(['class_id', 'is_published', 'due_at'], 'ix_assessments_class_pub_due');
            $table->index(['class_id', 'category_id'], 'ix_assessments_class_cat');

            $table->foreign(['class_id', 'category_id'], 'fk_assessments_grade_cat')
                ->references(['class_id', 'category_id'])
                ->on('grade_categories');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE assessments
                ADD CONSTRAINT ck_assessments_max_marks CHECK (max_marks > 0),
                ADD CONSTRAINT ck_assessments_cutoff CHECK (cutoff_at IS NULL OR cutoff_at >= due_at),
                ADD CONSTRAINT ck_assessments_penalty CHECK (late_penalty_pct_per_day BETWEEN 0.00 AND 100.00),
                ADD CONSTRAINT ck_assessments_attempts CHECK (max_attempts >= 1),
                ADD CONSTRAINT ck_assessments_published CHECK (is_published IN (0, 1)),
                ADD CONSTRAINT ck_assessments_row_version CHECK (row_version >= 1),
                ADD CONSTRAINT ck_assessments_title CHECK (CHAR_LENGTH(TRIM(title)) > 0)
            SQL);
        }

        Schema::create('assessment_exceptions', function (Blueprint $table) {
            $table->id('exception_id');
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('student_id');
            $table->dateTime('extended_due_at')->nullable();
            $table->dateTime('extended_cutoff_at')->nullable();
            $table->unsignedTinyInteger('extra_attempts')->default(0);
            $table->boolean('is_excused')->default(false);
            $table->text('reason')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['assessment_id', 'student_id'], 'uq_asmt_exceptions_asmt_stu');
            $table->index(['class_id', 'student_id'], 'ix_asmt_exceptions_class_stu');

            $table->foreign(['assessment_id', 'class_id'], 'fk_asmt_exceptions_assessment')
                ->references(['assessment_id', 'class_id'])
                ->on('assessments');

            $table->foreign(['class_id', 'student_id'], 'fk_asmt_exceptions_enrollment')
                ->references(['class_id', 'student_id'])
                ->on('enrollments');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE assessment_exceptions
                ADD CONSTRAINT ck_asmt_exceptions_excused CHECK (is_excused IN (0, 1)),
                ADD CONSTRAINT ck_asmt_exceptions_cutoff CHECK (
                    extended_cutoff_at IS NULL
                    OR extended_due_at IS NULL
                    OR extended_cutoff_at >= extended_due_at
                )
            SQL);
        }

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id('quiz_id');
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->decimal('pass_percentage', 5, 2)->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->char('access_code_hash', 64)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('assessment_id', 'uq_quizzes_assessment');

            $table->foreign('assessment_id', 'fk_quizzes_assessment')
                ->references('assessment_id')
                ->on('assessments');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE quizzes
                ADD CONSTRAINT ck_quizzes_time_limit CHECK (time_limit_minutes IS NULL OR time_limit_minutes > 0),
                ADD CONSTRAINT ck_quizzes_pass_pct CHECK (
                    pass_percentage IS NULL OR pass_percentage BETWEEN 0.00 AND 100.00
                ),
                ADD CONSTRAINT ck_quizzes_shuffle CHECK (shuffle_questions IN (0, 1))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('assessment_exceptions');
        Schema::dropIfExists('assessments');
    }
};
