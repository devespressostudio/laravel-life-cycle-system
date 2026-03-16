<?php

namespace Devespresso\SystemLifeCycle\Tests\Feature\Jobs;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Jobs\SystemLifeCycleExecuteJob;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleLog;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Services\FailingStageService;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Services\PassingStageService;
use Devespresso\SystemLifeCycle\Tests\TestCase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemLifeCycleExecuteJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_tries_set_to_one(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain();

        $job = new SystemLifeCycleExecuteJob($lcModel);

        $this->assertSame(1, $job->tries);
    }

    public function test_job_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new SystemLifeCycleExecuteJob(
            $this->createLifeCycleChain()['lcModel']
        ));
    }

    public function test_dispatching_with_passing_service_creates_success_log(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(PassingStageService::class);

        SystemLifeCycleExecuteJob::dispatchSync($lcModel);

        $this->assertDatabaseHas('system_life_cycle_logs', [
            'system_life_cycle_id'       => $lcModel->system_life_cycle_id,
            'system_life_cycle_stage_id' => $lcModel->system_life_cycle_stage_id,
            'status'                     => LifeCycleStatus::Success->value,
        ]);
    }

    public function test_dispatching_with_failing_service_creates_error_log(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(FailingStageService::class);

        SystemLifeCycleExecuteJob::dispatchSync($lcModel);

        $this->assertDatabaseHas('system_life_cycle_logs', [
            'system_life_cycle_id' => $lcModel->system_life_cycle_id,
            'status'               => LifeCycleStatus::Failed->value,
            'error'                => 'stage failed',
        ]);
    }

    public function test_model_is_re_hydrated_from_db_via_serializes_models(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(PassingStageService::class);

        // Simulate what happens during queue serialization/deserialization
        $serialized = serialize(new SystemLifeCycleExecuteJob($lcModel));
        $deserialized = unserialize($serialized);

        // The deserialized job should hold a fully resolved model instance
        $jobModel = (new \ReflectionProperty($deserialized, 'systemLifeCycleModel'))->getValue($deserialized);

        $this->assertInstanceOf(SystemLifeCycleModel::class, $jobModel);
        $this->assertSame($lcModel->id, $jobModel->id);
    }

    public function test_execute_calls_handle_and_marks_completed_on_last_stage(): void
    {
        // Single stage lifecycle — after execution the model should be completed
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(PassingStageService::class);

        SystemLifeCycleExecuteJob::dispatchSync($lcModel);

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(LifeCycleStatus::Completed, $freshModel->status);
    }

    public function test_job_does_nothing_when_current_stage_is_null(): void
    {
        // Simulates a stage being deleted while the job was queued
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(PassingStageService::class);

        $lcModel->update(['system_life_cycle_stage_id' => null]);
        $lcModel->unsetRelation('currentStage');

        SystemLifeCycleExecuteJob::dispatchSync($lcModel->fresh());

        // No log should be created — the job silently bailed out
        $this->assertDatabaseCount('system_life_cycle_logs', 0);
    }

    public function test_failing_job_increments_attempts_on_model(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            FailingStageService::class,
            modelOverrides: ['attempts' => 0]
        );

        SystemLifeCycleExecuteJob::dispatchSync($lcModel);

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(1, $freshModel->attempts);
    }
}
