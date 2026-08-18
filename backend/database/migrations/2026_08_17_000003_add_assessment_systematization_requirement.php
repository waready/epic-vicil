<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('course_offerings', 'requires_assessment_systematization')) {
            Schema::table('course_offerings', function (Blueprint $table) {
                $table->boolean('requires_assessment_systematization')
                    ->default(false)
                    ->index('course_offerings_assessment_systematization_idx');
            });
        }

        $modelId = DB::table('accreditation_models')
            ->where('code', 'ICACIT')
            ->value('id');

        if (! $modelId) {
            return;
        }

        $criterion = DB::table('accreditation_criteria')
            ->where('accreditation_model_id', $modelId)
            ->where('code', 'C3')
            ->first(['id']);

        if (! $criterion) {
            return;
        }

        $exists = DB::table('evidence_requirements')
            ->where('accreditation_criterion_id', $criterion->id)
            ->where('code', 'C3-ASS-05')
            ->exists();

        if ($exists) {
            return;
        }

        $subcriterionId = DB::table('accreditation_subcriteria')
            ->where('accreditation_criterion_id', $criterion->id)
            ->orderBy('order')
            ->value('id');
        $videoOrder = (int) DB::table('evidence_requirements')
            ->where('accreditation_criterion_id', $criterion->id)
            ->where('code', 'C3-ASS-04')
            ->value('order');
        $now = now();

        DB::table('evidence_requirements')->insert([
            'accreditation_criterion_id' => $criterion->id,
            'accreditation_subcriterion_id' => $subcriterionId,
            'code' => 'C3-ASS-05',
            'name' => 'Registro de sistematizacion de medicion de resultados del estudiante',
            'description' => 'Archivo Excel de sistematizacion de resultados del estudiante para el curso de medicion.',
            'applies_to' => 'assessment_course',
            'evidence_kind' => 'assessment',
            'is_required' => true,
            'allows_multiple_files' => false,
            'allowed_extensions' => json_encode(['xlsx', 'xls']),
            'order' => max(1, $videoOrder + 1),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('course_offerings', 'requires_assessment_systematization')) {
            return;
        }

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropIndex('course_offerings_assessment_systematization_idx');
            $table->dropColumn('requires_assessment_systematization');
        });
    }
};
