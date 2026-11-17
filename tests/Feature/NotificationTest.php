<?php

namespace Tests\Feature;

use App\Enums\ReminderTiming;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ReadingPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-17 20:00:00');

        $this->user = User::factory()->create();
        $book = Book::factory()->for($this->user)->create(['title' => 'リーダブルコード']);
        $this->plan = ReadingPlan::factory()->for($this->user)->for($book)->create(['target_date' => '2026-11-20']);
    }

    /**
     * $user（または指定したユーザー）にリマインダーを1件送り、保存された通知を返す。
     */
    private function sendReminder(ReminderTiming $timing, ?User $to = null): DatabaseNotification
    {
        $user = $to ?? $this->user;
        $user->notify(new ReadingPlanReminder($this->plan, $timing));

        return $user->notifications()->latest()->firstOrFail();
    }

    // ---------- 通知の内容（DatabaseChannel） ----------

    public function test_reminder_is_stored_in_notifications_table_with_expected_data(): void
    {
        $notification = $this->sendReminder(ReminderTiming::ThreeDaysBefore);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(ReadingPlanReminder::class, $notification->type);
        $this->assertNull($notification->read_at);
        $this->assertSame([
            'timing' => 'three_days_before',
            'title' => '読書計画の期日が近づいています',
            'body' => '『リーダブルコード』の期日（2026-11-20）まであと3日です。',
            'plan_id' => $this->plan->id,
            'book_title' => 'リーダブルコード',
            'target_date' => '2026-11-20',
        ], $notification->data);
    }

    public function test_title_and_body_are_defined_for_each_timing(): void
    {
        $onDue = $this->sendReminder(ReminderTiming::OnDueDate);
        $this->assertSame('on_due_date', $onDue->data['timing']);
        $this->assertSame('今日が読書計画の期日です', $onDue->data['title']);
        $this->assertSame('『リーダブルコード』の期日は今日（2026-11-20）です。', $onDue->data['body']);

        $onDue->delete();
        $after = $this->sendReminder(ReminderTiming::ThreeDaysAfter);
        $this->assertSame('three_days_after', $after->data['timing']);
        $this->assertSame('読書計画の期日を過ぎました', $after->data['title']);
        $this->assertSame(
            '『リーダブルコード』の期日（2026-11-20）から3日が過ぎました。期日を変更するか、読了を記録しましょう。',
            $after->data['body']
        );
    }

    public function test_reminder_uses_database_channel_only(): void
    {
        $reminder = new ReadingPlanReminder($this->plan, ReminderTiming::OnDueDate);

        $this->assertSame(['database'], $reminder->via($this->user));
    }

    // ---------- 通知一覧 ----------

    public function test_index_shows_own_notifications_newest_first(): void
    {
        Carbon::setTestNow('2026-11-17 20:00:00');
        $older = $this->sendReminder(ReminderTiming::ThreeDaysBefore);
        Carbon::setTestNow('2026-11-20 20:00:00');
        $newer = $this->sendReminder(ReminderTiming::OnDueDate);
        // 他人の通知は表示されない
        $this->sendReminder(ReminderTiming::ThreeDaysAfter, User::factory()->create());

        $response = $this->actingAs($this->user)->get('/notifications');

        $response->assertOk()
            ->assertViewHas('notifications', fn ($notifications) => $notifications->pluck('id')->all() === [$newer->id, $older->id])
            ->assertSeeInOrder(['今日が読書計画の期日です', '読書計画の期日が近づいています'])
            ->assertDontSee('読書計画の期日を過ぎました');
    }

    public function test_index_distinguishes_unread_and_read_notifications(): void
    {
        $notification = $this->sendReminder(ReminderTiming::OnDueDate);

        // 未読: 「未読」バッジと「既読にする」ボタンがある
        $this->actingAs($this->user)->get('/notifications')
            ->assertSee('未読')
            ->assertSee('既読にする')
            ->assertSee(route('notifications.read', $notification->id));

        $notification->markAsRead();

        // 既読: 一覧には残るが、バッジとボタンは表示されない
        $this->actingAs($this->user)->get('/notifications')
            ->assertSee('今日が読書計画の期日です')
            ->assertDontSee('未読')
            ->assertDontSee('既読にする');
    }

    public function test_index_shows_message_when_no_notifications(): void
    {
        $this->actingAs($this->user)->get('/notifications')
            ->assertOk()
            ->assertSee('通知はありません。');
    }

    // ---------- 既読化 ----------

    public function test_read_marks_notification_as_read_and_returns_back(): void
    {
        $notification = $this->sendReminder(ReminderTiming::OnDueDate);

        $response = $this->actingAs($this->user)
            ->from('/notifications')
            ->post('/notifications/'.$notification->id.'/read');

        $response->assertRedirect('/notifications');
        $this->assertNotNull($notification->fresh()->read_at);
        // 既読にしても、通知は削除されない
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_read_returns_404_for_another_users_notification(): void
    {
        $other = User::factory()->create();
        $notification = $this->sendReminder(ReminderTiming::OnDueDate, $other);

        $this->actingAs($this->user)
            ->post('/notifications/'.$notification->id.'/read')
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_read_returns_404_for_nonexistent_notification(): void
    {
        $this->actingAs($this->user)
            ->post('/notifications/00000000-0000-0000-0000-000000000000/read')
            ->assertNotFound();
    }

    // ---------- ヘッダーの未読件数 ----------

    public function test_header_shows_unread_count_badge(): void
    {
        $first = $this->sendReminder(ReminderTiming::ThreeDaysBefore);
        $this->sendReminder(ReminderTiming::OnDueDate);
        // 他人の未読は数えない
        $this->sendReminder(ReminderTiming::ThreeDaysAfter, User::factory()->create());

        $badge = fn (int $count): string => 'bg-red-600 rounded-full">'.$count.'</span>';

        $this->actingAs($this->user)->get('/books')
            ->assertSee(route('notifications.index'))
            ->assertSee($badge(2), false);

        $first->markAsRead();
        // 実際のリクエストと同じように、ユーザーを読み直してから数え直す
        // （同じモデルを使い回すと、1回目に読み込んだ未読の通知が残ってしまう）
        $this->actingAs($this->user->fresh())->get('/books')->assertSee($badge(1), false);
    }

    public function test_header_hides_badge_when_no_unread_notifications(): void
    {
        $this->sendReminder(ReminderTiming::OnDueDate)->markAsRead();

        $this->actingAs($this->user)->get('/books')
            ->assertSee(route('notifications.index'))
            ->assertDontSee('bg-red-600 rounded-full">', false);
    }

    public function test_guest_pages_are_displayed_without_notification_bell_error(): void
    {
        // ゲストにはログインユーザーがいないため、未読件数を数えずに表示できること
        $this->get('/books')->assertOk();
        $this->get('/ranking')->assertOk();
    }

    // ---------- 認証 ----------

    public function test_guest_is_redirected_to_login(): void
    {
        $notification = $this->sendReminder(ReminderTiming::OnDueDate);

        $this->get('/notifications')->assertRedirect('/login');
        $this->post('/notifications/'.$notification->id.'/read')->assertRedirect('/login');

        $this->assertNull($notification->fresh()->read_at);
    }

    // ---------- 関連データ ----------

    public function test_notifications_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at',
        ]));
    }
}
