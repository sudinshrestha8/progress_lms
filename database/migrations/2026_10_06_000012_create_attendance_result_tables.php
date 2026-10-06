<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 14 – Attendance and frozen results (design v2 §6, blueprint §5F).
 *
 * Tables: attendance_records, final_results.
 *
 * - attendance_records uses composite FK to enrollments (class_id, student_id).
 * - final_results is append-only; updates/deletes are rejected by trigger.
 *   The latest revision_no per class/student is the official result.
 * - GPA is never stored on the student; it is derived from final_results.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('student_id');
            $table->date('attendance_date');
            $table->unsignedBigInteger('class_meeting_id')->nullable();
            $table->enum('status', ['PRESENT', 'ABSENT', 'LATE', 'EXCUSED']);
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->dateTime('recorded_at')->useCurrent();
            $table->text('note')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['class_id', 'student_id', 'attendance_date'], 'uq_attendance_class_stu_date');
            $table->index(['class_id', 'attendance_date', 'status'], 'ix_attendance_class_date');
            $table->index(['student_id', 'attendance_date'], 'ix_attendance_student');
            $table->index('class_meeting_id', 'ix_attendance_meeting');
            $table->index('recorded_by_user_id', 'ix_attendance_recorder');

            $table->foreign(['class_id', 'student_id'], 'fk_attendance_enrollment')
                ->references(['class_id', 'student_id'])
                ->on('enrollments');

            $table->foreign('class_meeting_id', 'fk_attendance_meeting')
                ->references('class_meeting_id')
                ->on('class_meetings')
                ->nullOnDelete();

            $table->foreign('recorded_by_user_id', 'fk_attendance_recorder')
                ->references('user_id')
                ->on('users');
        });

        Schema::create('final_results', function (Blueprint $table) {
            $table->id('final_result_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedSmallInteger('revision_no')->default(1);
            $table->decimal('weighted_percentage', 5, 2);
            $table->string('grade_letter', 5);
            $table->decimal('grade_point', 4, 2);
            $table->decimal('credit_hours', 4, 1);
            $table->unsignedSmallInteger('class_rank')->nullable();
            $table->unsignedBigInteger('finalized_by_user_id');
            $table->dateTime('finalized_at')->useCurrent();
            $table->string('reason', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['class_id', 'student_id', 'revision_no'], 'uq_final_results_cls_stu_rev');
            $table->index(['student_id', 'class_id', 'revision_no'], 'ix_final_results_student');
            $table->index('finalized_by_user_id', 'ix_final_results_finalizer');

            $table->foreign(['class_id', 'student_id'], 'fk_final_results_enrollment')
                ->references(['class_id', 'student_id'])
                ->on('enrollments');

            $table->foreign('finalized_by_user_id', 'fk_final_results_finalizer')
                ->references('user_id')
                ->on('users');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE final_results
                ADD CONSTRAINT ck_final_results_pct CHECK (weighted_percentage BETWEEN 0.00 AND 100.00),
                ADD CONSTRAINT ck_final_results_gp CHECK (grade_point >= 0),
                ADD CONSTRAINT ck_final_results_credits CHECK (credit_hours > 0),
                ADD CONSTRAINT ck_final_results_revision CHECK (revision_no >= 1)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('final_results');
        Schema::dropIfExists('attendance_records');
    }
};
