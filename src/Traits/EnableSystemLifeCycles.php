<?php

namespace Devespresso\SystemLifeCycle\Traits;

use Devespresso\SystemLifeCycle\Contracts\LifeCycleStageContract;
use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycle;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait EnableSystemLifeCycles
{
    public function lifeCycles(): MorphMany
    {
        return $this->morphMany(SystemLifeCycleModel::class, 'model');
    }

    /**
     * Attach this model to a lifecycle. Idempotent — returns the existing record
     * if the model is already enrolled, regardless of which stage it is currently on.
     */
    public function addLifeCycleByCode(string $code): ?SystemLifeCycleModel
    {
        $id = SystemLifeCycle::where('code', $code)->value('id');

        if (!$id) {
            return null;
        }

        $stageId = SystemLifeCycleStage::where('system_life_cycle_id', $id)
            ->orderBy('sequence')
            ->value('id');

        return $this->lifeCycles()->firstOrCreate(
            ['system_life_cycle_id' => $id],
            ['system_life_cycle_stage_id' => $stageId]
        );
    }

    /**
     * Re-enroll this model in a lifecycle from the beginning.
     * If already enrolled, resets to the first stage with a clean slate.
     * If not enrolled, creates a fresh record.
     */
    public function reEnrollLifeCycle(string $code): ?SystemLifeCycleModel
    {
        $id = SystemLifeCycle::where('code', $code)->value('id');

        if (!$id) {
            return null;
        }

        $stageId = SystemLifeCycleStage::where('system_life_cycle_id', $id)
            ->orderBy('sequence')
            ->value('id');

        $existing = $this->lifeCycles()
            ->where('system_life_cycle_id', $id)
            ->first();

        if ($existing) {
            $existing->update([
                'system_life_cycle_stage_id' => $stageId,
                'status'                     => LifeCycleStatus::Pending,
                'attempts'                   => 0,
                'executes_at'                => null,
                'payload'                    => null,
                'batch'                      => null,
            ]);

            return $existing->fresh();
        }

        return $this->lifeCycles()->create([
            'system_life_cycle_id'       => $id,
            'system_life_cycle_stage_id' => $stageId,
        ]);
    }

    /**
     * Return the lifecycle model record for this model and the given lifecycle code.
     */
    public function getLifeCycleByCode(string $code): ?SystemLifeCycleModel
    {
        return $this->lifeCycles()
            ->whereLifeCycleCode($code)
            ->first();
    }

    /**
     * Instantiate and return the stage service for the model's current stage.
     */
    public function getLifeCycleStageByCode(string $code): ?LifeCycleStageContract
    {
        $lifeCycle = $this->lifeCycles()
            ->whereLifeCycleCode($code)
            ->first();

        if (!$lifeCycle) {
            return null;
        }

        $class = $lifeCycle->currentStage?->class;

        if (!$class) {
            return null;
        }

        return new $class($lifeCycle);
    }

    public function setNextLifeCycleStage(string $code): void
    {
        $lifeCycleModel = $this->lifeCycles()
            ->whereLifeCycleCode($code)
            ->first();

        if (!$lifeCycleModel) {
            return;
        }

        if (!$lifeCycleModel->currentStage) {
            return;
        }

        $newStageId = SystemLifeCycleStage::where('sequence', '>', $lifeCycleModel->currentStage->sequence)
            ->where('system_life_cycle_id', $lifeCycleModel->system_life_cycle_id)
            ->orderBy('sequence')
            ->value('id');

        $lifeCycleModel->update([
            'system_life_cycle_stage_id' => $newStageId,
            'status'                     => $newStageId ? LifeCycleStatus::Pending : LifeCycleStatus::Completed,
        ]);
    }

    public function removeLifeCycle(string $code): bool
    {
        return (bool) $this->lifeCycles()
            ->whereLifeCycleCode($code)
            ->delete();
    }
}
