<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Review $review;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        // 他のユーザーが投稿したレビュー
        $this->review = Review::factory()->create();
    }

    // ---------- トグル ----------

    public function test_like_is_toggled_by_add_remove_and_add_again(): void
    {
        $like = ['user_id' => $this->user->id, 'review_id' => $this->review->id];
        $url = '/reviews/'.$this->review->id.'/like';

        // 1回目：追加
        $this->actingAs($this->user)->post($url);
        $this->assertDatabaseHas('review_likes', $like);

        // 2回目：解除
        $this->actingAs($this->user)->post($url);
        $this->assertDatabaseMissing('review_likes', $like);

        // 3回目：再追加
        $this->actingAs($this->user)->post($url);
        $this->assertDatabaseHas('review_likes', $like);
        $this->assertDatabaseCount('review_likes', 1);
    }

    public function test_toggle_redirects_back_without_flash_message(): void
    {
        $bookUrl = '/books/'.$this->review->book_id;

        $response = $this->actingAs($this->user)
            ->from($bookUrl)
            ->post('/reviews/'.$this->review->id.'/like');

        $response->assertRedirect($bookUrl);
        $response->assertSessionMissing('success');
    }

    public function test_toggle_does_not_affect_other_users_likes(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->likedReviews()->attach($this->review);

        // 自分が追加 → 解除しても、他のユーザーのいいねは残る
        $this->actingAs($this->user)->post('/reviews/'.$this->review->id.'/like');
        $this->actingAs($this->user)->post('/reviews/'.$this->review->id.'/like');

        $this->assertDatabaseHas('review_likes', ['user_id' => $otherUser->id, 'review_id' => $this->review->id]);
        $this->assertDatabaseMissing('review_likes', ['user_id' => $this->user->id, 'review_id' => $this->review->id]);
    }

    public function test_user_cannot_like_own_review(): void
    {
        $ownReview = Review::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->post('/reviews/'.$ownReview->id.'/like')
            ->assertForbidden();

        $this->assertDatabaseCount('review_likes', 0);
    }

    public function test_guest_cannot_like_review(): void
    {
        $this->post('/reviews/'.$this->review->id.'/like')->assertRedirect('/login');

        $this->assertDatabaseCount('review_likes', 0);
    }

    public function test_toggle_returns_404_for_nonexistent_review(): void
    {
        $this->actingAs($this->user)->post('/reviews/999/like')->assertNotFound();
    }

    // ---------- 書籍詳細の表示 ----------

    public function test_book_detail_shows_like_count(): void
    {
        $this->review->likedByUsers()->attach(User::factory()->count(2)->create());

        $this->actingAs($this->user)
            ->get('/books/'.$this->review->book_id)
            ->assertSee('いいね (2)');
    }

    public function test_book_detail_shows_liked_state_for_user_who_liked(): void
    {
        $this->user->likedReviews()->attach($this->review);

        $this->actingAs($this->user)
            ->get('/books/'.$this->review->book_id)
            ->assertSee('いいね済み (1)');
    }

    public function test_like_button_is_hidden_on_own_review(): void
    {
        $ownReview = Review::factory()->for($this->user)->create();
        $ownReview->likedByUsers()->attach(User::factory()->create());
        $likeUrl = route('reviews.like', $ownReview);

        // 投稿者本人：いいねボタン（フォーム）は表示されず、件数だけ表示される
        $this->actingAs($this->user)
            ->get('/books/'.$ownReview->book_id)
            ->assertDontSee($likeUrl)
            ->assertSee('いいね (1)');

        // 他のユーザー：いいねボタンが表示される
        $this->actingAs(User::factory()->create())
            ->get('/books/'.$ownReview->book_id)
            ->assertSee($likeUrl);
    }
}
