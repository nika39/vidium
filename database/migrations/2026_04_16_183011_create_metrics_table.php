<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('p2p_bytes')->default(0);
            $table->unsignedBigInteger('http_bytes')->default(0);
            $table->timestamp('recorded_at');
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('player_version', 50)->nullable();

            $table->unique(
                ['site_id', 'recorded_at', 'browser', 'os', 'player_version'],
                'metrics_unique_bucket'
            );

            $table->index(['site_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
