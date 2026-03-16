<?php

namespace Devespresso\SystemLifeCycle\Commands;

use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Illuminate\Console\Command;

class SystemLifeCycleModelCleanUpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devespresso:life-cycle:completed-models-clean-up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete completed life cycle model records older than the configured retention period';

    public function handle(): int
    {
        $days = config('systemLifeCycle.completed_model_retention_days');

        $deleted = SystemLifeCycleModel::completed()
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();

        $deleted > 0
            ? $this->info("Deleted {$deleted} completed " . ($deleted === 1 ? 'record' : 'records') . " older than {$days} days.")
            : $this->info('No completed records to clean up.');

        return 0;
    }
}
