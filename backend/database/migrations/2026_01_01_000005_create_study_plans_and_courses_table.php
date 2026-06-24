<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->year('year')->nullable();
            $table->date('approved_on')->nullable();
            $table->string('approval_document')->nullable();
            $table->boolean('is_current')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['program_id', 'code']);
            $table->index(['program_id', 'is_current']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_plan_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->unsignedTinyInteger('cycle_number')->nullable();
            $table->decimal('credits', 5, 2)->default(0);
            $table->unsignedSmallInteger('theory_hours')->default(0);
            $table->unsignedSmallInteger('practice_hours')->default(0);
            $table->unsignedSmallInteger('lab_hours')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['study_plan_id', 'code']);
            $table->index(['study_plan_id', 'cycle_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
        Schema::dropIfExists('study_plans');
    }
};
