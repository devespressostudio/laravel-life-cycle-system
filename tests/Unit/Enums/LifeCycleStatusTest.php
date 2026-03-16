<?php

namespace Devespresso\SystemLifeCycle\Tests\Unit\Enums;

use Devespresso\SystemLifeCycle\Enums\LifeCycleStatus;
use PHPUnit\Framework\TestCase;
use ValueError;

class LifeCycleStatusTest extends TestCase
{
    public function test_all_five_cases_exist(): void
    {
        $cases = LifeCycleStatus::cases();

        $this->assertCount(5, $cases);
    }

    public function test_pending_has_correct_value(): void
    {
        $this->assertSame('pending', LifeCycleStatus::Pending->value);
    }

    public function test_processing_has_correct_value(): void
    {
        $this->assertSame('processing', LifeCycleStatus::Processing->value);
    }

    public function test_completed_has_correct_value(): void
    {
        $this->assertSame('completed', LifeCycleStatus::Completed->value);
    }

    public function test_failed_has_correct_value(): void
    {
        $this->assertSame('failed', LifeCycleStatus::Failed->value);
    }

    public function test_success_has_correct_value(): void
    {
        $this->assertSame('success', LifeCycleStatus::Success->value);
    }

    public function test_from_returns_correct_case(): void
    {
        $this->assertSame(LifeCycleStatus::Pending, LifeCycleStatus::from('pending'));
        $this->assertSame(LifeCycleStatus::Processing, LifeCycleStatus::from('processing'));
        $this->assertSame(LifeCycleStatus::Completed, LifeCycleStatus::from('completed'));
        $this->assertSame(LifeCycleStatus::Failed, LifeCycleStatus::from('failed'));
        $this->assertSame(LifeCycleStatus::Success, LifeCycleStatus::from('success'));
    }

    public function test_try_from_with_invalid_value_returns_null(): void
    {
        $result = LifeCycleStatus::tryFrom('nonexistent');

        $this->assertNull($result);
    }

    public function test_from_with_invalid_value_throws_value_error(): void
    {
        $this->expectException(ValueError::class);

        LifeCycleStatus::from('invalid_status');
    }

    public function test_cases_are_backed_by_strings(): void
    {
        foreach (LifeCycleStatus::cases() as $case) {
            $this->assertIsString($case->value);
        }
    }
}
