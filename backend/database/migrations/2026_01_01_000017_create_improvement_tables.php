<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accreditation_criterion_id')->nullable()->constrained('accreditation_criteria')->nullOnDelete();
            $table->foreignId('graduate_attribute_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('problem')->nullable();
            $table->text('root_cause')->nullable();
            $table->string('priority')->default('normal')->index();
            $table->string('status')->default('open')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['accreditation_cycle_id', 'status']);
        });

        Schema::create('improvement_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('improvement_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->decimal('progress', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('improvement_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('improvement_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary');
            $table->decimal('impact_value_before', 8, 2)->nullable();
            $table->decimal('impact_value_after', 8, 2)->nullable();
            $table->date('followup_date')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_followups');
        Schema::dropIfExists('improvement_actions');
        Schema::dropIfExists('improvement_plans');
    }
};
