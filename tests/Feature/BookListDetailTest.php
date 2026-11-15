<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookListDetailTest extends TestCase
{
    use RefreshDatabase;

    // ---------- 書籍一覧 ----------

    public function test_guest_can_view_book_list_at_root_and_books(): void
    {
        $book = Book::factory()->create(['title' => 'テスト用の書籍']);

        $this->get('/')->assertOk()->assertSee('テスト用の書籍');
        $this->get('/books')->assertOk()->assertSee('テスト用の書籍');
        $this->assertSame(url('/books'), route('books.index'));
        $this->assertSame(url('/books/'.$book->id), route('books.show', $book));
    }

    public function test_book_list_is_paginated_by_ten(): void
    {
        Book::factory()->count(11)->create();

        $this->get('/books')->assertViewHas(
            'books',
            fn ($books) => $books->count() === 10 && $books->total() === 11
        );
        $this->get('/books?page=2')->assertViewHas('books', fn ($books) => $books->count() === 1);
    }

    public function test_book_list_is_ordered_by_newest_first(): void
    {
        Book::factory()->create(['title' => '古い書籍', 'created_at' => now()->subDays(2)]);
        Book::factory()->create(['title' => '新しい書籍', 'created_at' => now()]);
        Book::factory()->create(['title' => '中間の書籍', 'created_at' => now()->subDay()]);

        $this->get('/books')->assertSeeInOrder(['新しい書籍', '中間の書籍', '古い書籍']);
    }

    public function test_book_list_shows_genres_and_average_rating(): void
    {
        $book = Book::factory()->create();
        $book->genres()->attach(Genre::factory()->create(['name' => 'テストジャンル']));
        Review::factory()->for($book)->create(['rating' => 3]);
        Review::factory()->for($book)->create(['rating' => 4]);

        $response = $this->get('/books');

        $response->assertSee('テストジャンル')->assertSee('(3.5)');
        $response->assertViewHas('books', fn ($books) => $books->first()->relationLoaded('genres'));
    }

    public function test_book_list_shows_success_flash_message(): void
    {
        $this->withSession(['success' => '書籍を削除しました。'])
            ->get('/books')
            ->assertSee('書籍を削除しました。');
    }

    // ---------- 書籍詳細 ----------

    public function test_guest_can_view_book_detail(): void
    {
        $book = Book::factory()->create([
            'title' => '詳細テストの書籍',
            'author' => '詳細テストの著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-04-01',
        ]);
        $book->genres()->attach(Genre::factory()->create(['name' => '詳細ジャンル']));
        $reviewer = User::factory()->create(['name' => 'レビュー太郎']);
        $review = Review::factory()->for($book)->for($reviewer)->create(['comment' => 'とても良い本でした。']);
        $review->likedByUsers()->attach(User::factory()->count(2)->create());

        $response = $this->get('/books/'.$book->id);

        $response->assertOk()
            ->assertSee('詳細テストの書籍')
            ->assertSee('詳細テストの著者')
            ->assertSee('9781234567897')
            ->assertSee('2020/04/01')
            ->assertDontSee('00:00:00')
            ->assertSee('詳細ジャンル')
            ->assertSee('レビュー太郎')
            ->assertSee('とても良い本でした。')
            ->assertSee('いいね (2)');
    }

    public function test_book_detail_shows_reviews_newest_first(): void
    {
        $book = Book::factory()->create();
        Review::factory()->for($book)->create(['comment' => '古いレビュー', 'created_at' => now()->subDay()]);
        Review::factory()->for($book)->create(['comment' => '新しいレビュー', 'created_at' => now()]);

        $this->get('/books/'.$book->id)->assertSeeInOrder(['新しいレビュー', '古いレビュー']);
    }

    public function test_book_detail_eager_loads_relations(): void
    {
        $book = Book::factory()->create();
        Review::factory()->for($book)->create();

        $this->get('/books/'.$book->id)->assertViewHas('book', function (Book $book) {
            $review = $book->reviews->first();

            return $book->relationLoaded('genres')
                && $review->relationLoaded('user')
                && $review->relationLoaded('likedByUsers');
        });
    }

    public function test_authenticated_user_can_view_book_detail(): void
    {
        $book = Book::factory()->create();
        Review::factory()->for($book)->create();

        $this->actingAs(User::factory()->create())
            ->get('/books/'.$book->id)
            ->assertOk();
    }

    public function test_book_detail_returns_404_for_nonexistent_id(): void
    {
        $this->get('/books/999')->assertNotFound();
    }

    public function test_book_detail_shows_success_flash_message(): void
    {
        $book = Book::factory()->create();

        $this->withSession(['success' => '書籍を登録しました。'])
            ->get('/books/'.$book->id)
            ->assertSee('書籍を登録しました。');
    }

    // ---------- 認証必須ページとログイン後の遷移 ----------

    public function test_guest_is_redirected_to_login_and_back_to_intended_url_after_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->get('/books/create')->assertRedirect('/login');

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect('/books/create');
    }
}
