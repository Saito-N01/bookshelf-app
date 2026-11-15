<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->book = Book::factory()->create();
    }

    // ---------- 投稿 ----------

    public function test_user_can_post_review(): void
    {
        $response = $this->actingAs($this->user)->post('/books/'.$this->book->id.'/reviews', [
            'rating' => 4,
            'comment' => 'とても参考になりました。',
        ]);

        $response->assertRedirect('/books/'.$this->book->id);
        $response->assertSessionHas('success', 'レビューを投稿しました。');
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'rating' => 4,
            'comment' => 'とても参考になりました。',
        ]);
    }

    public function test_user_can_post_review_without_comment(): void
    {
        $this->actingAs($this->user)->post('/books/'.$this->book->id.'/reviews', [
            'rating' => 5,
            'comment' => '',
        ]);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'rating' => 5,
            'comment' => null,
        ]);
    }

    public function test_guest_cannot_post_review(): void
    {
        $this->post('/books/'.$this->book->id.'/reviews', ['rating' => 4])->assertRedirect('/login');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_post_fails_when_rating_is_not_selected(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/books/'.$this->book->id)
            ->post('/books/'.$this->book->id.'/reviews', ['rating' => '', 'comment' => '入力したコメント']);

        $response->assertRedirect('/books/'.$this->book->id);
        $response->assertSessionHasErrors(['rating' => '評価は必須です。']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_post_fails_when_rating_is_out_of_range_or_not_integer(): void
    {
        foreach ([0, 6, '2.5', 'abc'] as $rating) {
            $response = $this->actingAs($this->user)
                ->post('/books/'.$this->book->id.'/reviews', ['rating' => $rating]);

            $response->assertSessionHasErrors(['rating' => '評価は1〜5の整数で選択してください。']);
        }
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_post_fails_when_comment_is_too_long(): void
    {
        $response = $this->actingAs($this->user)->post('/books/'.$this->book->id.'/reviews', [
            'rating' => 4,
            'comment' => str_repeat('あ', 1001),
        ]);

        $response->assertSessionHasErrors(['comment' => 'コメントは1000文字以内で入力してください。']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_cannot_post_review_twice_for_same_book(): void
    {
        Review::factory()->for($this->user)->for($this->book)->create();

        $response = $this->actingAs($this->user)
            ->post('/books/'.$this->book->id.'/reviews', ['rating' => 4]);

        $response->assertSessionHasErrors(['rating' => 'この書籍には既にレビューを投稿しています。']);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_user_can_post_review_for_book_reviewed_by_another_user(): void
    {
        Review::factory()->for($this->book)->create();

        $response = $this->actingAs($this->user)
            ->post('/books/'.$this->book->id.'/reviews', ['rating' => 3]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reviews', 2);
    }

    // ---------- 編集画面 ----------

    public function test_author_can_view_edit_screen_with_current_values(): void
    {
        $review = Review::factory()->for($this->user)->for($this->book)->create([
            'rating' => 3,
            'comment' => '編集前のコメント',
        ]);

        $this->actingAs($this->user)
            ->get('/reviews/'.$review->id.'/edit')
            ->assertOk()
            ->assertSee($this->book->title)
            ->assertSee('編集前のコメント')
            ->assertSee('value="3" class="sr-only peer" checked', false);
    }

    public function test_other_user_cannot_view_edit_screen(): void
    {
        $review = Review::factory()->for($this->book)->create();

        $this->actingAs($this->user)->get('/reviews/'.$review->id.'/edit')->assertForbidden();
    }

    public function test_guest_cannot_view_edit_screen(): void
    {
        $review = Review::factory()->for($this->book)->create();

        $this->get('/reviews/'.$review->id.'/edit')->assertRedirect('/login');
    }

    // ---------- 更新 ----------

    public function test_author_can_update_review(): void
    {
        $review = Review::factory()->for($this->user)->for($this->book)->create(['rating' => 3]);

        $response = $this->actingAs($this->user)->put('/reviews/'.$review->id, [
            'rating' => 5,
            'comment' => '更新後のコメント',
        ]);

        $response->assertRedirect('/books/'.$this->book->id);
        $response->assertSessionHas('success', 'レビューを更新しました。');
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'rating' => 5,
            'comment' => '更新後のコメント',
        ]);
    }

    public function test_update_fails_with_invalid_input(): void
    {
        $review = Review::factory()->for($this->user)->for($this->book)->create(['rating' => 3]);

        $response = $this->actingAs($this->user)
            ->from('/reviews/'.$review->id.'/edit')
            ->put('/reviews/'.$review->id, ['rating' => 6, 'comment' => str_repeat('あ', 1001)]);

        $response->assertRedirect('/reviews/'.$review->id.'/edit');
        $response->assertSessionHasErrors([
            'rating' => '評価は1〜5の整数で選択してください。',
            'comment' => 'コメントは1000文字以内で入力してください。',
        ]);
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 3]);
    }

    public function test_other_user_cannot_update_review(): void
    {
        $review = Review::factory()->for($this->book)->create(['rating' => 3]);

        $this->actingAs($this->user)
            ->put('/reviews/'.$review->id, ['rating' => 5])
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 3]);
    }

    public function test_guest_cannot_update_review(): void
    {
        $review = Review::factory()->for($this->book)->create();

        $this->put('/reviews/'.$review->id, ['rating' => 5])->assertRedirect('/login');
    }

    // ---------- 削除 ----------

    public function test_author_can_delete_review_and_its_likes(): void
    {
        $review = Review::factory()->for($this->user)->for($this->book)->create();
        $review->likedByUsers()->attach(User::factory()->count(2)->create());

        $response = $this->actingAs($this->user)->delete('/reviews/'.$review->id);

        $response->assertRedirect('/books/'.$this->book->id);
        $response->assertSessionHas('success', 'レビューを削除しました。');
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('review_likes', 0);
        // 書籍は残る
        $this->assertDatabaseHas('books', ['id' => $this->book->id]);
    }

    public function test_other_user_cannot_delete_review(): void
    {
        $review = Review::factory()->for($this->book)->create();

        $this->actingAs($this->user)->delete('/reviews/'.$review->id)->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_guest_cannot_delete_review(): void
    {
        $review = Review::factory()->for($this->book)->create();

        $this->delete('/reviews/'.$review->id)->assertRedirect('/login');

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    // ---------- 書籍詳細の編集・削除ボタン ----------

    public function test_edit_and_delete_buttons_are_shown_only_to_author(): void
    {
        $review = Review::factory()->for($this->user)->for($this->book)->create();
        $editUrl = route('reviews.edit', $review);

        $this->actingAs($this->user)->get('/books/'.$this->book->id)->assertSee($editUrl);
        $this->actingAs(User::factory()->create())->get('/books/'.$this->book->id)->assertDontSee($editUrl);
    }
}
