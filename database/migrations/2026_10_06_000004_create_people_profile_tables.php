<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 06 – People profiles (design v2 §3, §6, blueprint §5B).
 *
 * Tables: teachers, teacher_office_hours, students, student_emergency_contacts.
 *
 * student_code and batch_seq defaults are placeholders. The BEFORE INSERT
 * trigger (trg_students_bi_assign_code, Phase 22) replaces them with the
 * database-generated immutable student ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id('teacher_id');
            $table->unsignedBigInteger('user_id')->unique('uq_teachers_user');
            $table->string('employee_code', 30)->unique('uq_teachers_employee_code');
            $table->unsignedBigInteger('department_id');
            $table->string('academic_title', 50)->nullable();
            $table->string('office_location', 100)->nullable();
            $table->enum('status', ['ACTIVE', 'ON_LEAVE', 'RETIRED'])->default('ACTIVE');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('department_id', 'ix_teachers_department');
            $table->index('status', 'ix_teachers_status');

            $table->foreign('user_id', 'fk_teachers_user')
                ->references('user_id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('department_id', 'fk_teachers_department')
                ->references('department_id')
                ->on('departments');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE teachers
                ADD CONSTRAINT ck_teachers_employee_code CHECK (CHAR_LENGTH(TRIM(employee_code)) > 0)
            SQL);
        }

        Schema::create('teacher_office_hours', function (Blueprint $table) {
            $table->id('office_hour_id');
            $table->unsignedBigInteger('teacher_id');
            $table->enum('day_of_week', ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY']);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('location', 100)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['teacher_id', 'day_of_week', 'starts_at'], 'uq_teacher_office_hours_slot');

            $table->foreign('teacher_id', 'fk_teacher_office_hours_teacher')
                ->references('teacher_id')
                ->on('teachers')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE teacher_office_hours
                ADD CONSTRAINT ck_teacher_office_hours_time CHECK (ends_at > starts_at)
            SQL);
        }

        Schema::create('students', function (Blueprint $table) {
            $table->id('student_id');
            $table->unsignedBigInteger('user_id')->unique('uq_students_user');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedInteger('batch_seq')->default(0);
            $table->string('student_code', 20)->default('')->unique('uq_students_code');
            $table->date('admitted_on')->nullable();
            $table->unsignedBigInteger('advisor_teacher_id')->nullable();
            $table->enum('status', ['ACTIVE', 'GRADUATED', 'WITHDRAWN', 'SUSPENDED'])->default('ACTIVE');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['batch_id', 'batch_seq'], 'uq_students_batch_seq');
            $table->index('advisor_teacher_id', 'ix_students_advisor');
            $table->index('status', 'ix_students_status');

            $table->foreign('user_id', 'fk_students_user')
                ->references('user_id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('batch_id', 'fk_students_batch')
                ->references('batch_id')
                ->on('batches');

            $table->foreign('advisor_teacher_id', 'fk_students_advisor')
                ->references('teacher_id')
                ->on('teachers')
                ->nullOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE students
                ADD CONSTRAINT ck_students_code CHECK (
                    student_code = ''
                    OR REGEXP_LIKE(student_code, '^[a-z]{2,10}[0-9]{4}-[0-9]{4,}$', 'c')
                )
            SQL);
        }

        Schema::create('student_emergency_contacts', function (Blueprint $table) {
            $table->id('contact_id');
            $table->unsignedBigInteger('student_id');
            $table->string('contact_name', 150);
            $table->string('relationship', 50);
            $table->string('phone_number', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('student_id', 'ix_emergency_contacts_student');

            $table->foreign('student_id', 'fk_emergency_contacts_student')
                ->references('student_id')
                ->on('students')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE student_emergency_contacts
                ADD CONSTRAINT ck_emergency_contacts_channel CHECK (phone_number IS NOT NULL OR email IS NOT NULL),
                ADD CONSTRAINT ck_emergency_contacts_name CHECK (CHAR_LENGTH(TRIM(contact_name)) > 0),
                ADD CONSTRAINT ck_emergency_contacts_primary CHECK (is_primary IN (0, 1))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_emergency_contacts');
        Schema::dropIfExists('students');
        Schema::dropIfExists('teacher_office_hours');
        Schema::dropIfExists('teachers');
    }
};
