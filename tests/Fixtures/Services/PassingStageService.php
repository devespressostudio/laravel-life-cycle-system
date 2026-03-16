<?php

namespace Devespresso\SystemLifeCycle\Tests\Fixtures\Services;

use Devespresso\SystemLifeCycle\SystemLifeCycleService;

class PassingStageService extends SystemLifeCycleService
{
    public function handle(): void
    {
        // success — does nothing
    }

    public function shouldContinueToNextStage(): bool
    {
        return true;
    }
}
