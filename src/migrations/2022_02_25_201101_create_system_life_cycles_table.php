<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemLifeCyclesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_life_cycles', function (Blueprint $table) {
            $table->bigIncrements('internal_id');
            $table->ulid('id')->unique();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->boolean('active')->default(1);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedTinyInteger('activate_by_cron')->default(1);
            $table->softDeletes();
            $table->timestamps();

            // Covers the full WHERE clause used by scopeWhereCanBeExecuted:
            // active = 1 AND activate_by_cron = 1 AND starts_at < now AND (ends_at IS NULL OR ends_at > now)
            $table->index(['active', 'activate_by_cron', 'starts_at', 'ends_at'], 'slc_cron_execution_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('system_life_cycles');
    }
}
