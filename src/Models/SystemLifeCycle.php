<?php

namespace Devespresso\SystemLifeCycle\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemLifeCycle extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'system_life_cycles';

    protected $guarded = ['internal_id'];

    protected $casts = [
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'active'           => 'boolean',
        'activate_by_cron' => 'boolean',
    ];

    /**
     * The stages that belong to this lifecycle, ordered by sequence.
     */
    public function stages(): HasMany
    {
        return $this->hasMany(SystemLifeCycleStage::class, 'system_life_cycle_id', 'id')
            ->orderBy('sequence');
    }

    /**
     * Mark the lifecycle as active.
     */
    public function activate(): void
    {
        $this->update(['active' => true]);
    }

    /**
     * Mark the lifecycle as inactive.
     */
    public function deactivate(): void
    {
        $this->update(['active' => false]);
    }
}
