<?php

namespace Devespresso\SystemLifeCycle\Tests\Unit\Models;

use Devespresso\SystemLifeCycle\Models\SystemLifeCycle;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Devespresso\SystemLifeCycle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemLifeCycleStageTest extends TestCase
{
    use RefreshDatabase;

    private function createLifeCycle(string $code = 'stage-test-lc'): SystemLifeCycle
    {
        return SystemLifeCycle::create([
            'name'   => 'Stage Test Lifecycle',
            'code'   => $code,
            'active' => true,
        ]);
    }

    public function test_ulid_id_is_generated_on_create(): void
    {
        $lc = $this->createLifeCycle();

        $stage = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'Stage',
            'class'                => 'SomeClass',
        ]);

        $this->assertNotNull($stage->id);
        $this->assertSame(26, strlen($stage->id));
    }

    public function test_sequence_is_cast_to_integer(): void
    {
        $lc = $this->createLifeCycle('stage-int-cast');

        $stage = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => '2',
            'name'                 => 'Stage 2',
            'class'                => 'SomeClass',
        ]);

        $this->assertIsInt($stage->sequence);
        $this->assertSame(2, $stage->sequence);
    }

    public function test_life_cycle_relationship_returns_parent(): void
    {
        $lc = $this->createLifeCycle('stage-rel-test');

        $stage = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'Rel Stage',
            'class'                => 'SomeClass',
        ]);

        $relatedLc = $stage->lifeCycle;

        $this->assertNotNull($relatedLc);
        $this->assertSame($lc->id, $relatedLc->id);
        $this->assertSame('stage-rel-test', $relatedLc->code);
    }

    public function test_cascade_delete_removes_stage_when_lifecycle_deleted(): void
    {
        $lc = $this->createLifeCycle('cascade-delete-test');

        $stage = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'Cascade Stage',
            'class'                => 'SomeClass',
        ]);

        $stageId = $stage->id;

        // Hard delete via withTrashed to trigger cascade
        $lc->forceDelete();

        $this->assertNull(SystemLifeCycleStage::find($stageId));
    }

    public function test_multiple_stages_belong_to_same_lifecycle(): void
    {
        $lc = $this->createLifeCycle('multi-stage-lc');

        SystemLifeCycleStage::create(['system_life_cycle_id' => $lc->id, 'sequence' => 1, 'name' => 'S1', 'class' => 'A']);
        SystemLifeCycleStage::create(['system_life_cycle_id' => $lc->id, 'sequence' => 2, 'name' => 'S2', 'class' => 'B']);

        $this->assertCount(2, $lc->stages);
    }
}
