<?php

namespace Devespresso\SystemLifeCycle;

use Devespresso\SystemLifeCycle\Contracts\LifeCycleStageContract;
use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleLog;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class SystemLifeCycleService implements LifeCycleStageContract
{
    protected array $params = [];

    protected ?Model $model = null;

    protected ?SystemLifeCycleModel $systemLifeCycleModel = null;

    public function __construct(SystemLifeCycleModel $systemLifeCycleModel)
    {
        $this->systemLifeCycleModel = $systemLifeCycleModel;
        $this->params               = $systemLifeCycleModel->payload ?? [];
        $this->model                = $systemLifeCycleModel->model;
    }

    public function execute(): void
    {
        try {
            DB::transaction(function () {
                // If the stage is not ready and this is not a retry, reschedule it
                if (!$this->shouldContinueToNextStage() && !$this->isRetry()) {
                    $this->systemLifeCycleModel->update([
                        'status'      => LifeCycleStatus::Pending,
                        'executes_at' => $this->setExecutesAt(),
                    ]);

                    return;
                }

                $this->handle();

                $this->createSuccessLog();

                $this->setNextStage();
            });
        } catch (Exception $e) {
            $this->createErrorLog($e);
            $this->manageFailedCycle();
        }
    }

    public function setNextStage(): void
    {
        $currentStage = $this->systemLifeCycleModel->currentStage;

        $nextStageId = SystemLifeCycleStage::where('sequence', '>', $currentStage->sequence)
            ->where('system_life_cycle_id', $this->systemLifeCycleModel->system_life_cycle_id)
            ->orderBy('sequence')
            ->value('id');

        $attributes = [
            'status'      => $nextStageId ? LifeCycleStatus::Pending : LifeCycleStatus::Completed,
            'payload'     => $this->params,
            'executes_at' => null,
        ];

        if ($nextStageId) {
            $attributes['system_life_cycle_stage_id'] = $nextStageId;
            $attributes['attempts']                   = 0;
        }

        $this->systemLifeCycleModel->update($attributes);
    }

    public function manageFailedCycle(): void
    {
        $maxAttempts = config('systemLifeCycle.max_attempts', 3);
        $newAttempts = $this->systemLifeCycleModel->attempts + 1;

        $this->systemLifeCycleModel->update([
            'status'      => $newAttempts >= $maxAttempts
                ? LifeCycleStatus::Failed
                : LifeCycleStatus::Pending,
            'attempts'    => $newAttempts,
            'executes_at' => now()->addHour(),
        ]);
    }

    /**
     * Override to control when the next execution should run
     * when shouldContinueToNextStage() returns false.
     */
    public function setExecutesAt(): ?Carbon
    {
        return null;
    }

    protected function setParam(string $key, mixed $value): void
    {
        $this->params[$key] = $value;
    }

    protected function getParam(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    protected function isRetry(): bool
    {
        return $this->systemLifeCycleModel->attempts >= 1;
    }

    private function createLog(array $params = []): void
    {
        SystemLifeCycleLog::create(array_merge(
            $this->systemLifeCycleModel->only([
                'model_id',
                'model_type',
                'system_life_cycle_stage_id',
                'system_life_cycle_id',
                'payload',
                'attempts',
            ]),
            $params,
        ));
    }

    private function createSuccessLog(): void
    {
        $this->createLog(['status' => LifeCycleStatus::Success]);
    }

    private function createErrorLog(Exception $e): void
    {
        $this->createLog([
            'status' => LifeCycleStatus::Failed,
            'error'  => $e->getMessage(),
        ]);
    }
}
