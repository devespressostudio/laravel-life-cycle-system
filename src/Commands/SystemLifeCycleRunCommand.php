<?php

namespace Devespresso\SystemLifeCycle\Commands;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Jobs\SystemLifeCycleExecuteJob;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SystemLifeCycleRunCommand extends Command
{
    protected $signature = 'devespresso:life-cycle:run';

    protected $description = 'Process all pending life cycle stages and dispatch them to the queue';

    public function handle(): int
    {
        $batchId = Str::uuid()->toString();

        // Reset executes_at for stale records that were scheduled but never ran,
        // so they get picked up again on this tick
        SystemLifeCycleModel::where('executes_at', '<', now()->subMinutes(20))
            ->update(['executes_at' => null]);

        // Assign the first stage to any model that doesn't have one yet
        $sql = "(SELECT id FROM system_life_cycle_stages
            WHERE system_life_cycle_models.system_life_cycle_id = system_life_cycle_stages.system_life_cycle_id
            ORDER BY sequence ASC LIMIT 1)";

        SystemLifeCycleModel::whereNull('system_life_cycle_stage_id')
            ->whereCanBeExecuted()
            ->update(['system_life_cycle_stage_id' => DB::raw($sql)]);

        // Claim all pending records for this batch
        SystemLifeCycleModel::where('status', LifeCycleStatus::Pending)
            ->whereCanBeExecuted()
            ->update([
                'batch'  => $batchId,
                'status' => LifeCycleStatus::Processing,
            ]);

        // Dispatch a job for each claimed record
        SystemLifeCycleModel::with(['currentStage'])
            ->select('system_life_cycle_models.*')
            ->where('status', LifeCycleStatus::Processing)
            ->where('batch', $batchId)
            ->whereCanBeExecuted()
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    SystemLifeCycleExecuteJob::dispatch($item);
                }
            }, 'system_life_cycle_models.id', 'id');

        return 0;
    }
}
