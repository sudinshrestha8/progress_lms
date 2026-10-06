<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 15 – Analytics and risk rules (design v2 §6, blueprint §5F).
 *
 * Tables: risk_rules, at_risk_flags, teacher_notes, career_paths,
 *         career_path_subject_rules.
 *
 * - at_risk_flags uses a generated open_marker column to enforce at most
 *   one OPEN flag per rule/class/student.
 * - teacher_notes uses composite FK to enrollments.
 * - career_path_subject_rules is a pure junction with bounded percentages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_rules', function (Blueprint $table) {
            $table->id('risk_rule_id');
            $table->string('rule_code', 50)->unique('uq_risk_rules_code');
            $table->string('rule_name', 150);
            $table->enum('metric', ['ATTENDANCE_RATE', 'WEIGHTED_SCORE', 'MISSING_COUNT', 'FAILING_GRADE']);
            $table->enum('operator', ['LT', 'LTE', 'GT', 'GTE', 'EQ']);
            $table->decimal('threshold_value', 8, 2);
            $table->enum('risk_level', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('MEDIUM');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE risk_rules
                ADD CONSTRAINT ck_risk_rules_active CHECK (is_active IN (0, 1)),
                ADD CONSTRAINT ck_risk_rules_code CHECK (CHAR_LENGTH(TRIM(rule_code)) > 0)
            SQL);
        }

        Schema::create('at_risk_flags', function (Blueprint $table) {
            $openMarkerExpr = DB::connection()->getDriverName() === 'mysql'
                ? "IF(status = 'OPEN', 1, NULL)"
                : "CASE WHEN status = 'OPEN' THEN 1 ELSE NULL END";

            $table->id('risk_flag_id');
            $table->unsignedBigInteger('risk_rule_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('student_id');
            $table->enum('status', ['OPEN', 'ACKNOWLEDGED', 'RESOLVED', 'DISMISSED'])->default('OPEN');
            $table->decimal('detected_value', 8, 2);
            $table->dateTime('flagged_at')->useCurrent();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->tinyInteger('open_marker')->storedAs($openMarkerExpr)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['risk_rule_id', 'class_id', 'student_id', 'open_marker'], 'uq_risk_flags_open');
            $table->index(['class_id', 'status', 'risk_rule_id'], 'ix_risk_flags_class_status');
            $table->index('student_id', 'ix_risk_flags_student');

            $table->foreign('risk_rule_id', 'fk_risk_flags_rule')
                ->references('risk_rule_id')
                ->on('risk_rules');

            $table->foreign(['class_id', 'student_id'], 'fk_risk_flags_enrollment')
                ->references(['class_id', 'student_id'])
                ->on('enrollments');
        });

        Schema::create('teacher_notes', function (Blueprint $table) {
            $table->id('note_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('author_teacher_id');
            $table->text('note_text');
            $table->boolean('is_private_staff_only')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['class_id', 'student_id'], 'ix_teacher_notes_class_stu');
            $table->index('author_teacher_id', 'ix_teacher_notes_author');

            $table->foreign(['class_id', 'student_id'], 'fk_teacher_notes_enrollment')
                ->references(['class_id', 'student_id'])
                ->on('enrollments');

            $table->foreign('author_teacher_id', 'fk_teacher_notes_author')
                ->references('teacher_id')
                ->on('teachers');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE teacher_notes
                ADD CONSTRAINT ck_teacher_notes_private CHECK (is_private_staff_only IN (0, 1))
            SQL);
        }

        Schema::create('career_paths', function (Blueprint $table) {
            $table->id('career_path_id');
            $table->string('career_code', 50)->unique('uq_career_paths_code');
            $table->string('career_name', 200)->unique('uq_career_paths_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE career_paths
                ADD CONSTRAINT ck_career_paths_active CHECK (is_active IN (0, 1))
            SQL);
        }

        Schema::create('career_path_subject_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('career_path_id');
            $table->unsignedBigInteger('subject_id');
            $table->decimal('minimum_percentage', 5, 2)->default(0.00);
            $table->decimal('weight_percentage', 5, 2)->default(100.00);
            $table->boolean('is_required')->default(false);
            $table->dateTime('created_at')->useCurrent();

            $table->primary(['career_path_id', 'subject_id']);
            $table->index('subject_id', 'ix_career_rules_subject');

            $table->foreign('career_path_id', 'fk_career_rules_career')
                ->references('career_path_id')
                ->on('career_paths')
                ->cascadeOnDelete();

            $table->foreign('subject_id', 'fk_career_rules_subject')
                ->references('subject_id')
                ->on('subjects');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE career_path_subject_rules
                ADD CONSTRAINT ck_career_rules_min_pct CHECK (minimum_percentage BETWEEN 0.00 AND 100.00),
                ADD CONSTRAINT ck_career_rules_weight CHECK (weight_percentage BETWEEN 0.00 AND 100.00),
                ADD CONSTRAINT ck_career_rules_required CHECK (is_required IN (0, 1))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('career_path_subject_rules');
        Schema::dropIfExists('career_paths');
        Schema::dropIfExists('teacher_notes');
        Schema::dropIfExists('at_risk_flags');
        Schema::dropIfExists('risk_rules');
    }
};
