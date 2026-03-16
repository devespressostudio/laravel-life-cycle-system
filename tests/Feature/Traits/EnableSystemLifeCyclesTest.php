<?php

namespace Devespresso\SystemLifeCycle\Tests\Feature\Traits;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycle;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleModel;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Models\DummyUser;
use Devespresso\SystemLifeCycle\Tests\Fixtures\Services\PassingStageService;
use Devespresso\SystemLifeCycle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EnableSystemLifeCyclesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // lifeCycles() morphMany
    // -------------------------------------------------------------------------

    public function test_life_cycles_morph_many_relationship(): void
    {
        $user = DummyUser::create(['name' => 'John']);

        ['lcModel' => $lcModel] = $this->createLifeCycleChain(
            modelOverrides: ['model_id' => $user->id, 'model_type' => DummyUser::class]
        );

        $lcs = $user->lifeCycles;

        $this->assertCount(1, $lcs);
        $this->assertSame($lcModel->id, $lcs->first()->id);
    }

    // -------------------------------------------------------------------------
    // addLifeCycleByCode
    // -------------------------------------------------------------------------

    public function test_add_life_cycle_by_code_returns_model_with_correct_stage(): void
    {
        $user = DummyUser::create(['name' => 'Jane']);

        $lc = SystemLifeCycle::create([
            'name'   => 'Add LC Test',
            'code'   => 'add-lc-test',
            'active' => true,
        ]);

        $stage = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'First',
            'class'                => PassingStageService::class,
        ]);

        $lcModel = $user->addLifeCycleByCode('add-lc-test');

        $this->assertNotNull($lcModel);
        $this->assertSame($lc->id, $lcModel->system_life_cycle_id);
        $this->assertSame($stage->id, $lcModel->system_life_cycle_stage_id);
    }

    public function test_add_life_cycle_by_code_returns_same_model_on_duplicate(): void
    {
        $user = DummyUser::create(['name' => 'Dup Test']);

        $lc = SystemLifeCycle::create([
            'name'   => 'Dup LC',
            'code'   => 'dup-lc',
            'active' => true,
        ]);

        SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'Stage',
            'class'                => PassingStageService::class,
        ]);

        $model1 = $user->addLifeCycleByCode('dup-lc');
        $model2 = $user->addLifeCycleByCode('dup-lc');

        $this->assertSame($model1->id, $model2->id);
        $this->assertCount(1, SystemLifeCycleModel::all());
    }

    public function test_add_life_cycle_by_code_returns_null_for_unknown_code(): void
    {
        $user = DummyUser::create(['name' => 'Unknown Code']);

        $result = $user->addLifeCycleByCode('nonexistent-code');

        $this->assertNull($result);
    }

    public function test_add_life_cycle_by_code_does_not_duplicate_when_model_has_progressed(): void
    {
        // Regression: old code searched by (lc_id + stage_id), so after progressing to
        // stage 2 a second call would create a new record at stage 1.
        $user = DummyUser::create(['name' => 'Progress User']);

        $lc = SystemLifeCycle::create(['name' => 'Progress LC', 'code' => 'progress-lc', 'active' => true]);

        $stage1 = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id, 'sequence' => 1,
            'name' => 'Stage 1', 'class' => PassingStageService::class,
        ]);

        $stage2 = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id, 'sequence' => 2,
            'name' => 'Stage 2', 'class' => PassingStageService::class,
        ]);

        // Enroll, then simulate progression to stage 2
        $model = $user->addLifeCycleByCode('progress-lc');
        $model->update(['system_life_cycle_stage_id' => $stage2->id]);

        // Call again — must return the existing record, not create a new one
        $model2 = $user->addLifeCycleByCode('progress-lc');

        $this->assertSame($model->id, $model2->id);
        $this->assertCount(1, SystemLifeCycleModel::all());
    }

    // -------------------------------------------------------------------------
    // getLifeCycleByCode
    // -------------------------------------------------------------------------

    public function test_get_life_cycle_by_code_returns_model_record(): void
    {
        $user = DummyUser::create(['name' => 'Get LC User']);

        $lc = SystemLifeCycle::create(['name' => 'Get LC', 'code' => 'get-lc', 'active' => true]);
        $stage = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id, 'sequence' => 1,
            'name' => 'Stage 1', 'class' => PassingStageService::class,
        ]);

        $lcModel = SystemLifeCycleModel::create([
            'system_life_cycle_id'       => $lc->id,
            'system_life_cycle_stage_id' => $stage->id,
            'model_id'                   => $user->id,
            'model_type'                 => DummyUser::class,
            'status'                     => 'pending',
            'attempts'                   => 0,
        ]);

        $result = $user->getLifeCycleByCode('get-lc');

        $this->assertNotNull($result);
        $this->assertSame($lcModel->id, $result->id);
    }

    public function test_get_life_cycle_by_code_returns_null_when_not_enrolled(): void
    {
        $user = DummyUser::create(['name' => 'Not Enrolled']);

        SystemLifeCycle::create(['name' => 'Not Enrolled LC', 'code' => 'not-enrolled', 'active' => true]);

        $this->assertNull($user->getLifeCycleByCode('not-enrolled'));
    }

    // -------------------------------------------------------------------------
    // reEnrollLifeCycle
    // -------------------------------------------------------------------------

    public function test_re_enroll_resets_existing_record_to_first_stage(): void
    {
        $user = DummyUser::create(['name' => 'Re-enroll User']);

        $lc = SystemLifeCycle::create(['name' => 'Re-enroll LC', 'code' => 're-enroll-lc', 'active' => true]);

        $stage1 = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id, 'sequence' => 1,
            'name' => 'Stage 1', 'class' => PassingStageService::class,
        ]);

        $stage2 = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id, 'sequence' => 2,
            'name' => 'Stage 2', 'class' => PassingStageService::class,
        ]);

        // Enroll and simulate a progressed, completed lifecycle
        $model = $user->addLifeCycleByCode('re-enroll-lc');
        $model->update([
            'system_life_cycle_stage_id' => $stage2->id,
            'status'                     => 'completed',
            'attempts'                   => 2,
            'payload'                    => ['foo' => 'bar'],
        ]);

        $reset = $user->reEnrollLifeCycle('re-enroll-lc');

        $this->assertSame($model->id, $reset->id);
        $this->assertSame($stage1->id, $reset->system_life_cycle_stage_id);
        $this->assertSame(LifeCycleStatus::Pending, $reset->status);
        $this->assertSame(0, $reset->attempts);
        $this->assertNull($reset->payload);
        $this->assertNull($reset->executes_at);
        $this->assertNull($reset->batch);
        $this->assertCount(1, SystemLifeCycleModel::all());
    }

    public function test_re_enroll_creates_fresh_record_when_not_previously_enrolled(): void
    {
        $user = DummyUser::create(['name' => 'Fresh Enroll User']);

        $lc = SystemLifeCycle::create(['name' => 'Fresh LC', 'code' => 'fresh-lc', 'active' => true]);
        $stage = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id, 'sequence' => 1,
            'name' => 'Stage 1', 'class' => PassingStageService::class,
        ]);

        $result = $user->reEnrollLifeCycle('fresh-lc');

        $this->assertNotNull($result);
        $this->assertSame($stage->id, $result->system_life_cycle_stage_id);
        $this->assertSame(LifeCycleStatus::Pending, $result->status);
        $this->assertCount(1, SystemLifeCycleModel::all());
    }

    public function test_re_enroll_returns_null_for_unknown_code(): void
    {
        $user = DummyUser::create(['name' => 'Unknown Re-enroll']);

        $this->assertNull($user->reEnrollLifeCycle('nonexistent-code'));
    }

    // -------------------------------------------------------------------------
    // getLifeCycleStageByCode
    // -------------------------------------------------------------------------

    public function test_get_life_cycle_stage_by_code_returns_handler_instance(): void
    {
        $user = DummyUser::create(['name' => 'Handler Test']);

        $lc = SystemLifeCycle::create([
            'name'   => 'Handler LC',
            'code'   => 'handler-lc',
            'active' => true,
        ]);

        $stage = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'Stage',
            'class'                => PassingStageService::class,
        ]);

        SystemLifeCycleModel::create([
            'system_life_cycle_id'       => $lc->id,
            'system_life_cycle_stage_id' => $stage->id,
            'model_id'                   => $user->id,
            'model_type'                 => DummyUser::class,
            'status'                     => 'pending',
            'attempts'                   => 0,
        ]);

        $handler = $user->getLifeCycleStageByCode('handler-lc');

        $this->assertNotNull($handler);
        $this->assertInstanceOf(PassingStageService::class, $handler);
    }

    public function test_get_life_cycle_stage_by_code_returns_null_when_no_lc_model(): void
    {
        $user = DummyUser::create(['name' => 'No Model Test']);

        SystemLifeCycle::create([
            'name'   => 'No Model LC',
            'code'   => 'no-model-lc',
            'active' => true,
        ]);

        // No SystemLifeCycleModel created for this user
        $handler = $user->getLifeCycleStageByCode('no-model-lc');

        $this->assertNull($handler);
    }

    public function test_get_life_cycle_stage_by_code_returns_null_when_current_stage_is_null(): void
    {
        $user = DummyUser::create(['name' => 'Null Stage Test']);

        $lc = SystemLifeCycle::create([
            'name'   => 'Null Stage LC',
            'code'   => 'null-stage-lc',
            'active' => true,
        ]);

        // Create a model with no stage assigned (stage_id = null)
        SystemLifeCycleModel::create([
            'system_life_cycle_id'       => $lc->id,
            'system_life_cycle_stage_id' => null,
            'model_id'                   => $user->id,
            'model_type'                 => DummyUser::class,
            'status'                     => 'pending',
            'attempts'                   => 0,
        ]);

        $handler = $user->getLifeCycleStageByCode('null-stage-lc');

        // Our fix: should return null instead of crashing
        $this->assertNull($handler);
    }

    // -------------------------------------------------------------------------
    // setNextLifeCycleStage
    // -------------------------------------------------------------------------

    public function test_set_next_life_cycle_stage_advances_to_second_stage(): void
    {
        $user = DummyUser::create(['name' => 'Next Stage User']);

        $lc = SystemLifeCycle::create([
            'name'   => 'Next Stage LC',
            'code'   => 'next-stage-lc',
            'active' => true,
        ]);

        $stage1 = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'Stage 1',
            'class'                => PassingStageService::class,
        ]);

        $stage2 = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 2,
            'name'                 => 'Stage 2',
            'class'                => PassingStageService::class,
        ]);

        $lcModel = SystemLifeCycleModel::create([
            'system_life_cycle_id'       => $lc->id,
            'system_life_cycle_stage_id' => $stage1->id,
            'model_id'                   => $user->id,
            'model_type'                 => DummyUser::class,
            'status'                     => 'pending',
            'attempts'                   => 0,
        ]);

        $user->setNextLifeCycleStage('next-stage-lc');

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame($stage2->id, $freshModel->system_life_cycle_stage_id);
        $this->assertSame(LifeCycleStatus::Pending, $freshModel->status);
    }

    public function test_set_next_life_cycle_stage_marks_completed_on_last_stage(): void
    {
        $user = DummyUser::create(['name' => 'Last Stage User']);

        $lc = SystemLifeCycle::create([
            'name'   => 'Last Stage LC',
            'code'   => 'last-stage-lc',
            'active' => true,
        ]);

        $stage1 = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'Only Stage',
            'class'                => PassingStageService::class,
        ]);

        $lcModel = SystemLifeCycleModel::create([
            'system_life_cycle_id'       => $lc->id,
            'system_life_cycle_stage_id' => $stage1->id,
            'model_id'                   => $user->id,
            'model_type'                 => DummyUser::class,
            'status'                     => 'pending',
            'attempts'                   => 0,
        ]);

        $user->setNextLifeCycleStage('last-stage-lc');

        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertSame(LifeCycleStatus::Completed, $freshModel->status);
    }

    public function test_set_next_life_cycle_stage_does_nothing_for_unknown_code(): void
    {
        $user = DummyUser::create(['name' => 'Unknown Stage User']);

        // Should not throw, just return silently
        $user->setNextLifeCycleStage('code-that-does-not-exist');

        // Nothing changed in the database
        $this->assertDatabaseCount('system_life_cycle_models', 0);
    }

    public function test_set_next_life_cycle_stage_does_nothing_when_current_stage_is_null(): void
    {
        $user = DummyUser::create(['name' => 'Null Stage Next']);

        $lc = SystemLifeCycle::create([
            'name'   => 'Null CS LC',
            'code'   => 'null-cs-lc',
            'active' => true,
        ]);

        $lcModel = SystemLifeCycleModel::create([
            'system_life_cycle_id'       => $lc->id,
            'system_life_cycle_stage_id' => null,
            'model_id'                   => $user->id,
            'model_type'                 => DummyUser::class,
            'status'                     => 'pending',
            'attempts'                   => 0,
        ]);

        // Should not crash — our fix guards against null currentStage
        $user->setNextLifeCycleStage('null-cs-lc');

        // Model should remain unchanged
        $freshModel = SystemLifeCycleModel::find($lcModel->id);
        $this->assertNull($freshModel->system_life_cycle_stage_id);
    }

    // -------------------------------------------------------------------------
    // removeLifeCycle
    // -------------------------------------------------------------------------

    public function test_remove_life_cycle_deletes_the_record(): void
    {
        $user = DummyUser::create(['name' => 'Remove User']);

        $lc = SystemLifeCycle::create([
            'name'   => 'Remove LC',
            'code'   => 'remove-lc',
            'active' => true,
        ]);

        $stage = SystemLifeCycleStage::create([
            'system_life_cycle_id' => $lc->id,
            'sequence'             => 1,
            'name'                 => 'Stage',
            'class'                => PassingStageService::class,
        ]);

        $lcModel = SystemLifeCycleModel::create([
            'system_life_cycle_id'       => $lc->id,
            'system_life_cycle_stage_id' => $stage->id,
            'model_id'                   => $user->id,
            'model_type'                 => DummyUser::class,
            'status'                     => 'pending',
            'attempts'                   => 0,
        ]);

        $result = $user->removeLifeCycle('remove-lc');

        $this->assertNotFalse($result);
        $this->assertNull(SystemLifeCycleModel::find($lcModel->id));
    }
}
