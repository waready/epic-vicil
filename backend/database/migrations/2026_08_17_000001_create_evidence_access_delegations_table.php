<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_access_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegate_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['delegate_user_id', 'source_user_id'], 'ev_access_delegate_source_unique');
            $table->index(['delegate_user_id', 'expires_at'], 'ev_access_delegate_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_access_delegations');
    }
};
