<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 20)->nullable();
            $table->string('document_number', 30)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('highest_degree')->nullable();
            $table->string('specialty')->nullable();
            $table->string('employment_type')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('profile_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'document_type', 'document_number'], 'teachers_document_unique');
            $table->index(['institution_id', 'is_active']);
        });

        Schema::create('teacher_degrees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('degree_type');
            $table->string('degree_name');
            $table->string('institution_name')->nullable();
            $table->year('year')->nullable();
            $table->timestamps();
            $table->index(['teacher_id', 'degree_type']);
        });

        Schema::create('teacher_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('institution_name')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('hours', 8, 2)->nullable();
            $table->timestamps();
            $table->index(['teacher_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_trainings');
        Schema::dropIfExists('teacher_degrees');
        Schema::dropIfExists('teachers');
    }
};
