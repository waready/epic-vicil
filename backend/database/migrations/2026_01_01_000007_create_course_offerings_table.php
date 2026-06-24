<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('section')->nullable();
            $table->string('group_code')->nullable();
            $table->unsignedSmallInteger('enrolled_count')->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['program_id', 'academic_term_id']);
            $table->index(['course_id', 'academic_term_id']);
        });

        Schema::create('course_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('main');
            $table->decimal('weekly_hours', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['course_offering_id', 'teacher_id', 'role'], 'course_assignments_unique');
            $table->index(['teacher_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_assignments');
        Schema::dropIfExists('course_offerings');
    }
};
