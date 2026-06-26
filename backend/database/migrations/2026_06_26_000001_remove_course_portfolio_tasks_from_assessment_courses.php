<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $assessmentOfferingIds = DB::table('course_offerings')
            ->where('is_assessment_course', true)
            ->pluck('id');

        if ($assessmentOfferingIds->isEmpty()) {
            return;
        }

        DB::table('evidence_tasks')
            ->where('context_type', 'course_offering')
            ->whereIn('context_id', $assessmentOfferingIds)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data cleanup only. Assessment courses should not receive normal course portfolio tasks.
    }
};
