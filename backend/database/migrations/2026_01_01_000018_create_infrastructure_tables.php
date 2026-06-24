<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('classroom')->index();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('location_reference')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('laboratories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('responsible_name')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_code')->nullable()->index();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->index();
            $table->date('acquired_on')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('software_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('license_type')->default('academic')->index();
            $table->string('provider')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable()->index();
            $table->unsignedInteger('seats')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->string('maintainable_type');
            $table->unsignedBigInteger('maintainable_id');
            $table->string('maintenance_type')->default('preventive')->index();
            $table->date('performed_on')->nullable()->index();
            $table->string('provider')->nullable();
            $table->text('description')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('status')->default('done')->index();
            $table->timestamps();

            $table->index(['maintainable_type', 'maintainable_id'], 'maintenance_morph_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('software_licenses');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('laboratories');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('campuses');
    }
};
