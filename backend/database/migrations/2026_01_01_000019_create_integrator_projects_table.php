<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integrator_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('abstract')->nullable();
            $table->foreignId('advisor_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('integrator_project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integrator_project_id')->constrained()->cascadeOnDelete();
            $table->string('student_code')->nullable();
            $table->string('student_name');
            $table->string('student_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrator_project_members');
        Schema::dropIfExists('integrator_projects');
    }
};
