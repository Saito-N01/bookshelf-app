<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReadingPlanReminderBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 「今日」を 2026-11-17（バッチの実行時刻の 20:00）に固定する
        Carbon::setTestNow('2026-11-17 20:00:00');

        $this->user = User::factory()->create();
    }

    /**
     * $user の読書計画を作る（計画ごとに別の書籍を割り当てる）。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createPlan(string $targetDate, array $attributes = []): ReadingPlan
    {
        return ReadingPlan::factory()
            ->for($this->user)
            ->for(Book::factory()->for($this->user))
            ->create(['target_date' => $targetDate, ...$attributes]);
    }

    /**
     * 指定した計画・種類の通知が $user に送られたことを確認する。
     */
    private function assertReminderSent(ReadingPlan $plan, ReminderTiming $timing): void
    {
        Notification::assertSentTo(
            $this->user,
            ReadingPlanReminder::class,
            fn (ReadingPlanReminder $notification) => $notification->readingPlan->is($plan)
                && $notification->timing === $timing
        );
    }

    // ---------- 通知の送信 ----------

    public function test_sends_three_kinds_of_reminders_to_matching_plans(): void
    {
        Notification::fake();
        $threeDaysBefore = $this->createPlan('2026-11-20');
        $onDueDate = $this->createPlan('2026-11-17');
        $threeDaysAfter = $this->createPlan('2026-11-14');

        $this->artisan('reading-plans:notify')->assertSuccessful();

        $this->assertReminderSent($threeDaysBefore, ReminderTiming::ThreeDaysBefore);
        $this->assertReminderSent($onDueDate, ReminderTiming::OnDueDate);
        $this->assertReminderSent($threeDaysAfter, ReminderTiming::ThreeDaysAfter);
        Notification::assertCount(3);
    }

    public function test_records_sent_time_only_for_sent_reminder(): void
    {
        Notification::fake();
        $plan = $this->createPlan('2026-11-20');

        $this->artisan('reading-plans:notify');

        $plan->refresh();
        $this->assertSame('2026-11-17 20:00:00', $plan->notified_three_days_before_at->format('Y-m-d H:i:s'));
        $this->assertNull($plan->notified_on_due_at);
        $this->assertNull($plan->notified_three_days_after_at);
    }

    public function test_notification_data_has_timing_title_and_body(): void
    {
        Notification::fake();
        $book = Book::factory()->for($this->user)->create(['title' => 'リーダブルコード']);
        ReadingPlan::factory()->for($this->user)->for($book)->create(['target_date' => '2026-11-17']);

        $this->artisan('reading-plans:notify');

        Notification::assertSentTo(
            $this->user,
            ReadingPlanReminder::class,
            function (ReadingPlanReminder $notification, array $channels): bool {
                $data = $notification->toArray($this->user);

                return $channels === ['database']
                    && $data['timing'] === 'on_due_date'
                    && $data['title'] === '今日が読書計画の期日です'
                    && $data['body'] === '『リーダブルコード』の期日は今日（2026-11-17）です。'
                    && $data['book_title'] === 'リーダブルコード'
                    && $data['target_date'] === '2026-11-17';
            }
        );
    }

    public function test_does_not_send_to_plans_outside_target_dates(): void
    {
        Notification::fake();
        // 7日後・4日後・2日後・明日・昨日・2日前・4日前は、どの通知の対象でもない
        foreach (['2026-11-24', '2026-11-21', '2026-11-19', '2026-11-18', '2026-11-16', '2026-11-15', '2026-11-13'] as $date) {
            $this->createPlan($date);
        }

        $this->artisan('reading-plans:notify')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_does_not_send_to_completed_plans(): void
    {
        Notification::fake();
        foreach (['2026-11-20', '2026-11-17', '2026-11-14'] as $date) {
            $this->createPlan($date, ['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);
        }

        $this->artisan('reading-plans:notify');

        Notification::assertNothingSent();
        $this->assertSame(3, ReadingPlan::where('status', ReadingPlanStatus::Completed)->count());
    }

    public function test_sends_to_each_plan_owner(): void
    {
        Notification::fake();
        $other = User::factory()->create();
        $this->createPlan('2026-11-17');
        ReadingPlan::factory()->for($other)->create(['target_date' => '2026-11-17']);

        $this->artisan('reading-plans:notify');

        Notification::assertSentToTimes($this->user, ReadingPlanReminder::class, 1);
        Notification::assertSentToTimes($other, ReadingPlanReminder::class, 1);
    }

    // ---------- 二重送信の防止 ----------

    public function test_does_not_send_same_reminder_twice_when_rerun(): void
    {
        Notification::fake();
        $this->createPlan('2026-11-20');
        $this->createPlan('2026-11-17');
        $this->createPlan('2026-11-14');

        $this->artisan('reading-plans:notify');
        $this->artisan('reading-plans:notify');

        Notification::assertCount(3);
    }

    public function test_same_plan_receives_each_kind_of_reminder_once_as_days_pass(): void
    {
        // Notification::fake() を使わず、実際に notifications テーブルへ保存されることも確認する
        $plan = $this->createPlan('2026-11-20');

        // notifications リレーションは新しい順が既定のため、reorder で古い順に並べ直す
        $timings = fn (): array => $this->user->notifications()->reorder('created_at')->get()
            ->pluck('data.timing')->all();

        // 11/17: 3日前
        $this->artisan('reading-plans:notify');
        $this->assertSame(['three_days_before'], $timings());

        // 11/18・11/19: 何も送られない
        Carbon::setTestNow('2026-11-18 20:00:00');
        $this->artisan('reading-plans:notify');
        Carbon::setTestNow('2026-11-19 20:00:00');
        $this->artisan('reading-plans:notify');
        $this->assertSame(['three_days_before'], $timings());

        // 11/20: 当日
        Carbon::setTestNow('2026-11-20 20:00:00');
        $this->artisan('reading-plans:notify');
        $this->assertSame(['three_days_before', 'on_due_date'], $timings());
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->fresh()->status);

        // 11/21: 期限切れになる（通知はなし）
        Carbon::setTestNow('2026-11-21 20:00:00');
        $this->artisan('reading-plans:notify');
        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
        $this->assertCount(2, $timings());

        // 11/23: 3日後
        Carbon::setTestNow('2026-11-23 20:00:00');
        $this->artisan('reading-plans:notify');
        $this->assertSame(['three_days_before', 'on_due_date', 'three_days_after'], $timings());
        $this->assertDatabaseCount('notifications', 3);
    }

    public function test_reminder_is_sent_again_after_target_date_is_changed(): void
    {
        Notification::fake();
        $plan = $this->createPlan('2026-11-17');
        $this->artisan('reading-plans:notify');

        // 期日を変更すると送信日時がリセットされ（#24）、新しい期日の当日にもう一度通知される
        $this->actingAs($this->user)->put('/reading-plans/'.$plan->id, ['target_date' => '2026-11-18']);
        Carbon::setTestNow('2026-11-18 20:00:00');
        $this->artisan('reading-plans:notify');

        Notification::assertSentToTimes($this->user, ReadingPlanReminder::class, 2);
    }

    // ---------- 期限切れへの更新 ----------

    public function test_expires_overdue_in_progress_plans(): void
    {
        Notification::fake();
        $yesterday = $this->createPlan('2026-11-16');
        $longAgo = $this->createPlan('2026-10-01');

        $this->artisan('reading-plans:notify');

        $this->assertSame(ReadingPlanStatus::Expired, $yesterday->fresh()->status);
        $this->assertSame(ReadingPlanStatus::Expired, $longAgo->fresh()->status);
    }

    public function test_does_not_expire_plans_due_today_or_later_or_completed(): void
    {
        Notification::fake();
        $today = $this->createPlan('2026-11-17');
        $tomorrow = $this->createPlan('2026-11-18');
        $completed = $this->createPlan('2026-11-10', [
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => '2026-11-12 09:00:00',
        ]);

        $this->artisan('reading-plans:notify');

        $this->assertSame(ReadingPlanStatus::InProgress, $today->fresh()->status);
        $this->assertSame(ReadingPlanStatus::InProgress, $tomorrow->fresh()->status);
        $this->assertSame(ReadingPlanStatus::Completed, $completed->fresh()->status);
    }

    public function test_plan_overdue_by_three_days_is_expired_and_notified_in_one_run(): void
    {
        // シーダー3番のシナリオ: 期日を3日過ぎた「進行中」の計画は、1回の実行で
        // 期限切れへの更新と、3日後の通知の両方の対象になる
        Notification::fake();
        $plan = $this->createPlan('2026-11-14');

        $this->artisan('reading-plans:notify');

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
        $this->assertReminderSent($plan, ReminderTiming::ThreeDaysAfter);
        $this->assertNotNull($plan->fresh()->notified_three_days_after_at);
    }

    // ---------- 初期データでの動作 ----------

    public function test_seeded_plans_produce_expected_notifications(): void
    {
        $this->seed();
        $yamada = User::where('email', 'yamada@example.com')->firstOrFail();
        $suzuki = User::where('email', 'suzuki@example.com')->firstOrFail();

        $this->artisan('reading-plans:notify')
            ->expectsOutput('期限切れに更新: 1件')
            ->assertSuccessful();

        // 山田太郎: 3日前・当日・3日後の3件。鈴木花子（5日後）: なし
        $this->assertEqualsCanonicalizing(
            ['three_days_before', 'on_due_date', 'three_days_after'],
            $yamada->notifications()->get()->pluck('data.timing')->all()
        );
        $this->assertSame(0, $suzuki->notifications()->count());
        $this->assertSame(3, $yamada->unreadNotifications()->count());
        $this->assertSame(1, ReadingPlan::where('status', ReadingPlanStatus::Expired)->count());
    }

    // ---------- スケジュール ----------

    public function test_command_is_scheduled_daily_at_20_in_japan_time(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains((string) $event->command, 'reading-plans:notify'));

        $this->assertNotNull($event, 'reading-plans:notify がスケジュールに登録されていません。');
        // cron 式「0 20 * * *」= 毎日 20:00
        $this->assertSame('0 20 * * *', $event->expression);
        $this->assertSame('Asia/Tokyo', (string) $event->timezone);
    }
}
