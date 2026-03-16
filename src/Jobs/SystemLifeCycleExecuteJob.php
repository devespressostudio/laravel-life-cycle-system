<?php

namespace Devespresso\SystemLifeCycle\Jobs;

use Devespresso\SystemLifeCycle\Contracts\LifeCycleStageContract;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SystemLifeCycleExecuteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retries are managed internally by the lifecycle system via manageFailedCycle().
     * Disabling Laravel's built-in retries prevents conflicts with the attempts counter.
     */
    public int $tries = 1;

    public function __construct(
        protected SystemLifeCycleModel $systemLifeCycleModel
    ) {}

    public function handle(): void
    {
        if (!$this->systemLifeCycleModel->currentStage) {
            return;
        }

        /** @var LifeCycleStageContract $handler */
        $handler = new ($this->systemLifeCycleModel->currentStage->class)($this->systemLifeCycleModel);

        $handler->execute();
    }
}
