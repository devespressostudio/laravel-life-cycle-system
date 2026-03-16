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
     * Joins system_life_cycles to check active status, date range, and cron flag.
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
                $query->whereNull('executes_at')
                    ->orWhereBetween('executes_at', [
                        $startDate ?? $now->copy()->startOfMinute()->toDateTimeString(),
                        $endDate ?? $now->copy()->addMinutes(10)->toDateTimeString(),
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
