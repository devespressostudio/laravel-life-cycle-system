<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_life_cycles', function (Blueprint $table) {
            $table->bigIncrements('internal_id');
            $table->ulid('id')->unique();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->boolean('active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('activate_by_cron')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // Covers the full WHERE clause used by scopeWhereCanBeExecuted:
            // active = 1 AND activate_by_cron = 1 AND starts_at < now AND (ends_at IS NULL OR ends_at > now)
            $table->index(['active', 'activate_by_cron', 'starts_at', 'ends_at'], 'slc_cron_execution_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_life_cycles');
    }
};
