<?php

namespace Devespresso\SystemLifeCycle\Tests\Feature\Commands;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Devespresso\SystemLifeCycle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemLifeCycleModelCleanUpCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_completed_models_deleted(): void
    {
        $retentionDays = config('systemLifeCycle.completed_model_retention_days', 30);

        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['status' => 'completed']);

        SystemLifeCycleModel::where('id', $lcModel->id)->update([
            'updated_at' => now()->subDays($retentionDays + 1),
        ]);

        $this->artisan('devespresso:life-cycle:completed-models-clean-up');

        $this->assertNull(SystemLifeCycleModel::find($lcModel->id));
    }

    public function test_new_completed_models_preserved(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['status' => 'completed']);

        $this->artisan('devespresso:life-cycle:completed-models-clean-up');

        $this->assertNotNull(SystemLifeCycleModel::find($lcModel->id));
    }

    public function test_pending_models_not_deleted(): void
    {
        $retentionDays = config('systemLifeCycle.completed_model_retention_days', 30);

        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['status' => 'pending']);

        SystemLifeCycleModel::where('id', $lcModel->id)->update([
            'updated_at' => now()->subDays($retentionDays + 1),
        ]);

        $this->artisan('devespresso:life-cycle:completed-models-clean-up');

        $this->assertNotNull(SystemLifeCycleModel::find($lcModel->id));
    }

    public function test_failed_models_not_deleted(): void
    {
        $retentionDays = config('systemLifeCycle.completed_model_retention_days', 30);

        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['status' => 'failed']);

        SystemLifeCycleModel::where('id', $lcModel->id)->update([
            'updated_at' => now()->subDays($retentionDays + 1),
        ]);

        $this->artisan('devespresso:life-cycle:completed-models-clean-up');

        $this->assertNotNull(SystemLifeCycleModel::find($lcModel->id));
    }

    public function test_output_with_plural_when_multiple_deleted(): void
    {
        $retentionDays = config('systemLifeCycle.completed_model_retention_days', 30);

        ['lcModel' => $lcModel1] = $this->createLifeCycleChain(modelOverrides: ['status' => 'completed']);
        ['lcModel' => $lcModel2] = $this->createLifeCycleChain(modelOverrides: ['status' => 'completed']);

        SystemLifeCycleModel::whereIn('id', [$lcModel1->id, $lcModel2->id])->update([
            'updated_at' => now()->subDays($retentionDays + 1),
        ]);

        $this->artisan('devespresso:life-cycle:completed-models-clean-up')
            ->expectsOutput("Deleted 2 completed records older than {$retentionDays} days.")
            ->assertExitCode(0);
    }

    public function test_output_uses_singular_when_one_deleted(): void
    {
        $retentionDays = config('systemLifeCycle.completed_model_retention_days', 30);

        ['lcModel' => $lcModel] = $this->createLifeCycleChain(modelOverrides: ['status' => 'completed']);

        SystemLifeCycleModel::where('id', $lcModel->id)->update([
            'updated_at' => now()->subDays($retentionDays + 1),
        ]);

        $this->artisan('devespresso:life-cycle:completed-models-clean-up')
            ->expectsOutput("Deleted 1 completed record older than {$retentionDays} days.")
            ->assertExitCode(0);
    }

    public function test_no_records_message_when_nothing_deleted(): void
    {
        $this->artisan('devespresso:life-cycle:completed-models-clean-up')
            ->expectsOutput('No completed records to clean up.')
            ->assertExitCode(0);
    }

    public function test_respects_config_retention_days(): void
    {
        config(['systemLifeCycle.completed_model_retention_days' => 1]);

        ['lcModel' => $oldModel] = $this->createLifeCycleChain(modelOverrides: ['status' => 'completed']);
        ['lcModel' => $newModel] = $this->createLifeCycleChain(modelOverrides: ['status' => 'completed']);

        SystemLifeCycleModel::where('id', $oldModel->id)->update([
            'updated_at' => now()->subDays(2),
        ]);

        $this->artisan('devespresso:life-cycle:completed-models-clean-up');

        $this->assertNull(SystemLifeCycleModel::find($oldModel->id));
        $this->assertNotNull(SystemLifeCycleModel::find($newModel->id));
    }

    public function test_command_returns_exit_code_zero(): void
    {
        $this->artisan('devespresso:life-cycle:completed-models-clean-up')->assertExitCode(0);
    }
}
