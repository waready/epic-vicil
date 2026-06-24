<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evidence_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accreditation_criterion_id')->constrained('accreditation_criteria')->cascadeOnDelete();
            $table->foreignId('accreditation_subcriterion_id')->nullable()->constrained('accreditation_subcriteria')->nullOnDelete();
            $table->foreignId('evidence_requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();

            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->string('priority')->default('normal')->index();
            $table->text('instructions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['accreditation_cycle_id', 'status']);
            $table->index(['program_id', 'accreditation_criterion_id', 'status'], 'evidence_tasks_program_criterion_status_idx');
            $table->index(['assigned_to', 'status']);
            $table->index(['context_type', 'context_id']);
            $table->index(['academic_year_id', 'academic_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_tasks');
    }
};
