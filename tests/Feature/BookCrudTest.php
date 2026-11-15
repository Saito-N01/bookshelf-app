<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Genre $genre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->genre = Genre::factory()->create();
    }

    /**
     * 書籍フォームの正しい入力値（各テストで一部だけ上書きして使う）
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'title' => 'テストの書籍',
            'author' => 'テストの著者',
            'isbn' => '9784000000001',
            'published_date' => '2020-04-01',
            'description' => 'テスト用の説明です。',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => [$this->genre->id],
        ], $overrides);
    }

    // ---------- 登録画面 ----------

    public function test_guest_cannot_view_create_screen(): void
    {
        $this->get('/books/create')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_create_screen_with_genres(): void
    {
        $this->actingAs($this->user)
            ->get('/books/create')
            ->assertOk()
            ->assertSee($this->genre->name);
    }

    // ---------- 登録 ----------

    public function test_user_can_store_book_with_genres(): void
    {
        $otherGenre = Genre::factory()->create();

        $response = $this->actingAs($this->user)->post('/books', $this->validInput([
            'genres' => [$this->genre->id, $otherGenre->id],
        ]));

        $book = Book::first();
        $response->assertRedirect('/books/'.$book->id);
        $response->assertSessionHas('success', '書籍を登録しました。');
        $this->assertDatabaseHas('books', [
            'user_id' => $this->user->id,
            'title' => 'テストの書籍',
            'author' => 'テストの著者',
            'isbn' => '9784000000001',
            'description' => 'テスト用の説明です。',
            'image_url' => 'https://example.com/image.jpg',
        ]);
        $this->assertSame('2020-04-01', $book->published_date->toDateString());
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $this->genre->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $otherGenre->id]);
    }

    public function test_user_can_store_book_without_optional_fields(): void
    {
        $this->actingAs($this->user)->post('/books', $this->validInput([
            'description' => '',
            'image_url' => '',
        ]));

        $this->assertDatabaseHas('books', [
            'isbn' => '9784000000001',
            'description' => null,
            'image_url' => null,
        ]);
    }

    public function test_guest_cannot_store_book(): void
    {
        $this->post('/books', $this->validInput())->assertRedirect('/login');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_fails_when_required_fields_are_empty(): void
    {
        $response = $this->actingAs($this->user)->from('/books/create')->post('/books', [
            'title' => '',
            'author' => '',
            'isbn' => '',
            'published_date' => '',
        ]);

        $response->assertRedirect('/books/create');
        $response->assertSessionHasErrors([
            'title' => 'タイトルは必須です。',
            'author' => '著者名は必須です。',
            'isbn' => 'ISBNは必須です。',
            'published_date' => '出版日は必須です。',
            'genres' => 'ジャンルを1つ以上選択してください。',
        ]);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_fails_when_title_and_author_are_too_long(): void
    {
        $response = $this->actingAs($this->user)->post('/books', $this->validInput([
            'title' => str_repeat('あ', 256),
            'author' => str_repeat('あ', 256),
        ]));

        $response->assertSessionHasErrors([
            'title' => 'タイトルは255文字以内で入力してください。',
            'author' => '著者名は255文字以内で入力してください。',
        ]);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_fails_when_isbn_is_not_thirteen_digits(): void
    {
        foreach (['978400000000', '97840000000012', '978-4-00-000000', 'abcdefghijklm'] as $isbn) {
            $response = $this->actingAs($this->user)->post('/books', $this->validInput(['isbn' => $isbn]));

            $response->assertSessionHasErrors(['isbn' => 'ISBNは13桁で入力してください。']);
        }
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_fails_when_isbn_is_already_used(): void
    {
        Book::factory()->create(['isbn' => '9784000000001']);

        $response = $this->actingAs($this->user)->post('/books', $this->validInput());

        $response->assertSessionHasErrors(['isbn' => 'そのISBNは既に使用されています。']);
        $this->assertDatabaseCount('books', 1);
    }

    public function test_store_fails_when_published_date_is_not_a_date(): void
    {
        $response = $this->actingAs($this->user)->post('/books', $this->validInput([
            'published_date' => 'not-a-date',
        ]));

        $response->assertSessionHasErrors(['published_date' => '出版日は有効な日付形式で入力してください。']);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_fails_when_description_is_too_long(): void
    {
        $response = $this->actingAs($this->user)->post('/books', $this->validInput([
            'description' => str_repeat('あ', 1001),
        ]));

        $response->assertSessionHasErrors(['description' => '説明は1000文字以内で入力してください。']);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_fails_when_image_url_is_invalid(): void
    {
        $response = $this->actingAs($this->user)->post('/books', $this->validInput([
            'image_url' => 'not-a-url',
        ]));

        $response->assertSessionHasErrors(['image_url' => '画像URLは有効なURL形式で入力してください。']);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_fails_when_image_url_is_too_long(): void
    {
        $response = $this->actingAs($this->user)->post('/books', $this->validInput([
            'image_url' => 'https://example.com/'.str_repeat('a', 2030),
        ]));

        $response->assertSessionHasErrors(['image_url' => '画像URLは2048文字以内で入力してください。']);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_fails_when_no_genre_is_selected(): void
    {
        $response = $this->actingAs($this->user)->post('/books', $this->validInput(['genres' => []]));

        $response->assertSessionHasErrors(['genres' => 'ジャンルを1つ以上選択してください。']);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_fails_when_genre_does_not_exist(): void
    {
        $response = $this->actingAs($this->user)->post('/books', $this->validInput(['genres' => [999]]));

        $response->assertSessionHasErrors(['genres.0' => '選択されたジャンルが存在しません。']);
        $this->assertDatabaseCount('books', 0);
    }

    // ---------- 編集画面 ----------

    public function test_owner_can_view_edit_screen_with_current_values(): void
    {
        $book = Book::factory()->for($this->user)->create([
            'title' => '編集前の書籍',
            'published_date' => '2019-12-31',
        ]);

        $this->actingAs($this->user)
            ->get('/books/'.$book->id.'/edit')
            ->assertOk()
            ->assertSee('編集前の書籍')
            ->assertSee('value="2019-12-31"', false);
    }

    public function test_other_user_cannot_view_edit_screen(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)->get('/books/'.$book->id.'/edit')->assertForbidden();
    }

    public function test_guest_cannot_view_edit_screen(): void
    {
        $book = Book::factory()->create();

        $this->get('/books/'.$book->id.'/edit')->assertRedirect('/login');
    }

    // ---------- 更新 ----------

    public function test_owner_can_update_book_and_sync_genres(): void
    {
        $book = Book::factory()->for($this->user)->create(['isbn' => '9784000000001']);
        $oldGenre = Genre::factory()->create();
        $book->genres()->attach($oldGenre);

        // ISBNは変更しない（自身のISBNは重複エラーにならない）
        $response = $this->actingAs($this->user)->put('/books/'.$book->id, $this->validInput([
            'title' => '更新後の書籍',
        ]));

        $response->assertRedirect('/books/'.$book->id);
        $response->assertSessionHas('success', '書籍を更新しました。');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $this->user->id,
            'title' => '更新後の書籍',
            'isbn' => '9784000000001',
        ]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $this->genre->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $oldGenre->id]);
    }

    public function test_update_fails_when_isbn_is_used_by_another_book(): void
    {
        Book::factory()->create(['isbn' => '9784000000002']);
        $book = Book::factory()->for($this->user)->create(['isbn' => '9784000000001']);

        $response = $this->actingAs($this->user)
            ->from('/books/'.$book->id.'/edit')
            ->put('/books/'.$book->id, $this->validInput(['isbn' => '9784000000002']));

        $response->assertRedirect('/books/'.$book->id.'/edit');
        $response->assertSessionHasErrors(['isbn' => 'そのISBNは既に使用されています。']);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'isbn' => '9784000000001']);
    }

    public function test_update_fails_when_required_fields_are_empty(): void
    {
        $book = Book::factory()->for($this->user)->create(['title' => '元のタイトル']);

        $response = $this->actingAs($this->user)->put('/books/'.$book->id, $this->validInput([
            'title' => '',
            'genres' => [],
        ]));

        $response->assertSessionHasErrors([
            'title' => 'タイトルは必須です。',
            'genres' => 'ジャンルを1つ以上選択してください。',
        ]);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元のタイトル']);
    }

    public function test_other_user_cannot_update_book(): void
    {
        $book = Book::factory()->create(['title' => '元のタイトル']);

        $this->actingAs($this->user)
            ->put('/books/'.$book->id, $this->validInput())
            ->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元のタイトル']);
    }

    public function test_guest_cannot_update_book(): void
    {
        $book = Book::factory()->create();

        $this->put('/books/'.$book->id, $this->validInput())->assertRedirect('/login');
    }

    // ---------- 削除 ----------

    public function test_owner_can_delete_book_and_related_data(): void
    {
        $book = Book::factory()->for($this->user)->create();
        $book->genres()->attach($this->genre);
        $review = Review::factory()->for($book)->create();
        $review->likedByUsers()->attach($this->user);
        $this->user->favoriteBooks()->attach($book);

        $response = $this->actingAs($this->user)->delete('/books/'.$book->id);

        $response->assertRedirect('/books');
        $response->assertSessionHas('success', '書籍を削除しました。');
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_genre', 0);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('review_likes', 0);
        $this->assertDatabaseCount('favorites', 0);
        // ジャンル自体は残る
        $this->assertDatabaseHas('genres', ['id' => $this->genre->id]);
    }

    public function test_other_user_cannot_delete_book(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->user)->delete('/books/'.$book->id)->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_guest_cannot_delete_book(): void
    {
        $book = Book::factory()->create();

        $this->delete('/books/'.$book->id)->assertRedirect('/login');

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    // ---------- 書籍詳細の編集・削除ボタン ----------

    public function test_edit_and_delete_buttons_are_shown_only_to_owner(): void
    {
        $book = Book::factory()->for($this->user)->create();
        $editUrl = route('books.edit', $book);

        $this->actingAs($this->user)->get('/books/'.$book->id)->assertSee($editUrl);
        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)->assertDontSee($editUrl);
    }
}
