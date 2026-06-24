<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evidence_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accreditation_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accreditation_criterion_id')->constrained('accreditation_criteria')->cascadeOnDelete();
            $table->foreignId('accreditation_subcriterion_id')->nullable()->constrained('accreditation_subcriteria')->nullOnDelete();
            $table->foreignId('evidence_requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_file_asset_id')->nullable()->constrained('file_assets')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('uploaded')->index();
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamp('validated_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['program_id', 'status']);
            $table->index(['accreditation_cycle_id', 'status']);
            $table->index(['accreditation_criterion_id', 'status'], 'submissions_criterion_status_idx');
            $table->index(['evidence_requirement_id', 'status'], 'submissions_requirement_status_idx');
            $table->index(['teacher_id', 'status']);
            $table->index(['submitted_by', 'status']);
        });

        Schema::create('evidence_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('change_summary')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['evidence_submission_id', 'version_number'], 'evidence_versions_number_unique');
            $table->index(['file_asset_id', 'version_number']);
            $table->index(['uploaded_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_versions');
        Schema::dropIfExists('evidence_submissions');
    }
};
