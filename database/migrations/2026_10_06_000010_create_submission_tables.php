<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 – Submissions and grading (design v2 §6, blueprint §5E).
 *
 * Tables: submissions, submission_files, submission_grades, rubric_evaluations.
 *
 * - submissions validates same-class via composite FK to assessments and
 *   enrollment via composite FK to enrollments.
 * - submission_grades.final_marks is a STORED generated column:
 *   GREATEST(0, raw_marks - IF(late_penalty_waived, 0, late_penalty_marks))
 * - rubric_evaluations validates level ↔ criterion via composite FK to
 *   rubric_levels (criterion_id, level_id).
 * - row_version on submission_grades auto-incremented by trigger (Phase 22).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id('submission_id');
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->text('submission_text')->nullable();
            $table->string('submission_url', 500)->nullable();
            $table->dateTime('submitted_at')->useCurrent();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['assessment_id', 'student_id', 'attempt_number'], 'uq_submissions_asmt_stu_att');
            $table->index(['class_id', 'student_id'], 'ix_submissions_class_student');

            $table->foreign(['assessment_id', 'class_id'], 'fk_submissions_assessment')
                ->references(['assessment_id', 'class_id'])
                ->on('assessments');

            $table->foreign(['class_id', 'student_id'], 'fk_submissions_enrollment')
                ->references(['class_id', 'student_id'])
                ->on('enrollments');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE submissions
                ADD CONSTRAINT ck_submissions_attempt CHECK (attempt_number >= 1)
            SQL);
        }

        Schema::create('submission_files', function (Blueprint $table) {
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('file_id');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->primary(['submission_id', 'file_id']);
            $table->index('file_id', 'ix_submission_files_file');

            $table->foreign('submission_id', 'fk_submission_files_submission')
                ->references('submission_id')
                ->on('submissions')
                ->cascadeOnDelete();

            $table->foreign('file_id', 'fk_submission_files_file')
                ->references('file_id')
                ->on('stored_files');
        });

        Schema::create('submission_grades', function (Blueprint $table) {
            $finalMarksExpr = DB::connection()->getDriverName() === 'mysql'
                ? 'GREATEST(0, raw_marks - IF(late_penalty_waived = 1, 0, late_penalty_marks))'
                : 'MAX(0, raw_marks - CASE WHEN late_penalty_waived = 1 THEN 0 ELSE late_penalty_marks END)';

            $table->id('grade_id');
            $table->unsignedBigInteger('submission_id')->unique('uq_submission_grades_sub');
            $table->decimal('raw_marks', 8, 2)->default(0.00);
            $table->decimal('late_penalty_marks', 8, 2)->default(0.00);
            $table->boolean('late_penalty_waived')->default(false);
            $table->decimal('final_marks', 8, 2)->storedAs($finalMarksExpr);
            $table->unsignedBigInteger('graded_by_user_id');
            $table->dateTime('graded_at')->useCurrent();
            $table->text('feedback')->nullable();
            $table->boolean('is_published')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['is_published', 'submission_id'], 'ix_submission_grades_published');
            $table->index(['graded_by_user_id', 'graded_at'], 'ix_submission_grades_grader');

            $table->foreign('submission_id', 'fk_submission_grades_sub')
                ->references('submission_id')
                ->on('submissions');

            $table->foreign('graded_by_user_id', 'fk_submission_grades_grader')
                ->references('user_id')
                ->on('users');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE submission_grades
                ADD CONSTRAINT ck_submission_grades_raw CHECK (raw_marks >= 0),
                ADD CONSTRAINT ck_submission_grades_penalty CHECK (late_penalty_marks >= 0),
                ADD CONSTRAINT ck_submission_grades_waived CHECK (late_penalty_waived IN (0, 1)),
                ADD CONSTRAINT ck_submission_grades_pub CHECK (is_published IN (0, 1)),
                ADD CONSTRAINT ck_submission_grades_pub_at CHECK (
                    (is_published = 1) = (published_at IS NOT NULL)
                ),
                ADD CONSTRAINT ck_submission_grades_row_ver CHECK (row_version >= 1)
            SQL);
        }

        Schema::create('rubric_evaluations', function (Blueprint $table) {
            $table->id('evaluation_id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('criterion_id');
            $table->unsignedBigInteger('level_id');
            $table->decimal('points_awarded', 8, 2);
            $table->text('comments')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['submission_id', 'criterion_id'], 'uq_rubric_evals_sub_crit');
            $table->index(['criterion_id', 'level_id'], 'ix_rubric_evals_level');

            $table->foreign('submission_id', 'fk_rubric_evals_submission')
                ->references('submission_id')
                ->on('submissions');

            $table->foreign(['criterion_id', 'level_id'], 'fk_rubric_evals_level')
                ->references(['criterion_id', 'level_id'])
                ->on('rubric_levels');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE rubric_evaluations
                ADD CONSTRAINT ck_rubric_evals_points CHECK (points_awarded >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_evaluations');
        Schema::dropIfExists('submission_grades');
        Schema::dropIfExists('submission_files');
        Schema::dropIfExists('submissions');
    }
};
