<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 期日は「今日」を起点に決まるため、日付を固定して確認する
        Carbon::setTestNow('2026-11-17 10:00:00');
        $this->seed();
    }

    public function test_seeder_creates_five_plans_for_yamada_and_one_for_suzuki(): void
    {
        $yamada = User::where('email', 'yamada@example.com')->firstOrFail();
        $suzuki = User::where('email', 'suzuki@example.com')->firstOrFail();

        $this->assertSame(6, ReadingPlan::count());
        $this->assertSame(5, $yamada->readingPlans()->count());
        $this->assertSame(1, $suzuki->readingPlans()->count());
    }

    public function test_plans_have_expected_target_date_and_status(): void
    {
        $plans = ReadingPlan::orderBy('id')->get();

        $actual = $plans->map(fn (ReadingPlan $plan): array => [
            $plan->target_date->format('Y-m-d'),
            $plan->status,
            $plan->completed_at?->format('Y-m-d'),
        ])->all();

        $this->assertSame([
            ['2026-11-20', ReadingPlanStatus::InProgress, null], // 3日前リマインダーの対象
            ['2026-11-17', ReadingPlanStatus::InProgress, null], // 当日リマインダーの対象
            ['2026-11-14', ReadingPlanStatus::InProgress, null], // 期限切れへの変更 + 3日後の通知の対象
            ['2026-11-24', ReadingPlanStatus::InProgress, null], // リマインダーの対象外
            ['2026-11-07', ReadingPlanStatus::Completed, '2026-11-12'], // 完了済み
            ['2026-11-22', ReadingPlanStatus::InProgress, null], // 他のユーザーの計画
        ], $actual);
    }

    public function test_each_plan_is_assigned_to_different_book(): void
    {
        $bookIds = ReadingPlan::pluck('book_id');

        $this->assertCount(6, $bookIds->unique());
    }

    public function test_no_reminder_has_been_sent_yet(): void
    {
        $this->assertSame(0, ReadingPlan::whereNotNull('notified_three_days_before_at')
            ->orWhereNotNull('notified_on_due_at')
            ->orWhereNotNull('notified_three_days_after_at')
            ->count());
    }
}
