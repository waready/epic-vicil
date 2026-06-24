<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accreditation_cycles', function (Blueprint $table) {
            if (! Schema::hasColumn('accreditation_cycles', 'academic_term_id')) {
                $table->foreignId('academic_term_id')
                    ->nullable()
                    ->after('program_id')
                    ->constrained('academic_terms')
                    ->nullOnDelete();
                $table->index(['program_id', 'academic_term_id', 'status'], 'accreditation_cycles_program_term_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accreditation_cycles', function (Blueprint $table) {
            if (Schema::hasColumn('accreditation_cycles', 'academic_term_id')) {
                $table->dropIndex('accreditation_cycles_program_term_status_idx');
                $table->dropConstrainedForeignId('academic_term_id');
            }
        });
    }
};
