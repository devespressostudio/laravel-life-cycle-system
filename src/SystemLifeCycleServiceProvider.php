<?php

namespace Devespresso\SystemLifeCycle;

use Devespresso\SystemLifeCycle\Commands\CreateSystemLifeCycleCommand;
use Devespresso\SystemLifeCycle\Commands\SystemLifeCycleLogsCleanUpCommand;
use Devespresso\SystemLifeCycle\Commands\SystemLifeCycleModelCleanUpCommand;
use Devespresso\SystemLifeCycle\Commands\SystemLifeCycleRunCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class SystemLifeCycleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerMigrations();
        $this->registerMorphMap();

        $this->publishes([
            __DIR__ . '/config/systemLifeCycle.php' => config_path('systemLifeCycle.php'),
        ], 'devespresso-life-cycle-config');

        $this->publishes([
            __DIR__ . '/migrations' => database_path('migrations'),
        ], 'devespresso-life-cycle-migrations');

        if (config('systemLifeCycle.schedule.enabled', true)) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);

                $schedule->command('devespresso:life-cycle:run')
                    ->{config('systemLifeCycle.schedule.run.frequency', 'hourly')}();

                $schedule->command('devespresso:life-cycle:logs-clean-up')
                    ->{config('systemLifeCycle.schedule.logs_clean_up.frequency', 'weekly')}();

                $schedule->command('devespresso:life-cycle:completed-models-clean-up')
                    ->{config('systemLifeCycle.schedule.completed_models_clean_up.frequency', 'weekly')}();
            });
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/systemLifeCycle.php',
            'systemLifeCycle'
        );
    }

    private function registerMorphMap(): void
    {
        if (config('systemLifeCycle.custom_relation_mapping')) {
            Relation::morphMap(config('systemLifeCycle.relation_mapping'));
        }
    }

    private function registerMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/migrations');
        }
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SystemLifeCycleRunCommand::class,
                SystemLifeCycleLogsCleanUpCommand::class,
                SystemLifeCycleModelCleanUpCommand::class,
                CreateSystemLifeCycleCommand::class,
            ]);
        }
    }
}
