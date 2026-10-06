<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module B (part 1) – Academic structure (design v2 §3, §6).
 * departments, programs, batches, batch_student_counters, academic_terms.
 * batch_code and the counter row are maintained by triggers (…_000017).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id('department_id');
            $table->string('department_code', 20)->unique('uq_departments_code');
            $table->string('department_name', 150)->unique('uq_departments_name');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE departments
                ADD CONSTRAINT ck_departments_code CHECK (REGEXP_LIKE(department_code, '^[a-z][a-z0-9_]{1,19}$', 'c'))
            SQL);
        }

        Schema::create('programs', function (Blueprint $table) {
            $table->id('program_id');
            $table->unsignedBigInteger('department_id');
            $table->string('program_code', 10)->unique('uq_programs_code');
            $table->string('program_name', 150);
            $table->unsignedTinyInteger('duration_terms');
            $table->decimal('total_credits', 5, 1)->nullable();
            $table->enum('status', ['ACTIVE', 'RETIRED'])->default('ACTIVE');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('department_id', 'ix_programs_department');

            $table->foreign('department_id', 'fk_programs_department')
                ->references('department_id')
                ->on('departments');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE programs
                ADD CONSTRAINT ck_programs_code CHECK (REGEXP_LIKE(program_code, '^[a-z]{2,10}$', 'c')),
                ADD CONSTRAINT ck_programs_duration CHECK (duration_terms BETWEEN 1 AND 20),
                ADD CONSTRAINT ck_programs_credits CHECK (total_credits IS NULL OR total_credits > 0)
            SQL);
        }

        Schema::create('batches', function (Blueprint $table) {
            $table->id('batch_id');
            $table->unsignedBigInteger('program_id');
            $table->unsignedSmallInteger('intake_year');
            $table->string('batch_code', 14)->unique('uq_batches_code');
            $table->enum('status', ['ACTIVE', 'CLOSED'])->default('ACTIVE');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['program_id', 'intake_year'], 'uq_batches_program_year');
            $table->index('intake_year', 'ix_batches_intake_year');

            $table->foreign('program_id', 'fk_batches_program')
                ->references('program_id')
                ->on('programs');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE batches
                ADD CONSTRAINT ck_batches_intake_year CHECK (intake_year BETWEEN 2000 AND 2100),
                ADD CONSTRAINT ck_batches_code CHECK (REGEXP_LIKE(batch_code, '^[a-z]{2,10}[0-9]{4}$', 'c'))
            SQL);
        }

        Schema::create('batch_student_counters', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id');
            $table->unsignedInteger('last_seq')->default(0);
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary('batch_id');

            $table->foreign('batch_id', 'fk_batch_student_counters_batch')
                ->references('batch_id')
                ->on('batches')
                ->cascadeOnDelete();
        });

        Schema::create('academic_terms', function (Blueprint $table) {
            $markerExpr = DB::connection()->getDriverName() === 'mysql'
                ? 'IF(is_current = 1, 1, NULL)'
                : 'CASE WHEN is_current = 1 THEN 1 ELSE NULL END';

            $table->id('term_id');
            $table->string('term_code', 20)->unique('uq_academic_terms_code');
            $table->string('term_name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->tinyInteger('current_marker')->storedAs($markerExpr)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('current_marker', 'uq_academic_terms_current');
            $table->index(['start_date', 'end_date'], 'ix_academic_terms_dates');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE academic_terms
                ADD CONSTRAINT ck_academic_terms_dates CHECK (end_date > start_date),
                ADD CONSTRAINT ck_academic_terms_is_current CHECK (is_current IN (0, 1))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('batch_student_counters');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('departments');
    }
};
