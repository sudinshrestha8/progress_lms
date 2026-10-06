<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 05 – Subject catalog (design v2 §6, blueprint §5B).
 *
 * Tables: subjects, program_subjects, subject_prerequisites, topics.
 * Topics use a self-referencing parent FK; same-subject enforcement
 * for parent topics is handled by a trigger in Phase 22.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id('subject_id');
            $table->unsignedBigInteger('department_id');
            $table->string('subject_code', 20)->unique('uq_subjects_code');
            $table->string('subject_title', 200);
            $table->decimal('credit_hours', 4, 1);
            $table->unsignedTinyInteger('lecture_hours')->default(0);
            $table->unsignedTinyInteger('lab_hours')->default(0);
            $table->enum('status', ['ACTIVE', 'RETIRED'])->default('ACTIVE');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('department_id', 'ix_subjects_department');
            $table->index('status', 'ix_subjects_status');

            $table->foreign('department_id', 'fk_subjects_department')
                ->references('department_id')
                ->on('departments');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE subjects
                ADD CONSTRAINT ck_subjects_credit_hours CHECK (credit_hours > 0),
                ADD CONSTRAINT ck_subjects_title CHECK (CHAR_LENGTH(TRIM(subject_title)) > 0)
            SQL);
        }

        Schema::create('program_subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('program_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedTinyInteger('semester_no');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('display_order')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->primary(['program_id', 'subject_id']);
            $table->index('subject_id', 'ix_program_subjects_subject');

            $table->foreign('program_id', 'fk_program_subjects_program')
                ->references('program_id')
                ->on('programs');

            $table->foreign('subject_id', 'fk_program_subjects_subject')
                ->references('subject_id')
                ->on('subjects');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE program_subjects
                ADD CONSTRAINT ck_program_subjects_semester CHECK (semester_no BETWEEN 1 AND 20),
                ADD CONSTRAINT ck_program_subjects_required CHECK (is_required IN (0, 1))
            SQL);
        }

        Schema::create('subject_prerequisites', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('prerequisite_subject_id');
            $table->string('minimum_grade', 5)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->primary(['subject_id', 'prerequisite_subject_id']);
            $table->index('prerequisite_subject_id', 'ix_subject_prereqs_prereq');

            $table->foreign('subject_id', 'fk_subject_prereqs_subject')
                ->references('subject_id')
                ->on('subjects');

            $table->foreign('prerequisite_subject_id', 'fk_subject_prereqs_prereq')
                ->references('subject_id')
                ->on('subjects');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE subject_prerequisites
                ADD CONSTRAINT ck_subject_prereqs_no_self CHECK (subject_id <> prerequisite_subject_id)
            SQL);
        }

        Schema::create('topics', function (Blueprint $table) {
            $table->id('topic_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('parent_topic_id')->nullable();
            $table->string('topic_name', 200);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['subject_id', 'display_order'], 'uq_topics_subject_order');
            $table->index('parent_topic_id', 'ix_topics_parent');

            $table->foreign('subject_id', 'fk_topics_subject')
                ->references('subject_id')
                ->on('subjects');

            $table->foreign('parent_topic_id', 'fk_topics_parent')
                ->references('topic_id')
                ->on('topics');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE topics
                ADD CONSTRAINT ck_topics_name CHECK (CHAR_LENGTH(TRIM(topic_name)) > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
        Schema::dropIfExists('subject_prerequisites');
        Schema::dropIfExists('program_subjects');
        Schema::dropIfExists('subjects');
    }
};
