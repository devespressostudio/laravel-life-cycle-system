<?php

namespace Devespresso\SystemLifeCycle\Tests\Unit\Models;

use Devespresso\SystemLifeCycle\Models\SystemLifeCycle;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Devespresso\SystemLifeCycle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemLifeCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_ulid_id_is_generated_on_create(): void
    {
        $lc = SystemLifeCycle::create([
            'name'      => 'Test',
            'code'      => 'test-ulid',
            'active'    => true,
            'starts_at' => now(),
        ]);

        $this->assertNotNull($lc->id);
        // ULID is 26 characters
        $this->assertSame(26, strlen($lc->id));
    }

    public function test_active_is_cast_to_boolean(): void
    {
        $lc = SystemLifeCycle::create([
            'name'   => 'Active Cast Test',
            'code'   => 'active-cast',
            'active' => 1,
        ]);

        $this->assertIsBool($lc->active);
        $this->assertTrue($lc->active);
    }

    public function test_starts_at_is_cast_to_carbon(): void
    {
        $lc = SystemLifeCycle::create([
            'name'      => 'Date Cast Test',
            'code'      => 'date-cast-starts',
            'active'    => true,
            'starts_at' => '2024-01-15 10:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $lc->starts_at);
    }

    public function test_ends_at_is_cast_to_carbon_when_set(): void
    {
        $lc = SystemLifeCycle::create([
            'name'    => 'Date Cast Test Ends',
            'code'    => 'date-cast-ends',
            'active'  => true,
            'ends_at' => '2025-12-31 23:59:59',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $lc->ends_at);
    }

    public function test_activate_sets_active_true_and_persists(): void
    {
        $lc = SystemLifeCycle::create([
            'name'   => 'Activate Test',
            'code'   => 'activate-test',
            'active' => false,
        ]);

        $lc->activate();

        $this->assertTrue($lc->fresh()->active);
    }

    public function test_deactivate_sets_active_false_and_persists(): void
    {
        $lc = SystemLifeCycle::create([
            'name'   => 'Deactivate Test',
            'code'   => 'deactivate-test',
            'active' => true,
        ]);

        $lc->deactivate();

        $this->assertFalse($lc->fresh()->active);
    }

    public function test_stages_relationship_returns_ordered_by_sequence(): void
    {
        $lc = SystemLifeCycle::create([
            'name'   => 'Stages Order Test',
            'code'   => 'stages-order',
            'active' => true,
        ]);

        SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 3,
            'name'                 => 'Third Stage',
            'class'                => 'SomeClass',
        ]);
        SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'First Stage',
            'class'                => 'SomeClass',
        ]);
        SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 2,
            'name'                 => 'Second Stage',
            'class'                => 'SomeClass',
        ]);

        $stages = $lc->stages;

        $this->assertSame(1, $stages[0]->sequence);
        $this->assertSame(2, $stages[1]->sequence);
        $this->assertSame(3, $stages[2]->sequence);
    }

    public function test_soft_delete_does_not_hard_delete_record(): void
    {
        $lc = SystemLifeCycle::create([
            'name'   => 'Soft Delete Test',
            'code'   => 'soft-delete-test',
            'active' => true,
        ]);

        $id = $lc->id;
        $lc->delete();

        $this->assertNull(SystemLifeCycle::find($id));
        $this->assertNotNull(SystemLifeCycle::withTrashed()->find($id));
    }

    public function test_internal_id_is_guarded(): void
    {
        $lc = SystemLifeCycle::create([
            'name'        => 'Guarded Test',
            'code'        => 'guarded-test',
            'active'      => true,
            'internal_id' => 9999,
        ]);

        // internal_id should be auto-incremented, not the supplied value
        $this->assertNotSame(9999, $lc->internal_id);
    }
}
