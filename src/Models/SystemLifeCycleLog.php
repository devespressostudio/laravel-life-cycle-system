<?php

namespace Devespresso\SystemLifeCycle\Models;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SystemLifeCycleLog extends Model
{
    use HasUlids;

    protected $table = 'system_life_cycle_logs';

    protected $guarded = ['internal_id'];

    protected $casts = [
        'payload' => 'json',
        'status'  => LifeCycleStatus::class,
    ];

    public function lifeCycle(): BelongsTo
    {
        return $this->belongsTo(SystemLifeCycle::class, 'system_life_cycle_id', 'id');
    }

    public function lifeCycleStage(): BelongsTo
    {
        return $this->belongsTo(SystemLifeCycleStage::class, 'system_life_cycle_stage_id', 'id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }

    public function scopeFailed(Builder $builder): Builder
    {
        return $builder->where('status', LifeCycleStatus::Failed);
    }

    public function scopeSuccess(Builder $builder): Builder
    {
        return $builder->where('status', LifeCycleStatus::Success);
    }
}
