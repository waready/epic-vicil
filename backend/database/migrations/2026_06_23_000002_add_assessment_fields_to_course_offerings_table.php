<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            if (! Schema::hasColumn('course_offerings', 'is_assessment_course')) {
                $table->boolean('is_assessment_course')->default(false)->index()->after('status');
            }

            if (! Schema::hasColumn('course_offerings', 'assessment_result_code')) {
                $table->string('assessment_result_code', 30)->nullable()->index()->after('is_assessment_course');
            }

            if (! Schema::hasColumn('course_offerings', 'assessment_result_name')) {
                $table->string('assessment_result_name')->nullable()->after('assessment_result_code');
            }

            if (! Schema::hasColumn('course_offerings', 'requires_assessment_video')) {
                $table->boolean('requires_assessment_video')->default(false)->index()->after('assessment_result_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $columns = [
                'requires_assessment_video',
                'assessment_result_name',
                'assessment_result_code',
                'is_assessment_course',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('course_offerings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
