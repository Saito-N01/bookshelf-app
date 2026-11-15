<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
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

    // ---------- トグル ----------

    public function test_favorite_is_toggled_by_add_remove_and_add_again(): void
    {
        $favorite = ['user_id' => $this->user->id, 'book_id' => $this->book->id];
        $url = '/books/'.$this->book->id.'/favorites';

        // 1回目：追加
        $this->actingAs($this->user)->post($url);
        $this->assertDatabaseHas('favorites', $favorite);

        // 2回目：解除
        $this->actingAs($this->user)->post($url);
        $this->assertDatabaseMissing('favorites', $favorite);

        // 3回目：再追加
        $this->actingAs($this->user)->post($url);
        $this->assertDatabaseHas('favorites', $favorite);
        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_toggle_redirects_back_without_flash_message(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/books/'.$this->book->id)
            ->post('/books/'.$this->book->id.'/favorites');

        $response->assertRedirect('/books/'.$this->book->id);
        $response->assertSessionMissing('success');
    }

    public function test_toggle_does_not_affect_other_users_favorites(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->favoriteBooks()->attach($this->book);

        // 自分が追加 → 解除しても、他のユーザーのお気に入りは残る
        $this->actingAs($this->user)->post('/books/'.$this->book->id.'/favorites');
        $this->actingAs($this->user)->post('/books/'.$this->book->id.'/favorites');

        $this->assertDatabaseHas('favorites', ['user_id' => $otherUser->id, 'book_id' => $this->book->id]);
        $this->assertDatabaseMissing('favorites', ['user_id' => $this->user->id, 'book_id' => $this->book->id]);
    }

    public function test_guest_cannot_toggle_favorite(): void
    {
        $this->post('/books/'.$this->book->id.'/favorites')->assertRedirect('/login');

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_toggle_returns_404_for_nonexistent_book(): void
    {
        $this->actingAs($this->user)->post('/books/999/favorites')->assertNotFound();
    }

    // ---------- 書籍詳細のお気に入りボタン ----------

    public function test_book_detail_shows_favorite_state(): void
    {
        $this->actingAs($this->user)
            ->get('/books/'.$this->book->id)
            ->assertSee('お気に入りに追加')
            ->assertDontSee('お気に入りから削除');

        $this->user->favoriteBooks()->attach($this->book);

        $this->actingAs($this->user->fresh())
            ->get('/books/'.$this->book->id)
            ->assertSee('お気に入りから削除');
    }

    // ---------- お気に入り一覧 ----------

    public function test_guest_cannot_view_favorite_list(): void
    {
        $this->get('/favorites')->assertRedirect('/login');
    }

    public function test_favorite_list_shows_only_own_favorites(): void
    {
        $myBook = Book::factory()->create(['title' => '自分のお気に入り']);
        $othersBook = Book::factory()->create(['title' => '他人のお気に入り']);
        $notFavorite = Book::factory()->create(['title' => 'お気に入りでない書籍']);
        $this->user->favoriteBooks()->attach($myBook);
        User::factory()->create()->favoriteBooks()->attach($othersBook);

        $this->actingAs($this->user)
            ->get('/favorites')
            ->assertOk()
            ->assertSee('自分のお気に入り')
            ->assertDontSee('他人のお気に入り')
            ->assertDontSee($notFavorite->title);
    }

    public function test_favorite_list_is_paginated_by_ten(): void
    {
        $this->user->favoriteBooks()->attach(Book::factory()->count(11)->create());

        $this->actingAs($this->user)->get('/favorites')->assertViewHas(
            'books',
            fn ($books) => $books->count() === 10 && $books->total() === 11
        );
        $this->actingAs($this->user)
            ->get('/favorites?page=2')
            ->assertViewHas('books', fn ($books) => $books->count() === 1);
    }

    public function test_favorite_list_shows_message_when_empty(): void
    {
        $this->actingAs($this->user)
            ->get('/favorites')
            ->assertOk()
            ->assertSee('お気に入りに登録された書籍はありません。');
    }
}
