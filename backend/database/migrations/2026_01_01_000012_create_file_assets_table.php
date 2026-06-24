<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('file_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 80)->default('public')->index();
            $table->string('path', 1024);
            $table->string('original_name', 512);
            $table->string('stored_name', 512)->nullable();
            $table->string('mime_type', 150)->nullable()->index();
            $table->string('extension', 30)->nullable()->index();
            $table->unsignedBigInteger('size_bytes')->default(0)->index();
            $table->string('checksum', 64)->nullable()->index();
            $table->string('visibility', 30)->default('private');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['disk', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_assets');
    }
};
