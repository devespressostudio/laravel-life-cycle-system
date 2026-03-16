<?php

namespace Devespresso\SystemLifeCycle\Tests\Feature\Commands;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleLog;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Models\DummyUser;
use Devespresso\SystemLifeCycle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemLifeCycleLogsCleanUpCommandTest extends TestCase
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

    public function test_old_logs_deleted(): void
    {
        $retentionDays = config('systemLifeCycle.log_retention_days', 90);

        $oldLog = $this->createLog();
        // Manually set created_at to be older than retention period
        SystemLifeCycleLog::where('id', $oldLog->id)->update([
            'created_at' => now()->subDays($retentionDays + 1),
        ]);

        $this->artisan('devespresso:life-cycle:logs-clean-up');

        $this->assertNull(SystemLifeCycleLog::find($oldLog->id));
    }

    public function test_new_logs_preserved(): void
    {
        $newLog = $this->createLog();

        $this->artisan('devespresso:life-cycle:logs-clean-up');

        $this->assertNotNull(SystemLifeCycleLog::find($newLog->id));
    }

    public function test_output_message_with_count_when_logs_deleted(): void
    {
        $retentionDays = config('systemLifeCycle.log_retention_days', 90);

        $oldLog1 = $this->createLog();
        $oldLog2 = $this->createLog();

        SystemLifeCycleLog::whereIn('id', [$oldLog1->id, $oldLog2->id])->update([
            'created_at' => now()->subDays($retentionDays + 1),
        ]);

        $this->artisan('devespresso:life-cycle:logs-clean-up')
            ->expectsOutput("Deleted 2 log entries older than {$retentionDays} days.")
            ->assertExitCode(0);
    }

    public function test_output_uses_singular_when_one_log_deleted(): void
    {
        $retentionDays = config('systemLifeCycle.log_retention_days', 90);

        $oldLog = $this->createLog();
        SystemLifeCycleLog::where('id', $oldLog->id)->update([
            'created_at' => now()->subDays($retentionDays + 1),
        ]);

        $this->artisan('devespresso:life-cycle:logs-clean-up')
            ->expectsOutput("Deleted 1 log entry older than {$retentionDays} days.")
            ->assertExitCode(0);
    }

    public function test_no_logs_message_when_nothing_deleted(): void
    {
        $this->artisan('devespresso:life-cycle:logs-clean-up')
            ->expectsOutput('No logs to clean up.')
            ->assertExitCode(0);
    }

    public function test_respects_config_retention_days(): void
    {
        // Use a very short retention period
        config(['systemLifeCycle.log_retention_days' => 1]);

        $oldLog = $this->createLog();
        SystemLifeCycleLog::where('id', $oldLog->id)->update([
            'created_at' => now()->subDays(2),
        ]);

        $recentLog = $this->createLog(); // created now, should be preserved

        $this->artisan('devespresso:life-cycle:logs-clean-up');

        $this->assertNull(SystemLifeCycleLog::find($oldLog->id));
        $this->assertNotNull(SystemLifeCycleLog::find($recentLog->id));
    }

    public function test_command_returns_exit_code_zero(): void
    {
        $this->artisan('devespresso:life-cycle:logs-clean-up')->assertExitCode(0);
    }
}
