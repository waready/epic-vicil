<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('graduate_attribute_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rubric_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_indicator_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 6, 2)->default(1);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('rubric_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('score', 8, 2);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('graduate_attribute_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_offering_id')->nullable()->constrained()->nullOnDelete();
            $table->string('measurement_type')->default('direct')->index(); // direct, indirect
            $table->string('name');
            $table->decimal('expected_level', 5, 2)->nullable();
            $table->decimal('achieved_level', 5, 2)->nullable();
            $table->string('status')->default('draft')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['accreditation_cycle_id', 'graduate_attribute_id']);
        });

        Schema::create('measurement_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_indicator_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sample_size')->default(0);
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->json('raw_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_results');
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('rubric_levels');
        Schema::dropIfExists('rubric_dimensions');
        Schema::dropIfExists('rubrics');
    }
};
