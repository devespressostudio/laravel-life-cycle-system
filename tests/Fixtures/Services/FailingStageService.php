<?php

namespace Devespresso\SystemLifeCycle\Tests\Fixtures\Services;

use Devespresso\SystemLifeCycle\SystemLifeCycleService;

class FailingStageService extends SystemLifeCycleService
{
    public function handle(): void
    {
        throw new \RuntimeException('stage failed');
    }

    public function shouldContinueToNextStage(): bool
    {
        return true;
    }
}
