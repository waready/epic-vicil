<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accreditation_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('level')->nullable(); // internacional, nacional
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('accreditation_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('year')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('planning')->index();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['accreditation_model_id', 'program_id', 'year', 'name'], 'accreditation_cycles_unique');
            $table->index(['program_id', 'year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_cycles');
        Schema::dropIfExists('accreditation_models');
    }
};
