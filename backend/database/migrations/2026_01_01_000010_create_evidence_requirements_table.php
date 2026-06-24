<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evidence_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_criterion_id')->constrained('accreditation_criteria')->cascadeOnDelete();
            $table->foreignId('accreditation_subcriterion_id')->nullable()->constrained('accreditation_subcriteria')->nullOnDelete();
            $table->string('code', 80)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('applies_to')->default('program')->index();
            $table->string('evidence_kind')->default('record'); // normative, record, portfolio, report
            $table->boolean('is_required')->default(true)->index();
            $table->boolean('allows_multiple_files')->default(true);
            $table->json('allowed_extensions')->nullable();
            $table->unsignedSmallInteger('order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['accreditation_criterion_id', 'applies_to'], 'requirements_criterion_applies_idx');
            $table->index(['accreditation_subcriterion_id', 'is_required'], 'requirements_subcriterion_required_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_requirements');
    }
};
