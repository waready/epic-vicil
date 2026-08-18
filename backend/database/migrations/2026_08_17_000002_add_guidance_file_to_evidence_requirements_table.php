<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evidence_requirements', function (Blueprint $table) {
            $table->foreignId('guidance_file_asset_id')->nullable();
            $table->index('guidance_file_asset_id', 'ev_req_guidance_asset_idx');
            $table->foreign('guidance_file_asset_id', 'ev_req_guidance_asset_fk')
                ->references('id')
                ->on('file_assets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evidence_requirements', function (Blueprint $table) {
            $table->dropForeign('ev_req_guidance_asset_fk');
            $table->dropIndex('ev_req_guidance_asset_idx');
            $table->dropColumn('guidance_file_asset_id');
        });
    }
};
