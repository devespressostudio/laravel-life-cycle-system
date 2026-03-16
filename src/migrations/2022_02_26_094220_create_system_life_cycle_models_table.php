<?php

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemLifeCycleModelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_life_cycle_models', function (Blueprint $table) {
            $table->bigIncrements('internal_id');
            $table->ulid('id')->unique();
            $table->char('system_life_cycle_id', 26)->index();
            match(config('systemLifeCycle.model_id_type', 'string')) {
                'integer' => $table->unsignedBigInteger('model_id'),
                'ulid'    => $table->char('model_id', 26),
                'uuid'    => $table->char('model_id', 36),
                default   => $table->string('model_id'),
            };
            $table->string('model_type');
            $table->string('status', 20)
                ->default(LifeCycleStatus::Pending->value);
            $table->char('system_life_cycle_stage_id', 26)->nullable();
            $table->string('batch', 50)->nullable();
            $table->longText('payload')->nullable();
            $table->dateTime('executes_at')->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            // Polymorphic model lookup
            $table->index(['model_id', 'model_type']);

            // Used when dispatching: WHERE status = ? AND batch = ?
            $table->index(['status', 'batch']);

            // Used by the clean-up command: WHERE status = 'completed' AND updated_at < ?
            $table->index(['status', 'updated_at'], 'slcm_status_updated_idx');

            // Used by the run command: whereNull('system_life_cycle_stage_id')
            $table->index('system_life_cycle_stage_id');

            $table->foreign('system_life_cycle_id')
                ->references('id')
                ->on('system_life_cycles')
                ->cascadeOnDelete();

            $table->foreign('system_life_cycle_stage_id')
                ->references('id')
                ->on('system_life_cycle_stages')
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
        Schema::dropIfExists('system_life_cycle_models');
    }
}
