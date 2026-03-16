<?php

namespace Devespresso\SystemLifeCycle\Tests\Unit\Models;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycle;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleLog;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Models\DummyUser;
use Devespresso\SystemLifeCycle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemLifeCycleLogTest extends TestCase
{
    use RefreshDatabase;

    private function createLog(array $overrides = []): SystemLifeCycleLog
    {
        ['lc' => $lc, 'stage' => $stage, 'user' => $user] = $this->createLifeCycleChain();

        return SystemLifeCycleLog::create(array_merge([
            'system_life_cycle_id'       => $lc->id,
            'system_life_cycle_stage_id' => $stage->id,
            'model_id'                   => $user->id,
            'model_type'                 => DummyUser::class,
            'status'                     => LifeCycleStatus::Success,
            'attempts'                   => 0,
        ], $overrides));
    }

    public function test_ulid_id_is_generated_on_create(): void
    {
        $log = $this->createLog();

        $this->assertNotNull($log->id);
        $this->assertSame(26, strlen($log->id));
    }

    public function test_payload_json_cast(): void
    {
        $log = $this->createLog(['payload' => ['result' => 'ok', 'items' => [1, 2, 3]]]);

        $freshLog = SystemLifeCycleLog::find($log->id);

        $this->assertIsArray($freshLog->payload);
        $this->assertSame('ok', $freshLog->payload['result']);
        $this->assertSame([1, 2, 3], $freshLog->payload['items']);
    }

    public function test_status_cast_to_life_cycle_status_enum(): void
    {
        $log = $this->createLog(['status' => LifeCycleStatus::Success]);

        $freshLog = SystemLifeCycleLog::find($log->id);

        $this->assertInstanceOf(LifeCycleStatus::class, $freshLog->status);
        $this->assertSame(LifeCycleStatus::Success, $freshLog->status);
    }

    public function test_scope_failed_returns_only_failed_logs(): void
    {
        $this->createLog(['status' => LifeCycleStatus::Success]);
        $this->createLog(['status' => LifeCycleStatus::Failed]);
        $this->createLog(['status' => LifeCycleStatus::Failed]);

        $failedLogs = SystemLifeCycleLog::failed()->get();

        $this->assertCount(2, $failedLogs);
        foreach ($failedLogs as $log) {
            $this->assertSame(LifeCycleStatus::Failed, $log->status);
        }
    }

    public function test_scope_success_returns_only_success_logs(): void
    {
        $this->createLog(['status' => LifeCycleStatus::Success]);
        $this->createLog(['status' => LifeCycleStatus::Success]);
        $this->createLog(['status' => LifeCycleStatus::Failed]);

        $successLogs = SystemLifeCycleLog::success()->get();

        $this->assertCount(2, $successLogs);
        foreach ($successLogs as $log) {
            $this->assertSame(LifeCycleStatus::Success, $log->status);
        }
    }

    public function test_life_cycle_relationship_returns_correct_lifecycle(): void
    {
        ['lc' => $lc] = $this->createLifeCycleChain();
        $log = $this->createLog(['system_life_cycle_id' => $lc->id]);

        $this->assertNotNull($log->lifeCycle);
        $this->assertSame($lc->id, $log->lifeCycle->id);
    }

    public function test_life_cycle_stage_relationship_returns_correct_stage(): void
    {
        ['stage' => $stage] = $this->createLifeCycleChain();
        $log = $this->createLog(['system_life_cycle_stage_id' => $stage->id]);

        $this->assertNotNull($log->lifeCycleStage);
        $this->assertSame($stage->id, $log->lifeCycleStage->id);
    }

    public function test_model_morph_relationship_returns_correct_model(): void
    {
        ['user' => $user] = $this->createLifeCycleChain();

        $log = $this->createLog([
            'model_id'   => $user->id,
            'model_type' => DummyUser::class,
        ]);

        $this->assertNotNull($log->model);
        $this->assertInstanceOf(DummyUser::class, $log->model);
        $this->assertSame($user->id, $log->model->id);
    }

    public function test_error_stored_as_plain_string(): void
    {
        $log = $this->createLog([
            'status' => LifeCycleStatus::Failed,
            'error'  => 'Something went wrong during processing',
        ]);

        $freshLog = SystemLifeCycleLog::find($log->id);

        $this->assertIsString($freshLog->error);
        $this->assertSame('Something went wrong during processing', $freshLog->error);
    }
}
