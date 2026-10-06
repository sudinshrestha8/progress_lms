<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 – Question bank (design v2 §6, blueprint §5D).
 *
 * Tables: questions, question_versions, question_version_options, quiz_questions.
 *
 * - questions holds stable identity only; content lives in question_versions.
 * - question_versions is append-only (enforced by trigger in Phase 22).
 * - question_version_options are pure child rows of a version (CASCADE).
 * - quiz_questions references immutable question_versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id('question_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('created_by_teacher_id');
            $table->enum('status', ['ACTIVE', 'RETIRED'])->default('ACTIVE');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('subject_id', 'ix_questions_subject');
            $table->index('created_by_teacher_id', 'ix_questions_creator');

            $table->foreign('subject_id', 'fk_questions_subject')
                ->references('subject_id')
                ->on('subjects');

            $table->foreign('created_by_teacher_id', 'fk_questions_creator')
                ->references('teacher_id')
                ->on('teachers');
        });

        Schema::create('question_versions', function (Blueprint $table) {
            $table->id('question_version_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedSmallInteger('version_no')->default(1);
            $table->text('question_text');
            $table->enum('question_type', ['MCQ', 'MSQ', 'TRUE_FALSE', 'SHORT_ANSWER', 'ESSAY']);
            $table->decimal('default_points', 8, 2)->default(1.00);
            $table->enum('difficulty_level', ['EASY', 'MEDIUM', 'HARD'])->default('MEDIUM');
            $table->text('explanation')->nullable();
            $table->unsignedBigInteger('created_by_teacher_id');
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['question_id', 'version_no'], 'uq_question_versions_q_ver');
            $table->index('created_by_teacher_id', 'ix_question_versions_creator');

            $table->foreign('question_id', 'fk_question_versions_question')
                ->references('question_id')
                ->on('questions');

            $table->foreign('created_by_teacher_id', 'fk_question_versions_creator')
                ->references('teacher_id')
                ->on('teachers');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE question_versions
                ADD CONSTRAINT ck_question_versions_ver CHECK (version_no >= 1),
                ADD CONSTRAINT ck_question_versions_points CHECK (default_points >= 0)
            SQL);
        }

        Schema::create('question_version_options', function (Blueprint $table) {
            $table->id('option_id');
            $table->unsignedBigInteger('question_version_id');
            $table->char('option_key', 1);
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['question_version_id', 'option_key'], 'uq_qv_options_version_key');
            $table->unique(['question_version_id', 'display_order'], 'uq_qv_options_version_order');

            $table->foreign('question_version_id', 'fk_qv_options_version')
                ->references('question_version_id')
                ->on('question_versions')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE question_version_options
                ADD CONSTRAINT ck_qv_options_correct CHECK (is_correct IN (0, 1))
            SQL);
        }

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('quiz_id');
            $table->unsignedBigInteger('question_version_id');
            $table->unsignedSmallInteger('display_order');
            $table->decimal('question_points', 8, 2);
            $table->dateTime('created_at')->useCurrent();

            $table->primary(['quiz_id', 'question_version_id']);
            $table->unique(['quiz_id', 'display_order'], 'uq_quiz_questions_order');
            $table->index('question_version_id', 'ix_quiz_questions_version');

            $table->foreign('quiz_id', 'fk_quiz_questions_quiz')
                ->references('quiz_id')
                ->on('quizzes')
                ->cascadeOnDelete();

            $table->foreign('question_version_id', 'fk_quiz_questions_version')
                ->references('question_version_id')
                ->on('question_versions');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE quiz_questions
                ADD CONSTRAINT ck_quiz_questions_points CHECK (question_points >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('question_version_options');
        Schema::dropIfExists('question_versions');
        Schema::dropIfExists('questions');
    }
};
