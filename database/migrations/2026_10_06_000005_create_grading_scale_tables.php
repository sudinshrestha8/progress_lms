<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 07 – Grading scales (design v2 §6, blueprint §5C).
 *
 * Tables: grading_scales, grading_scale_bands.
 * A published scale and its bands become immutable (trigger Phase 22).
 * Grade lookup: find the band with the greatest min_percentage ≤ score.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id('grading_scale_id');
            $table->string('scale_name', 100);
            $table->unsignedSmallInteger('version_no')->default(1);
            $table->enum('status', ['DRAFT', 'PUBLISHED', 'ARCHIVED'])->default('DRAFT');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['scale_name', 'version_no'], 'uq_grading_scales_name_ver');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE grading_scales
                ADD CONSTRAINT ck_grading_scales_published CHECK (
                    (status IN ('PUBLISHED','ARCHIVED')) = (published_at IS NOT NULL)
                ),
                ADD CONSTRAINT ck_grading_scales_version CHECK (version_no >= 1)
            SQL);
        }

        Schema::create('grading_scale_bands', function (Blueprint $table) {
            $table->id('band_id');
            $table->unsignedBigInteger('grading_scale_id');
            $table->string('grade_letter', 5);
            $table->decimal('min_percentage', 5, 2);
            $table->decimal('grade_point', 4, 2);
            $table->boolean('is_passing')->default(true);
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['grading_scale_id', 'grade_letter'], 'uq_grading_bands_scale_letter');
            $table->unique(['grading_scale_id', 'min_percentage'], 'uq_grading_bands_scale_min');

            $table->foreign('grading_scale_id', 'fk_grading_bands_scale')
                ->references('grading_scale_id')
                ->on('grading_scales')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
            ALTER TABLE grading_scale_bands
                ADD CONSTRAINT ck_grading_bands_min_pct CHECK (min_percentage BETWEEN 0.00 AND 100.00),
                ADD CONSTRAINT ck_grading_bands_grade_point CHECK (grade_point >= 0.00),
                ADD CONSTRAINT ck_grading_bands_is_passing CHECK (is_passing IN (0, 1))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scale_bands');
        Schema::dropIfExists('grading_scales');
    }
};
