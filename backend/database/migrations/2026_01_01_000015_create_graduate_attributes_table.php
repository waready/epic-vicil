<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('graduate_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_model_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['accreditation_model_id', 'code']);
        });

        Schema::create('attribute_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('graduate_attribute_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('expected_level', 5, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['graduate_attribute_id', 'is_active']);
        });

        Schema::create('course_attribute_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('graduate_attribute_id')->constrained()->cascadeOnDelete();
            $table->string('coverage_level')->default('introduced'); // introduced, reinforced, mastered
            $table->boolean('is_measured')->default(false)->index();
            $table->timestamps();

            $table->unique(['course_id', 'graduate_attribute_id'], 'course_attribute_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_attribute_mappings');
        Schema::dropIfExists('attribute_indicators');
        Schema::dropIfExists('graduate_attributes');
    }
};
