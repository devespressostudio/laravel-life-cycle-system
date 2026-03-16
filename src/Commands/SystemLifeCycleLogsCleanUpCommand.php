<?php

namespace Devespresso\SystemLifeCycle\Commands;

use Devespresso\SystemLifeCycle\Models\SystemLifeCycleLog;
use Illuminate\Console\Command;

class SystemLifeCycleLogsCleanUpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devespresso:life-cycle:logs-clean-up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete life cycle logs older than the configured retention period';

    public function handle(): int
    {
        $days = config('systemLifeCycle.log_retention_days');

        $deleted = SystemLifeCycleLog::where('created_at', '<', now()->subDays($days))->delete();

        $deleted > 0
            ? $this->info("Deleted {$deleted} log " . ($deleted === 1 ? 'entry' : 'entries') . " older than {$days} days.")
            : $this->info('No logs to clean up.');

        return 0;
    }
}
