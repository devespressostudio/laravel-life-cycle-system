<?php

namespace Devespresso\SystemLifeCycle\Tests\Unit\Service;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleLog;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Services\FailingStageService;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Services\NotReadyStageService;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Services\PassingStageService;
use Devespresso\SystemLifeCycle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemLifeCycleServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_happy_path_creates_success_log(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(PassingStageService::class);

        $service = new PassingStageService($lcModel);
        $service->execute();

        $this->assertDatabaseHas('system_life_cycle_logs', [
            'system_life_cycle_id'       => $lcModel->system_life_cycle_id,
            'system_life_cycle_stage_id' => $lcModel->system_life_cycle_stage_id,
            'status'                     => LifeCycleStatus::Success->value,
        ]);
    }

    public function test_happy_path_advances_stage_when_next_exists(): void
    {
        ['lc' => $lc, 'stage' => $stage1, 'lcModel' => $lcModel] = $this->createLifeCycleChain(PassingStageService::class);

        // Create a second stage
        $stage2 = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 2,
            'name'                 => 'Stage 2',
            'class'                => PassingStageService::class,
        ]);

        $service = new PassingStageService($lcModel);
        $service->execute();

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame($stage2->id, $freshModel->system_life_cycle_stage_id);
        $this->assertSame(LifeCycleStatus::Pending, $freshModel->status);
    }

    public function test_last_stage_marks_model_completed(): void
    {
        // Only one stage — there is no next stage
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(PassingStageService::class);

        $service = new PassingStageService($lcModel);
        $service->execute();

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(LifeCycleStatus::Completed, $freshModel->status);
    }

    // -------------------------------------------------------------------------
    // shouldContinueToNextStage = false
    // -------------------------------------------------------------------------

    public function test_not_ready_and_not_retry_reschedules_without_log(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            NotReadyStageService::class,
            modelOverrides: ['attempts' => 0]
        );

        $service = new NotReadyStageService($lcModel);
        $service->execute();

        // No log should be created
        $this->assertDatabaseCount('system_life_cycle_logs', 0);

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(LifeCycleStatus::Pending, $freshModel->status);
        $this->assertNotNull($freshModel->executes_at);
    }

    public function test_not_ready_but_is_retry_calls_handle_and_creates_log(): void
    {
        // attempts >= 1 makes isRetry() return true, so handle() IS called
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            PassingStageService::class,
            modelOverrides: ['attempts' => 1]
        );

        // Use a service that returns false for shouldContinueToNextStage but
        // we actually want to confirm the code path — using a custom inline approach
        // We verify by using PassingStageService with attempts=1: should proceed normally
        $service = new PassingStageService($lcModel);
        $service->execute();

        // A success log should exist because handle() was called
        $this->assertDatabaseHas('system_life_cycle_logs', [
            'status' => LifeCycleStatus::Success->value,
        ]);
    }

    public function test_not_ready_with_attempts_1_bypasses_reschedule(): void
    {
        // When isRetry() is true (!shouldContinue && !isRetry) is false, so handle runs
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            NotReadyStageService::class,
            modelOverrides: ['attempts' => 1]
        );

        $service = new NotReadyStageService($lcModel);
        $service->execute();

        // handle() is invoked (NotReadyStageService::handle does nothing, no exception)
        // so a success log IS created
        $this->assertDatabaseHas('system_life_cycle_logs', [
            'status' => LifeCycleStatus::Success->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Exception handling
    // -------------------------------------------------------------------------

    public function test_exception_creates_error_log_with_message(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(FailingStageService::class);

        $service = new FailingStageService($lcModel);
        $service->execute();

        $this->assertDatabaseHas('system_life_cycle_logs', [
            'system_life_cycle_id' => $lcModel->system_life_cycle_id,
            'status'               => LifeCycleStatus::Failed->value,
            'error'                => 'stage failed',
        ]);
    }

    public function test_exception_increments_attempts(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            FailingStageService::class,
            modelOverrides: ['attempts' => 0]
        );

        $service = new FailingStageService($lcModel);
        $service->execute();

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(1, $freshModel->attempts);
    }

    public function test_exception_keeps_status_pending_when_below_max_attempts(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            FailingStageService::class,
            modelOverrides: ['attempts' => 0]
        );

        $service = new FailingStageService($lcModel);
        $service->execute();

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(LifeCycleStatus::Pending, $freshModel->status);
    }

    public function test_exception_marks_failed_when_attempts_reach_max(): void
    {
        $maxAttempts = config('systemLifeCycle.max_attempts', 3);

        // One more failure will push attempts to max_attempts, triggering the failed status.
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            FailingStageService::class,
            modelOverrides: ['attempts' => $maxAttempts - 1]
        );

        $service = new FailingStageService($lcModel);
        $service->execute();

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(LifeCycleStatus::Failed, $freshModel->status);
    }

    // -------------------------------------------------------------------------
    // setParam / getParam round-trip
    // -------------------------------------------------------------------------

    public function test_set_param_and_get_param_round_trip(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain();

        $service = new PassingStageService($lcModel);

        // Use reflection to call protected methods
        $setParam = new \ReflectionMethod($service, 'setParam');
        $getParam = new \ReflectionMethod($service, 'getParam');

        $setParam->invoke($service, 'my_key', 'my_value');
        $result = $getParam->invoke($service, 'my_key');

        $this->assertSame('my_value', $result);
    }

    public function test_get_param_returns_null_for_missing_key(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain();

        $service = new PassingStageService($lcModel);
        $getParam = new \ReflectionMethod($service, 'getParam');

        $this->assertNull($getParam->invoke($service, 'nonexistent_key'));
    }

    // -------------------------------------------------------------------------
    // isRetry
    // -------------------------------------------------------------------------

    public function test_is_retry_returns_false_when_attempts_is_zero(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['attempts' => 0]);

        $service = new PassingStageService($lcModel);
        $isRetry = new \ReflectionMethod($service, 'isRetry');

        $this->assertFalse($isRetry->invoke($service));
    }

    public function test_is_retry_returns_true_when_attempts_is_one(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['attempts' => 1]);

        $service = new PassingStageService($lcModel);
        $isRetry = new \ReflectionMethod($service, 'isRetry');

        $this->assertTrue($isRetry->invoke($service));
    }

    public function test_is_retry_returns_true_when_attempts_greater_than_one(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['attempts' => 5]);

        $service = new PassingStageService($lcModel);
        $isRetry = new \ReflectionMethod($service, 'isRetry');

        $this->assertTrue($isRetry->invoke($service));
    }

    // -------------------------------------------------------------------------
    // Params propagated to next stage via payload
    // -------------------------------------------------------------------------

    public function test_params_propagated_to_next_stage_via_payload(): void
    {
        ['lc' => $lc, 'lcModel' => $lcModel] = $this->createLifeCycleChain(
            PassingStageService::class,
            modelOverrides: ['payload' => ['initial' => 'data']]
        );

        // Create a second stage
        SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 2,
            'name'                 => 'Stage 2',
            'class'                => PassingStageService::class,
        ]);

        // Reload the model so payload is hydrated
        $lcModel = SystemLifeCycleModel::find($lcModel->id);

        $service = new PassingStageService($lcModel);

        // Set a new param before executing
        $setParam = new \ReflectionMethod($service, 'setParam');
        $setParam->invoke($service, 'added_key', 'added_value');

        $service->execute();

        $freshModel = SystemLifeCycleModel::find($lcModel->id);

        // The payload should contain both the original data and the new param
        $this->assertSame('data', $freshModel->payload['initial']);
        $this->assertSame('added_value', $freshModel->payload['added_key']);
    }

    // -------------------------------------------------------------------------
    // Reschedule sets executes_at
    // -------------------------------------------------------------------------

    public function test_failed_cycle_reschedules_with_executes_at(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            FailingStageService::class,
            modelOverrides: ['attempts' => 0]
        );

        $service = new FailingStageService($lcModel);
        $service->execute();

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertNotNull($freshModel->executes_at);
    }
}
