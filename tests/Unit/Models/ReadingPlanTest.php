<?php

namespace Tests\Unit\Models;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_plan_belongs_to_user_and_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->for($user)->for($book)->create();

        $this->assertTrue($plan->user->is($user));
        $this->assertTrue($plan->book->is($book));
    }

    public function test_user_and_book_have_reading_plans(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        ReadingPlan::factory()->for($user)->for($book)->create();
        ReadingPlan::factory()->for($user)->create();

        $this->assertCount(2, $user->readingPlans);
        $this->assertCount(1, $book->readingPlans);
    }

    public function test_status_is_cast_to_enum_and_stored_as_string(): void
    {
        $plan = ReadingPlan::factory()->create(['status' => ReadingPlanStatus::Expired]);

        // DBには文字列で保存され、モデルから読むと Enum になる
        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id, 'status' => 'expired']);
        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
    }

    public function test_status_defaults_to_in_progress(): void
    {
        $plan = ReadingPlan::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => Book::factory()->create()->id,
            'target_date' => today()->addDays(3),
        ]);

        // 保存した直後のモデルでも、読み直したモデルでも「進行中」
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->fresh()->status);
        $this->assertNull($plan->fresh()->completed_at);
    }

    public function test_dates_are_cast_to_carbon(): void
    {
        $plan = ReadingPlan::factory()->completed()->create([
            'target_date' => '2026-12-01',
            'notified_three_days_before_at' => '2026-11-28 20:00:00',
        ])->fresh();

        $this->assertInstanceOf(Carbon::class, $plan->target_date);
        $this->assertSame('2026-12-01', $plan->target_date->format('Y-m-d'));
        $this->assertInstanceOf(Carbon::class, $plan->completed_at);
        $this->assertInstanceOf(Carbon::class, $plan->notified_three_days_before_at);
        $this->assertNull($plan->notified_on_due_at);
        $this->assertNull($plan->notified_three_days_after_at);
    }

    public function test_factory_states_create_completed_and_expired_plans(): void
    {
        $completed = ReadingPlan::factory()->completed()->create();
        $expired = ReadingPlan::factory()->expired()->create();

        $this->assertSame(ReadingPlanStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(ReadingPlanStatus::Expired, $expired->status);
        $this->assertTrue($expired->target_date->isPast());
    }

    public function test_reading_plans_are_deleted_with_book_or_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        ReadingPlan::factory()->for($book)->create();
        ReadingPlan::factory()->for($user)->create();

        $book->delete();
        $this->assertDatabaseCount('reading_plans', 1);

        $user->delete();
        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_reading_plans_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('reading_plans', [
            'id', 'user_id', 'book_id', 'target_date', 'status', 'completed_at',
            'notified_three_days_before_at', 'notified_on_due_at', 'notified_three_days_after_at',
            'created_at', 'updated_at',
        ]));
    }
}
