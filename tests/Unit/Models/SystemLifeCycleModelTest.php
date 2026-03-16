<?php

namespace Devespresso\SystemLifeCycle\Tests\Unit\Models;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycle;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Models\DummyUser;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Services\PassingStageService;
use Devespresso\SystemLifeCycle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemLifeCycleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_status_is_pending_enum(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain();

        $freshModel = SystemLifeCycleModel::find($lcModel->id);

        $this->assertSame(LifeCycleStatus::Pending, $freshModel->status);
    }

    public function test_default_attempts_is_zero(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain();

        $this->assertSame(0, $lcModel->attempts);
    }

    public function test_payload_json_cast(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            modelOverrides: ['payload' => ['key' => 'value', 'count' => 42]]
        );

        $freshModel = SystemLifeCycleModel::find($lcModel->id);

        $this->assertIsArray($freshModel->payload);
        $this->assertSame('value', $freshModel->payload['key']);
        $this->assertSame(42, $freshModel->payload['count']);
    }

    public function test_status_cast_to_life_cycle_status_enum(): void
    {
        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            modelOverrides: ['status' => 'processing']
        );

        $freshModel = SystemLifeCycleModel::find($lcModel->id);

        $this->assertInstanceOf(LifeCycleStatus::class, $freshModel->status);
        $this->assertSame(LifeCycleStatus::Processing, $freshModel->status);
    }

    public function test_scope_pending_filters_correctly(): void
    {
        $this->createLifeCycleChain(modelOverrides: ['status' => 'pending']);
        $this->createLifeCycleChain(modelOverrides: ['status' => 'processing']);
        $this->createLifeCycleChain(modelOverrides: ['status' => 'completed']);
        $this->createLifeCycleChain(modelOverrides: ['status' => 'failed']);

        $results = SystemLifeCycleModel::pending()->get();

        $this->assertCount(1, $results);
        $this->assertSame(LifeCycleStatus::Pending, $results->first()->status);
    }

    public function test_scope_processing_filters_correctly(): void
    {
        $this->createLifeCycleChain(modelOverrides: ['status' => 'pending']);
        $this->createLifeCycleChain(modelOverrides: ['status' => 'processing']);

        $results = SystemLifeCycleModel::processing()->get();

        $this->assertCount(1, $results);
        $this->assertSame(LifeCycleStatus::Processing, $results->first()->status);
    }

    public function test_scope_completed_filters_correctly(): void
    {
        $this->createLifeCycleChain(modelOverrides: ['status' => 'pending']);
        $this->createLifeCycleChain(modelOverrides: ['status' => 'completed']);

        $results = SystemLifeCycleModel::completed()->get();

        $this->assertCount(1, $results);
        $this->assertSame(LifeCycleStatus::Completed, $results->first()->status);
    }

    public function test_scope_failed_filters_correctly(): void
    {
        $this->createLifeCycleChain(modelOverrides: ['status' => 'pending']);
        $this->createLifeCycleChain(modelOverrides: ['status' => 'failed']);

        $results = SystemLifeCycleModel::failed()->get();

        $this->assertCount(1, $results);
        $this->assertSame(LifeCycleStatus::Failed, $results->first()->status);
    }

    public function test_scope_where_life_cycle_code_filters_by_code(): void
    {
        ['lc' => $lc1] = $this->createLifeCycleChain(lcOverrides: ['code' => 'lc-code-alpha']);
        ['lc' => $lc2] = $this->createLifeCycleChain(lcOverrides: ['code' => 'lc-code-beta']);

        $results = SystemLifeCycleModel::whereLifeCycleCode('lc-code-alpha')->get();

        $this->assertCount(1, $results);
        $this->assertSame($lc1->id, $results->first()->system_life_cycle_id);
    }

    public function test_scope_where_can_be_executed_excludes_inactive_lifecycle(): void
    {
        $this->createLifeCycleChain(lcOverrides: ['active' => false, 'code' => 'inactive-lc']);

        $results = SystemLifeCycleModel::select('system_life_cycle_models.*')
            ->whereCanBeExecuted()
            ->get();

        $this->assertCount(0, $results);
    }

    public function test_scope_where_can_be_executed_excludes_future_starts_at(): void
    {
        $this->createLifeCycleChain(lcOverrides: [
            'starts_at' => now()->addHour(),
            'code'      => 'future-starts',
        ]);

        $results = SystemLifeCycleModel::select('system_life_cycle_models.*')
            ->whereCanBeExecuted()
            ->get();

        $this->assertCount(0, $results);
    }

    public function test_scope_where_can_be_executed_excludes_past_ends_at(): void
    {
        $this->createLifeCycleChain(lcOverrides: [
            'ends_at' => now()->subHour(),
            'code'    => 'past-ends',
        ]);

        $results = SystemLifeCycleModel::select('system_life_cycle_models.*')
            ->whereCanBeExecuted()
            ->get();

        $this->assertCount(0, $results);
    }

    public function test_scope_where_can_be_executed_respects_activate_by_cron_flag(): void
    {
        $this->createLifeCycleChain(lcOverrides: [
            'activate_by_cron' => false,
            'code'             => 'no-cron',
        ]);

        // With onlyByCron=true (default), should not pick up non-cron lifecycles
        $resultsCron = SystemLifeCycleModel::select('system_life_cycle_models.*')
            ->whereCanBeExecuted(onlyByCron: true)
            ->get();

        // With onlyByCron=false, should pick it up
        $resultsAll = SystemLifeCycleModel::select('system_life_cycle_models.*')
            ->whereCanBeExecuted(onlyByCron: false)
            ->get();

        $this->assertCount(0, $resultsCron);
        $this->assertCount(1, $resultsAll);
    }

    public function test_scope_where_can_be_executed_respects_executes_at_window(): void
    {
        // executes_at far in the future should be excluded from the default window
        $this->createLifeCycleChain(
            lcOverrides: ['code' => 'executes-future'],
            modelOverrides: ['executes_at' => now()->addDays(2)]
        );

        $results = SystemLifeCycleModel::select('system_life_cycle_models.*')
            ->whereCanBeExecuted()
            ->get();

        $this->assertCount(0, $results);
    }

    public function test_scope_where_can_be_executed_includes_null_executes_at(): void
    {
        $this->createLifeCycleChain(
            lcOverrides: ['code' => 'executes-null'],
            modelOverrides: ['executes_at' => null]
        );

        $results = SystemLifeCycleModel::select('system_life_cycle_models.*')
            ->whereCanBeExecuted()
            ->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_where_can_be_executed_includes_active_lifecycle_without_end_date(): void
    {
        $this->createLifeCycleChain(lcOverrides: [
            'ends_at' => null,
            'code'    => 'no-end-date',
        ]);

        $results = SystemLifeCycleModel::select('system_life_cycle_models.*')
            ->whereCanBeExecuted()
            ->get();

        $this->assertCount(1, $results);
    }
}
