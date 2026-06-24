<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accreditation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_model_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['accreditation_model_id', 'code']);
        });

        Schema::create('accreditation_subcriteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_criterion_id')->constrained('accreditation_criteria')->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['accreditation_criterion_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_subcriteria');
        Schema::dropIfExists('accreditation_criteria');
    }
};
