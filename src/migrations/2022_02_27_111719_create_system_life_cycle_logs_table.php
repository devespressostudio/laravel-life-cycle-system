<?php

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_life_cycle_logs', function (Blueprint $table) {
            $table->bigIncrements('internal_id');
            $table->ulid('id')->unique();
            $table->char('system_life_cycle_id', 26)->index();
            $table->char('system_life_cycle_stage_id', 26)->index();
            match(config('systemLifeCycle.model_id_type', 'string')) {
                'integer' => $table->unsignedBigInteger('model_id'),
                'ulid'    => $table->char('model_id', 26),
                'uuid'    => $table->char('model_id', 36),
                default   => $table->string('model_id'),
            };
            $table->string('model_type');
            $table->string('status', 20)
                ->default(LifeCycleStatus::Success->value)
                ->index();
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->longText('error')->nullable();
            $table->dateTime('created_at')->index();
            $table->dateTime('updated_at')->index();

            $table->index(['model_id', 'model_type']);

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

    public function down(): void
    {
        Schema::dropIfExists('system_life_cycle_logs');
    }
};
