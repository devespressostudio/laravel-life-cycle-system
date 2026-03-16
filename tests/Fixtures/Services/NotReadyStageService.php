<?php

namespace Devespresso\SystemLifeCycle\Tests\Fixtures\Services;

use Carbon\Carbon;
use Devespresso\SystemLifeCycle\SystemLifeCycleService;

class NotReadyStageService extends SystemLifeCycleService
{
    public function handle(): void
    {
        // should not be called
    }

    public function shouldContinueToNextStage(): bool
    {
        return false;
    }

    public function setExecutesAt(): ?Carbon
    {
        return now()->addHour();
    }
}
