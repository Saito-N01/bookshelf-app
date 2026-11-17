<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        // 「今日」を固定して、期日のバリデーションを確認しやすくする
        Carbon::setTestNow('2026-11-17 10:00:00');

        // $user と、$user が登録した書籍
        $this->user = User::factory()->create();
        $this->book = Book::factory()->for($this->user)->create(['title' => '自分の書籍', 'author' => '自分の著者']);
    }

    /**
     * $user の読書計画を作る（書籍は $user が登録したもの）。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createPlan(array $attributes = [], ?Book $book = null): ReadingPlan
    {
        return ReadingPlan::factory()
            ->for($this->user)
            ->for($book ?? $this->book)
            ->create($attributes);
    }

    // ---------- 一覧 ----------

    public function test_index_shows_only_own_plans(): void
    {
        $this->createPlan();
        $othersBook = Book::factory()->create(['title' => '他人の書籍']);
        ReadingPlan::factory()->for($othersBook)->create();

        $response = $this->actingAs($this->user)->get('/reading-plans');

        $response->assertOk()
            ->assertSee('自分の書籍')
            ->assertDontSee('他人の書籍')
            ->assertViewHas('readingPlans', fn (Collection $plans) => $plans->count() === 1
                && $plans->first()->relationLoaded('book'));
    }

    public function test_index_is_ordered_by_target_date(): void
    {
        $later = $this->createPlan(['target_date' => '2026-12-10'], Book::factory()->for($this->user)->create());
        $sooner = $this->createPlan(['target_date' => '2026-11-20']);

        $this->actingAs($this->user)->get('/reading-plans')
            ->assertViewHas('readingPlans', fn (Collection $plans) => $plans->pluck('id')->all() === [$sooner->id, $later->id]);
    }

    public function test_index_filters_by_status(): void
    {
        $inProgress = $this->createPlan();
        $completed = ReadingPlan::factory()->completed()->for($this->user)->create();
        $expired = ReadingPlan::factory()->expired()->for($this->user)->create();

        $ids = fn (string $url): array => $this->actingAs($this->user)->get($url)
            ->assertOk()->viewData('readingPlans')->pluck('id')->sort()->values()->all();

        $this->assertSame([$inProgress->id], $ids('/reading-plans?status=in_progress'));
        $this->assertSame([$completed->id], $ids('/reading-plans?status=completed'));
        $this->assertSame([$expired->id], $ids('/reading-plans?status=expired'));
        // 未指定・空・不正な値は「すべて」
        $all = [$inProgress->id, $completed->id, $expired->id];
        $this->assertSame($all, $ids('/reading-plans'));
        $this->assertSame($all, $ids('/reading-plans?status='));
        $this->assertSame($all, $ids('/reading-plans?status=invalid'));
        $this->assertSame($all, $ids('/reading-plans?status[]=completed'));
    }

    public function test_index_keeps_selected_status_and_shows_labels_and_completed_date(): void
    {
        ReadingPlan::factory()->completed()->for($this->user)->create(['completed_at' => '2026-11-10 09:00:00']);

        $this->actingAs($this->user)->get('/reading-plans?status=completed')
            ->assertViewHas('currentStatus', 'completed')
            ->assertSee('<option value="completed" selected>', false)
            ->assertSee('2026-11-10')
            ->assertSee('完了');

        $this->actingAs($this->user)->get('/reading-plans?status=invalid')->assertViewHas('currentStatus', '');
    }

    public function test_index_hides_complete_and_edit_actions_for_completed_plan(): void
    {
        $completed = ReadingPlan::factory()->completed()->for($this->user)->create();

        $this->actingAs($this->user)->get('/reading-plans')
            ->assertDontSee(route('reading-plans.edit', $completed))
            ->assertDontSee('読了する')
            ->assertSee('削除');
    }

    public function test_index_shows_message_when_no_plans(): void
    {
        $this->actingAs($this->user)->get('/reading-plans')
            ->assertOk()
            ->assertSee('該当する読書計画はありません。');
    }

    // ---------- 作成画面 ----------

    public function test_create_shows_only_books_registered_by_user(): void
    {
        Book::factory()->create(['title' => '他人の書籍']);

        $this->actingAs($this->user)->get('/reading-plans/create')
            ->assertOk()
            ->assertSee('自分の書籍（自分の著者）')
            ->assertDontSee('他人の書籍')
            ->assertViewHas('books', fn (Collection $books) => $books->pluck('id')->all() === [$this->book->id]);
    }

    // ---------- 登録 ----------

    public function test_store_creates_in_progress_plan(): void
    {
        $response = $this->actingAs($this->user)->post('/reading-plans', [
            'book_id' => $this->book->id,
            'target_date' => '2026-11-30',
        ]);

        $response->assertRedirect('/reading-plans')->assertSessionHas('success', '読書計画を登録しました。');
        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'status' => 'in_progress',
            'completed_at' => null,
        ]);
        $this->assertSame('2026-11-30', ReadingPlan::first()->target_date->format('Y-m-d'));
    }

    public function test_store_accepts_today_as_target_date(): void
    {
        $this->actingAs($this->user)
            ->post('/reading-plans', ['book_id' => $this->book->id, 'target_date' => '2026-11-17'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reading_plans', 1);
    }

    public function test_store_fails_when_required_fields_are_missing(): void
    {
        $response = $this->actingAs($this->user)->from('/reading-plans/create')->post('/reading-plans', []);

        $response->assertRedirect('/reading-plans/create')->assertSessionHasErrors([
            'book_id' => '書籍は必須です。',
            'target_date' => '期日は必須です。',
        ]);
        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_store_fails_when_target_date_is_past_or_invalid(): void
    {
        $this->actingAs($this->user)
            ->post('/reading-plans', ['book_id' => $this->book->id, 'target_date' => '2026-11-16'])
            ->assertSessionHasErrors(['target_date' => '期日は今日以降の日付を指定してください。']);

        $this->actingAs($this->user)
            ->post('/reading-plans', ['book_id' => $this->book->id, 'target_date' => 'not-a-date'])
            ->assertSessionHasErrors(['target_date' => '期日は有効な日付形式で入力してください。']);

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_store_fails_when_book_does_not_exist_or_is_not_registered_by_user(): void
    {
        $othersBook = Book::factory()->create();

        foreach ([9999, 'abc', $othersBook->id] as $bookId) {
            $this->actingAs($this->user)
                ->post('/reading-plans', ['book_id' => $bookId, 'target_date' => '2026-11-30'])
                ->assertSessionHasErrors(['book_id' => '選択された書籍が存在しません。']);
        }

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_store_fails_when_in_progress_plan_exists_for_same_book(): void
    {
        $this->createPlan();

        $response = $this->actingAs($this->user)->from('/reading-plans/create')->post('/reading-plans', [
            'book_id' => $this->book->id,
            'target_date' => '2026-11-30',
        ]);

        // 入力値を保持して作成画面に戻る
        $response->assertRedirect('/reading-plans/create')
            ->assertSessionHasErrors(['book_id' => 'この書籍の読書計画は既に進行中です。'])
            ->assertSessionHasInput('target_date', '2026-11-30');
        $this->assertDatabaseCount('reading_plans', 1);
    }

    public function test_store_succeeds_when_only_completed_or_expired_plans_exist_for_same_book(): void
    {
        $this->createPlan(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);
        $this->createPlan(['status' => ReadingPlanStatus::Expired, 'target_date' => '2026-11-01']);

        $this->actingAs($this->user)
            ->post('/reading-plans', ['book_id' => $this->book->id, 'target_date' => '2026-11-30'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reading_plans', 3);
    }

    public function test_another_users_in_progress_plan_does_not_block_store(): void
    {
        ReadingPlan::factory()->for($this->book)->create();

        $this->actingAs($this->user)
            ->post('/reading-plans', ['book_id' => $this->book->id, 'target_date' => '2026-11-30'])
            ->assertSessionHasNoErrors();
    }

    // ---------- 読了 ----------

    public function test_complete_updates_status_and_completed_at(): void
    {
        $plan = $this->createPlan();

        $response = $this->actingAs($this->user)->post('/reading-plans/'.$plan->id.'/complete');

        $response->assertRedirect('/reading-plans')->assertSessionHas('success', '読了しました。');
        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::Completed, $plan->status);
        $this->assertSame('2026-11-17 10:00:00', $plan->completed_at->format('Y-m-d H:i:s'));
    }

    public function test_expired_plan_can_be_completed(): void
    {
        $plan = $this->createPlan(['status' => ReadingPlanStatus::Expired, 'target_date' => '2026-11-10']);

        $this->actingAs($this->user)->post('/reading-plans/'.$plan->id.'/complete')->assertRedirect('/reading-plans');

        $this->assertSame(ReadingPlanStatus::Completed, $plan->fresh()->status);
        $this->assertNotNull($plan->fresh()->completed_at);
    }

    public function test_completed_plan_cannot_be_completed_again(): void
    {
        $plan = $this->createPlan(['status' => ReadingPlanStatus::Completed, 'completed_at' => '2026-11-10 09:00:00']);

        $this->actingAs($this->user)->post('/reading-plans/'.$plan->id.'/complete')->assertForbidden();

        // 完了日時は書き換わらない
        $this->assertSame('2026-11-10 09:00:00', $plan->fresh()->completed_at->format('Y-m-d H:i:s'));
    }

    // ---------- 編集・更新 ----------

    public function test_edit_shows_book_title_status_and_current_target_date(): void
    {
        $plan = $this->createPlan(['target_date' => '2026-11-25']);

        $this->actingAs($this->user)->get('/reading-plans/'.$plan->id.'/edit')
            ->assertOk()
            ->assertSee('自分の書籍')
            ->assertSee('進行中')
            ->assertSee('value="2026-11-25"', false);
    }

    public function test_update_changes_target_date(): void
    {
        $plan = $this->createPlan(['target_date' => '2026-11-25']);

        $response = $this->actingAs($this->user)->put('/reading-plans/'.$plan->id, ['target_date' => '2026-12-05']);

        $response->assertRedirect('/reading-plans')->assertSessionHas('success', '読書計画を更新しました。');
        $plan->refresh();
        $this->assertSame('2026-12-05', $plan->target_date->format('Y-m-d'));
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
    }

    public function test_update_fails_when_target_date_is_past_or_missing(): void
    {
        $plan = $this->createPlan(['target_date' => '2026-11-25']);

        $this->actingAs($this->user)->from('/reading-plans/'.$plan->id.'/edit')
            ->put('/reading-plans/'.$plan->id, ['target_date' => '2026-11-16'])
            ->assertRedirect('/reading-plans/'.$plan->id.'/edit')
            ->assertSessionHasErrors(['target_date' => '期日は今日以降の日付を指定してください。']);

        $this->actingAs($this->user)
            ->put('/reading-plans/'.$plan->id, ['target_date' => ''])
            ->assertSessionHasErrors(['target_date' => '期日は必須です。']);

        $this->assertSame('2026-11-25', $plan->fresh()->target_date->format('Y-m-d'));
    }

    public function test_update_resets_reminder_timestamps_when_target_date_changes(): void
    {
        $plan = $this->createPlan([
            'target_date' => '2026-11-20',
            'notified_three_days_before_at' => '2026-11-17 09:00:00',
            'notified_on_due_at' => '2026-11-17 09:00:00',
            'notified_three_days_after_at' => '2026-11-17 09:00:00',
        ]);

        $this->actingAs($this->user)->put('/reading-plans/'.$plan->id, ['target_date' => '2026-12-05']);

        $plan->refresh();
        $this->assertNull($plan->notified_three_days_before_at);
        $this->assertNull($plan->notified_on_due_at);
        $this->assertNull($plan->notified_three_days_after_at);
    }

    public function test_update_keeps_reminder_timestamps_when_target_date_is_not_changed(): void
    {
        $plan = $this->createPlan([
            'target_date' => '2026-11-20',
            'notified_three_days_before_at' => '2026-11-17 09:00:00',
        ]);

        $this->actingAs($this->user)
            ->put('/reading-plans/'.$plan->id, ['target_date' => '2026-11-20'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($plan->fresh()->notified_three_days_before_at);
    }

    public function test_update_returns_expired_plan_to_in_progress_and_resets_reminders(): void
    {
        $plan = $this->createPlan([
            'status' => ReadingPlanStatus::Expired,
            'target_date' => '2026-11-10',
            'notified_three_days_after_at' => '2026-11-13 09:00:00',
        ]);

        $this->actingAs($this->user)
            ->put('/reading-plans/'.$plan->id, ['target_date' => '2026-11-30'])
            ->assertRedirect('/reading-plans');

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
        $this->assertSame('2026-11-30', $plan->target_date->format('Y-m-d'));
        $this->assertNull($plan->notified_three_days_after_at);
    }

    public function test_update_fails_when_another_in_progress_plan_exists_for_same_book(): void
    {
        // 同じ書籍に「期限切れ」と「進行中」の計画がある状態で、期限切れのほうを進行中に戻そうとする
        $expired = $this->createPlan(['status' => ReadingPlanStatus::Expired, 'target_date' => '2026-11-10']);
        $this->createPlan();

        $this->actingAs($this->user)
            ->put('/reading-plans/'.$expired->id, ['target_date' => '2026-11-30'])
            ->assertSessionHasErrors(['target_date' => 'この書籍の読書計画は既に進行中です。']);

        $this->assertSame(ReadingPlanStatus::Expired, $expired->fresh()->status);
    }

    public function test_completed_plan_cannot_be_edited_or_updated(): void
    {
        $plan = $this->createPlan([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
            'target_date' => '2026-11-10',
        ]);

        $this->actingAs($this->user)->get('/reading-plans/'.$plan->id.'/edit')->assertForbidden();
        $this->actingAs($this->user)->put('/reading-plans/'.$plan->id, ['target_date' => '2026-12-05'])->assertForbidden();

        $this->assertSame('2026-11-10', $plan->fresh()->target_date->format('Y-m-d'));
    }

    // ---------- 削除 ----------

    public function test_destroy_deletes_plan_in_any_status(): void
    {
        $inProgress = $this->createPlan();
        $completed = $this->createPlan(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);

        $this->actingAs($this->user)->delete('/reading-plans/'.$inProgress->id)
            ->assertRedirect('/reading-plans')
            ->assertSessionHas('success', '読書計画を削除しました。');
        $this->actingAs($this->user)->delete('/reading-plans/'.$completed->id)->assertRedirect('/reading-plans');

        $this->assertDatabaseCount('reading_plans', 0);
        // 書籍は削除されない
        $this->assertDatabaseHas('books', ['id' => $this->book->id]);
    }

    // ---------- 認可（他人の計画は403）・認証（ゲストは /login） ----------

    public function test_other_user_gets_403_for_all_operations(): void
    {
        $plan = $this->createPlan(['target_date' => '2026-11-25']);
        $other = User::factory()->create();

        $this->actingAs($other)->get('/reading-plans/'.$plan->id.'/edit')->assertForbidden();
        $this->actingAs($other)->put('/reading-plans/'.$plan->id, ['target_date' => '2026-12-05'])->assertForbidden();
        // 入力内容が不正でも、バリデーションより先に403になる
        $this->actingAs($other)->put('/reading-plans/'.$plan->id, [])->assertForbidden();
        $this->actingAs($other)->post('/reading-plans/'.$plan->id.'/complete')->assertForbidden();
        $this->actingAs($other)->delete('/reading-plans/'.$plan->id)->assertForbidden();

        $plan->refresh();
        $this->assertSame('2026-11-25', $plan->target_date->format('Y-m-d'));
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $plan = $this->createPlan();

        $this->get('/reading-plans')->assertRedirect('/login');
        $this->get('/reading-plans/create')->assertRedirect('/login');
        $this->post('/reading-plans', [])->assertRedirect('/login');
        $this->get('/reading-plans/'.$plan->id.'/edit')->assertRedirect('/login');
        $this->put('/reading-plans/'.$plan->id, [])->assertRedirect('/login');
        $this->post('/reading-plans/'.$plan->id.'/complete')->assertRedirect('/login');
        $this->delete('/reading-plans/'.$plan->id)->assertRedirect('/login');

        $this->assertDatabaseCount('reading_plans', 1);
    }

    public function test_nonexistent_plan_returns_404(): void
    {
        $this->actingAs($this->user)->get('/reading-plans/9999/edit')->assertNotFound();
        // 詳細画面はない
        $this->actingAs($this->user)->get('/reading-plans/9999')->assertStatus(405);
    }

    public function test_navigation_has_link_to_reading_plans(): void
    {
        $this->actingAs($this->user)->get('/books')->assertSee(route('reading-plans.index'));
    }
}
