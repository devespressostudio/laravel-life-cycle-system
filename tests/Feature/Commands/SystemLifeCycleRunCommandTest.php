<?php

namespace Devespresso\SystemLifeCycle\Tests\Feature\Commands;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Jobs\SystemLifeCycleExecuteJob;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycle;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Models\DummyUser;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Services\PassingStageService;
use Devespresso\SystemLifeCycle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class SystemLifeCycleRunCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_records_have_executes_at_reset(): void
    {
        Queue::fake();

        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: [
            'executes_at' => now()->subMinutes(25), // older than 20 min threshold
            'status'      => 'pending',
        ]);

        $this->artisan('devespresso:life-cycle:run');

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertNull($freshModel->executes_at);
    }

    public function test_first_stage_assigned_to_null_stage_models(): void
    {
        Queue::fake();

        $user = DummyUser::create(['name' => 'No Stage User']);

        $lc = SystemLifeCycle::create([
            'name'             => 'First Stage Test',
            'code'             => 'first-stage-assign',
            'active'           => true,
            'starts_at'        => now()->subMinute(),
            'activate_by_cron' => true,
        ]);

        $stage = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'Stage 1',
            'class'                => PassingStageService::class,
        ]);

        $lcModel = SystemLifeCycleModel::create([
            'system_life_cycle_id'       => $lc->id,
            'system_life_cycle_stage_id' => null, // no stage
            'model_id'                   => $user->id,
            'model_type'                 => DummyUser::class,
            'status'                     => 'pending',
            'attempts'                   => 0,
        ]);

        $this->artisan('devespresso:life-cycle:run');

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame($stage->id, $freshModel->system_life_cycle_stage_id);
    }

    public function test_pending_models_claimed_to_processing_with_batch(): void
    {
        Queue::fake();

        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['status' => 'pending']);

        $this->artisan('devespresso:life-cycle:run');

        // After run, the model has been processed (either still processing or advanced)
        // The important thing is it was claimed with a batch ID
        $this->assertDatabaseMissing('system_life_cycle_models', [
            'id'    => $lcModel->id,
            'batch' => null,
        ]);
    }

    public function test_completed_models_not_claimed(): void
    {
        Queue::fake();

        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['status' => 'completed']);

        $this->artisan('devespresso:life-cycle:run');

        // Completed model should not have been given a batch ID or changed to processing
        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(LifeCycleStatus::Completed, $freshModel->status);
        $this->assertNull($freshModel->batch);
    }

    public function test_failed_models_not_claimed(): void
    {
        Queue::fake();

        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['status' => 'failed']);

        $this->artisan('devespresso:life-cycle:run');

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(LifeCycleStatus::Failed, $freshModel->status);
        $this->assertNull($freshModel->batch);
    }

    public function test_inactive_lifecycle_not_claimed(): void
    {
        Queue::fake();

        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            lcOverrides: ['active' => false],
            modelOverrides: ['status' => 'pending']
        );

        $this->artisan('devespresso:life-cycle:run');

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(LifeCycleStatus::Pending, $freshModel->status);
        $this->assertNull($freshModel->batch);
    }

    public function test_job_dispatched_per_eligible_model(): void
    {
        Queue::fake();

        $this->createLifeCycleChain(modelOverrides: ['status' => 'pending']);
        $this->createLifeCycleChain(modelOverrides: ['status' => 'pending']);

        $this->artisan('devespresso:life-cycle:run');

        Queue::assertPushed(SystemLifeCycleExecuteJob::class, 2);
    }

    public function test_no_job_dispatched_when_no_eligible_models(): void
    {
        Queue::fake();

        $this->artisan('devespresso:life-cycle:run');

        Queue::assertNothingPushed();
    }

    public function test_command_returns_exit_code_zero(): void
    {
        Queue::fake();

        $this->artisan('devespresso:life-cycle:run')->assertExitCode(0);
    }
}
