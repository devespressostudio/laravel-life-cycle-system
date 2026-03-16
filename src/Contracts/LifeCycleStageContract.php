<?php

namespace Devespresso\SystemLifeCycle\Contracts;

interface LifeCycleStageContract
{
    /**
     * Entry point called by the framework to run this stage.
     * Handles transactions, logging, and stage progression automatically.
     */
    public function execute(): void;

    /**
     * The stage-specific logic to run.
     */
    public function handle(): void;

    /**
     * Determine whether the stage is ready to run.
     * Return false to reschedule and try again later.
     */
    public function shouldContinueToNextStage(): bool;
}
