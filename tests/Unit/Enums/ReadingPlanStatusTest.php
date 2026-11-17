<?php

namespace Tests\Unit\Enums;

use App\Enums\ReadingPlanStatus;
use PHPUnit\Framework\TestCase;

class ReadingPlanStatusTest extends TestCase
{
    public function test_cases_have_expected_values_in_order(): void
    {
        // 一覧画面の絞り込みプルダウンは cases() の順（進行中 → 完了 → 期限切れ）で表示される
        $this->assertSame(
            ['in_progress', 'completed', 'expired'],
            array_column(ReadingPlanStatus::cases(), 'value')
        );
    }

    public function test_label_returns_japanese_name(): void
    {
        $this->assertSame('進行中', ReadingPlanStatus::InProgress->label());
        $this->assertSame('完了', ReadingPlanStatus::Completed->label());
        $this->assertSame('期限切れ', ReadingPlanStatus::Expired->label());
    }

    public function test_badge_class_returns_different_color_for_each_status(): void
    {
        $this->assertSame('bg-blue-100 text-blue-800', ReadingPlanStatus::InProgress->badgeClass());
        $this->assertSame('bg-green-100 text-green-800', ReadingPlanStatus::Completed->badgeClass());
        $this->assertSame('bg-red-100 text-red-800', ReadingPlanStatus::Expired->badgeClass());
    }

    public function test_status_can_be_created_from_string_value(): void
    {
        $this->assertSame(ReadingPlanStatus::Expired, ReadingPlanStatus::from('expired'));
        // 不正な値は例外にならず null になる（一覧の絞り込みで、不正な status を無視するために使う）
        $this->assertNull(ReadingPlanStatus::tryFrom('invalid'));
    }
}
