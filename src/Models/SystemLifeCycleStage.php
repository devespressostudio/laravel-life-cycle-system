<?php

namespace Devespresso\SystemLifeCycle\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLifeCycleStage extends Model
{
    use HasUlids;

    protected $table = 'system_life_cycle_stages';

    protected $guarded = ['internal_id'];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function lifeCycle(): BelongsTo
    {
        return $this->belongsTo(SystemLifeCycle::class, 'system_life_cycle_id', 'id');
    }
}
