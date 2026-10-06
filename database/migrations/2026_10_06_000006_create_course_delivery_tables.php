<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 08 – Course delivery (design v2 §6, blueprint §5C).
 *
 * Tables: classes, class_staff, class_meetings, enrollments, grade_categories.
 *
 * - classes.row_version: auto-incremented by trigger (Phase 22).
 * - class state machine: DRAFT → ACTIVE → COMPLETED → ARCHIVED (trigger).
 * - class_staff: at most one primary teacher per class via generated marker.
 * - grade_categories exposes (class_id, category_id) for composite FK from assessments.
 * - enrollments exposes (class_id, student_id) for composite FK from submissions,
 *   attendance, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id('class_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('term_id');
            $table->unsignedBigInteger('grading_scale_id');
            $table->string('crn_code', 20)->unique('uq_classes_crn');
            $table->string('section_name', 50);
            $table->unsignedSmallInteger('max_capacity');
            $table->enum('status', ['DRAFT', 'ACTIVE', 'COMPLETED', 'ARCHIVED'])->default('DRAFT');
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('subject_id', 'ix_classes_subject');
            $table->index('term_id', 'ix_classes_term');
            $table->index('grading_scale_id', 'ix_classes_grading_scale');
            $table->index('status', 'ix_classes_status');

            $table->foreign('subject_id', 'fk_classes_subject')
                ->references('subject_id')
                ->on('subjects');

            $table->foreign('term_id', 'fk_classes_term')
                ->references('term_id')
                ->on('academic_terms');

            $table->foreign('grading_scale_id', 'fk_classes_grading_scale')
                ->references('grading_scale_id')
                ->on('grading_scales');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE classes
                ADD CONSTRAINT ck_classes_capacity CHECK (max_capacity > 0),
                ADD CONSTRAINT ck_classes_row_version CHECK (row_version >= 1),
                ADD CONSTRAINT ck_classes_section CHECK (CHAR_LENGTH(TRIM(section_name)) > 0)
            SQL);
        }

        Schema::create('class_staff', function (Blueprint $table) {
            $markerExpr = DB::connection()->getDriverName() === 'mysql'
                ? 'IF(is_primary = 1, 1, NULL)'
                : 'CASE WHEN is_primary = 1 THEN 1 ELSE NULL END';

            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('teacher_id');
            $table->enum('staff_role', ['INSTRUCTOR', 'CO_INSTRUCTOR', 'TA', 'GRADER'])->default('INSTRUCTOR');
            $table->boolean('is_primary')->default(false);
            $table->tinyInteger('primary_marker')->storedAs($markerExpr)->nullable();
            $table->dateTime('assigned_at')->useCurrent();
            $table->dateTime('created_at')->useCurrent();

            $table->primary(['class_id', 'teacher_id']);
            $table->unique(['class_id', 'primary_marker'], 'uq_class_staff_primary');
            $table->index('teacher_id', 'ix_class_staff_teacher');

            $table->foreign('class_id', 'fk_class_staff_class')
                ->references('class_id')
                ->on('classes')
                ->cascadeOnDelete();

            $table->foreign('teacher_id', 'fk_class_staff_teacher')
                ->references('teacher_id')
                ->on('teachers');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE class_staff
                ADD CONSTRAINT ck_class_staff_primary CHECK (is_primary IN (0, 1))
            SQL);
        }

        Schema::create('class_meetings', function (Blueprint $table) {
            $table->id('class_meeting_id');
            $table->unsignedBigInteger('class_id');
            $table->enum('day_of_week', ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY']);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room', 50)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['class_id', 'day_of_week', 'starts_at'], 'uq_class_meetings_slot');

            $table->foreign('class_id', 'fk_class_meetings_class')
                ->references('class_id')
                ->on('classes')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE class_meetings
                ADD CONSTRAINT ck_class_meetings_time CHECK (ends_at > starts_at)
            SQL);
        }

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id('enrollment_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('student_id');
            $table->enum('status', ['ENROLLED', 'DROPPED', 'COMPLETED'])->default('ENROLLED');
            $table->dateTime('enrolled_at')->useCurrent();
            $table->dateTime('dropped_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['class_id', 'student_id'], 'uq_enrollments_class_student');
            $table->index('student_id', 'ix_enrollments_student');
            $table->index(['class_id', 'status', 'student_id'], 'ix_enrollments_class_status');

            $table->foreign('class_id', 'fk_enrollments_class')
                ->references('class_id')
                ->on('classes');

            $table->foreign('student_id', 'fk_enrollments_student')
                ->references('student_id')
                ->on('students');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE enrollments
                ADD CONSTRAINT ck_enrollments_dropped CHECK (
                    dropped_at IS NULL OR status = 'DROPPED'
                )
            SQL);
        }

        Schema::create('grade_categories', function (Blueprint $table) {
            $table->id('category_id');
            $table->unsignedBigInteger('class_id');
            $table->string('category_name', 100);
            $table->decimal('weight_percentage', 5, 2);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['class_id', 'category_name'], 'uq_grade_categories_class_name');
            $table->unique(['class_id', 'display_order'], 'uq_grade_categories_class_order');
            $table->unique(['class_id', 'category_id'], 'uq_grade_categories_class_cat');

            $table->foreign('class_id', 'fk_grade_categories_class')
                ->references('class_id')
                ->on('classes')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE grade_categories
                ADD CONSTRAINT ck_grade_categories_weight CHECK (
                    weight_percentage > 0 AND weight_percentage <= 100
                ),
                ADD CONSTRAINT ck_grade_categories_name CHECK (CHAR_LENGTH(TRIM(category_name)) > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_categories');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('class_meetings');
        Schema::dropIfExists('class_staff');
        Schema::dropIfExists('classes');
    }
};
