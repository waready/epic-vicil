<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evidence_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->text('comment')->nullable();
            $table->string('from_status')->nullable()->index();
            $table->string('to_status')->index();
            $table->timestamps();

            $table->index(['evidence_submission_id', 'action']);
            $table->index(['reviewer_id', 'created_at']);
        });

        Schema::create('evidence_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status')->index();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['evidence_task_id', 'to_status']);
            $table->index(['evidence_submission_id', 'to_status'], 'history_submission_status_idx');
            $table->index(['changed_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_status_histories');
        Schema::dropIfExists('evidence_reviews');
    }
};
