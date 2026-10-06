<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 – Rubrics (design v2 §6, blueprint §5D).
 *
 * Tables: rubrics, rubric_criteria, rubric_levels.
 *
 * rubric_levels exposes UNIQUE (criterion_id, level_id) so that
 * rubric_evaluations can use a composite FK to prove level↔criterion
 * consistency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->id('rubric_id');
            $table->unsignedBigInteger('assessment_id')->unique('uq_rubrics_assessment');
            $table->string('title', 200);
            $table->decimal('total_points', 8, 2);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('assessment_id', 'fk_rubrics_assessment')
                ->references('assessment_id')
                ->on('assessments');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE rubrics
                ADD CONSTRAINT ck_rubrics_total_points CHECK (total_points > 0),
                ADD CONSTRAINT ck_rubrics_title CHECK (CHAR_LENGTH(TRIM(title)) > 0)
            SQL);
        }

        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id('criterion_id');
            $table->unsignedBigInteger('rubric_id');
            $table->string('criterion_name', 200);
            $table->text('description')->nullable();
            $table->decimal('max_points', 8, 2);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['rubric_id', 'criterion_name'], 'uq_rubric_criteria_rubric_name');
            $table->unique(['rubric_id', 'display_order'], 'uq_rubric_criteria_rubric_order');

            $table->foreign('rubric_id', 'fk_rubric_criteria_rubric')
                ->references('rubric_id')
                ->on('rubrics')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE rubric_criteria
                ADD CONSTRAINT ck_rubric_criteria_max_points CHECK (max_points > 0),
                ADD CONSTRAINT ck_rubric_criteria_name CHECK (CHAR_LENGTH(TRIM(criterion_name)) > 0)
            SQL);
        }

        Schema::create('rubric_levels', function (Blueprint $table) {
            $table->id('level_id');
            $table->unsignedBigInteger('criterion_id');
            $table->string('level_name', 100);
            $table->decimal('points', 8, 2);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['criterion_id', 'level_id'], 'uq_rubric_levels_crit_level');
            $table->unique(['criterion_id', 'display_order'], 'uq_rubric_levels_crit_order');
            $table->unique(['criterion_id', 'level_name'], 'uq_rubric_levels_crit_name');

            $table->foreign('criterion_id', 'fk_rubric_levels_criterion')
                ->references('criterion_id')
                ->on('rubric_criteria')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE rubric_levels
                ADD CONSTRAINT ck_rubric_levels_points CHECK (points >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_levels');
        Schema::dropIfExists('rubric_criteria');
        Schema::dropIfExists('rubrics');
    }
};
