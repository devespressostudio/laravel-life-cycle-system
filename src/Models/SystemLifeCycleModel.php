<?php

namespace Devespresso\SystemLifeCycle\Models;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SystemLifeCycleModel extends Model
{
    use HasUlids;

    protected $table = 'system_life_cycle_models';

    protected $guarded = ['internal_id'];

    protected $casts = [
        'payload'     => 'json',
        'status'      => LifeCycleStatus::class,
        'executes_at' => 'datetime',
    ];

    protected $attributes = [
        'status'   => 'pending',
        'attempts' => 0,
    ];

    public function lifeCycle(): BelongsTo
    {
        return $this->belongsTo(SystemLifeCycle::class, 'system_life_cycle_id', 'id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(SystemLifeCycleStage::class, 'system_life_cycle_stage_id', 'id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }

    /**
     * Filter records that are eligible to be executed.
     *
     * Joins system_life_cycles and applies the following conditions:
     *  1. The lifecycle must be active.
     *  2. The lifecycle's starts_at must be in the past.
     *  3. If $onlyByCron is true (default), only lifecycles with activate_by_cron = true.
     *  4. The lifecycle must not have ended (ends_at is null or in the future).
     *  5. The record either has no executes_at (run immediately) or its executes_at
     *     falls within the configured window. The window is determined by
     *     config('systemLifeCycle.schedule.run.window_in_minutes') and should match
     *     the run frequency. Boundaries are snapped to startOfMinute / endOfMinute
     *     so records scheduled at any second within those boundary minutes are included.
     *
     * Override $startDate / $endDate to use a custom window instead of the config value.
     */
    public function scopeWhereCanBeExecuted(
        Builder $builder,
        ?string $startDate = null,
        ?string $endDate = null,
        bool $onlyByCron = true
    ): Builder {
        $now = now();

        return $builder
            ->join('system_life_cycles', 'system_life_cycles.id', 'system_life_cycle_models.system_life_cycle_id')
            ->where('system_life_cycles.active', true)
            ->where('system_life_cycles.starts_at', '<', $now)
            ->when($onlyByCron, fn ($q) => $q->where('system_life_cycles.activate_by_cron', true))
            ->where(function ($query) use ($now) {
                $query->whereNull('system_life_cycles.ends_at')
                    ->orWhere('system_life_cycles.ends_at', '>', $now);
            })
            ->where(function ($query) use ($startDate, $endDate, $now) {
                $windowMinutes = config('systemLifeCycle.schedule.run.window_in_minutes', 60);

                $query->whereNull('executes_at')
                    ->orWhereBetween('executes_at', [
                        $startDate ?? $now->copy()->subMinutes($windowMinutes)->startOfMinute()->toDateTimeString(),
                        $endDate ?? $now->copy()->addMinutes($windowMinutes)->endOfMinute()->toDateTimeString(),
                    ]);
            });
    }

    /**
     * Filter by lifecycle code.
     */
    public function scopeWhereLifeCycleCode(Builder $builder, string $code): Builder
    {
        return $builder->whereHas('lifeCycle', fn ($q) => $q->where('code', $code));
    }

    public function scopePending(Builder $builder): Builder
    {
        return $builder->where('status', LifeCycleStatus::Pending);
    }

    public function scopeProcessing(Builder $builder): Builder
    {
        return $builder->where('status', LifeCycleStatus::Processing);
    }

    public function scopeCompleted(Builder $builder): Builder
    {
        return $builder->where('status', LifeCycleStatus::Completed);
    }

    public function scopeFailed(Builder $builder): Builder
    {
        return $builder->where('status', LifeCycleStatus::Failed);
    }
}
