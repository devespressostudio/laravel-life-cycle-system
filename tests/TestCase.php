<?php

namespace Devespresso\SystemLifeCycle\Tests;

use Devespresso\SystemLifeCycle\Models\SystemLifeCycle;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Devespresso\SystemLifeCycle\SystemLifeCycleServiceProvider;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Models\DummyUser;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Services\PassingStageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SystemLifeCycleServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        // Enable foreign key enforcement in SQLite (off by default)
        DB::statement('PRAGMA foreign_keys = ON');

        $this->loadMigrationsFrom(__DIR__ . '/../src/migrations');

        Schema::create('dummy_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Create a complete lifecycle chain for testing.
     *
     * @param string $stageClass The service class to use for the stage
     * @param array  $lcOverrides Overrides for the SystemLifeCycle model
     * @param array  $stageOverrides Overrides for the SystemLifeCycleStage model
     * @param array  $modelOverrides Overrides for the SystemLifeCycleModel model
     * @return array{user: DummyUser, lc: SystemLifeCycle, stage: SystemLifeCycleStage, lcModel: SystemLifeCycleModel}
     */
    protected function createLifeCycleChain(
        string $stageClass = PassingStageService::class,
        array $lcOverrides = [],
        array $stageOverrides = [],
        array $modelOverrides = []
    ): array {
        $user = DummyUser::create(['name' => 'Test User']);

        $lc = SystemLifeCycle::create(array_merge([
            'name'             => 'Test Lifecycle',
            'code'             => 'test-lc-' . uniqid(),
            'active'           => true,
            'starts_at'        => now()->subMinute(),
            'activate_by_cron' => true,
        ], $lcOverrides));

        $stage = SystemLifeCycleStage::create(array_merge([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'Stage 1',
            'class'                => $stageClass,
        ], $stageOverrides));

        $lcModel = SystemLifeCycleModel::create(array_merge([
            'system_life_cycle_id'       => $lc->id,
            'system_life_cycle_stage_id' => $stage->id,
            'model_id'                   => $user->id,
            'model_type'                 => DummyUser::class,
            'status'                     => 'pending',
            'attempts'                   => 0,
        ], $modelOverrides));

        return compact('user', 'lc', 'stage', 'lcModel');
    }
}
