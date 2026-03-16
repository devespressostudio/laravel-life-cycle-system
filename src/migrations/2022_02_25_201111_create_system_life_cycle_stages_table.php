<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemLifeCycleStagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_life_cycle_stages', function (Blueprint $table) {
            $table->bigIncrements('internal_id');
            $table->ulid('id')->unique();
            $table->char('system_life_cycle_id', 26)->nullable();
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->string('name');
            $table->string('class');
            $table->timestamps();

            // Covers: WHERE system_life_cycle_id = ? ORDER BY sequence
            // and:    WHERE system_life_cycle_id = ? AND sequence > ? ORDER BY sequence
            $table->index(['system_life_cycle_id', 'sequence'], 'slcs_lc_sequence_idx');

            $table->foreign('system_life_cycle_id')
                ->references('id')
                ->on('system_life_cycles')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('system_life_cycle_stages');
    }
}
