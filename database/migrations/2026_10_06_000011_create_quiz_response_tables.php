<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 – Quiz responses (design v2 §6, blueprint §5E).
 *
 * Tables: quiz_attempts, student_answers, student_answer_options.
 *
 * - student_answers references immutable question_versions (frozen snapshot).
 * - student_answer_options is a pure junction table (composite PK).
 * - Validation that a selected option belongs to the answer's question version
 *   is enforced by trigger or application logic (Phase 22).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id('quiz_attempt_id');
            $table->unsignedBigInteger('quiz_id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('submitted_at')->nullable();
            $table->enum('status', ['IN_PROGRESS', 'SUBMITTED', 'TIMED_OUT', 'ABANDONED'])->default('IN_PROGRESS');
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['quiz_id', 'submission_id'], 'uq_quiz_attempts_quiz_sub');
            $table->unique(['quiz_id', 'attempt_number'], 'uq_quiz_attempts_quiz_att');
            $table->index('submission_id', 'ix_quiz_attempts_submission');

            $table->foreign('quiz_id', 'fk_quiz_attempts_quiz')
                ->references('quiz_id')
                ->on('quizzes');

            $table->foreign('submission_id', 'fk_quiz_attempts_submission')
                ->references('submission_id')
                ->on('submissions');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE quiz_attempts
                ADD CONSTRAINT ck_quiz_attempts_attempt CHECK (attempt_number >= 1),
                ADD CONSTRAINT ck_quiz_attempts_submitted CHECK (
                    submitted_at IS NULL OR submitted_at >= started_at
                )
            SQL);
        }

        Schema::create('student_answers', function (Blueprint $table) {
            $table->id('answer_id');
            $table->unsignedBigInteger('quiz_attempt_id');
            $table->unsignedBigInteger('question_version_id');
            $table->text('text_answer')->nullable();
            $table->decimal('marks_awarded', 8, 2)->nullable();
            $table->unsignedBigInteger('graded_by_user_id')->nullable();
            $table->dateTime('graded_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['quiz_attempt_id', 'question_version_id'], 'uq_student_answers_att_qv');
            $table->index('question_version_id', 'ix_student_answers_version');
            $table->index('graded_by_user_id', 'ix_student_answers_grader');

            $table->foreign('quiz_attempt_id', 'fk_student_answers_attempt')
                ->references('quiz_attempt_id')
                ->on('quiz_attempts');

            $table->foreign('question_version_id', 'fk_student_answers_version')
                ->references('question_version_id')
                ->on('question_versions');

            $table->foreign('graded_by_user_id', 'fk_student_answers_grader')
                ->references('user_id')
                ->on('users');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE student_answers
                ADD CONSTRAINT ck_student_answers_marks CHECK (
                    marks_awarded IS NULL OR marks_awarded >= 0
                ),
                ADD CONSTRAINT ck_student_answers_graded CHECK (
                    (graded_by_user_id IS NULL) = (graded_at IS NULL)
                )
            SQL);
        }

        Schema::create('student_answer_options', function (Blueprint $table) {
            $table->unsignedBigInteger('answer_id');
            $table->unsignedBigInteger('option_id');

            $table->primary(['answer_id', 'option_id']);
            $table->index('option_id', 'ix_student_answer_opts_option');

            $table->foreign('answer_id', 'fk_student_answer_opts_ans')
                ->references('answer_id')
                ->on('student_answers')
                ->cascadeOnDelete();

            $table->foreign('option_id', 'fk_student_answer_opts_opt')
                ->references('option_id')
                ->on('question_version_options');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_answer_options');
        Schema::dropIfExists('student_answers');
        Schema::dropIfExists('quiz_attempts');
    }
};
