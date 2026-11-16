<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 登録・更新APIの正しいリクエストボディ（各テストで一部だけ上書きして使う）
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'APIテストの書籍',
            'author' => 'APIテストの著者',
            'isbn' => '9784000000001',
            'published_date' => '2020-04-01',
            'description' => 'APIテスト用の説明です。',
            'image_url' => 'https://example.com/cover.jpg',
            'genre_ids' => [Genre::factory()->create()->id],
        ], $overrides);
    }

    // ---------- AP01: 書籍一覧 ----------

    public function test_index_returns_books_with_data_links_and_meta(): void
    {
        $book = Book::factory()->create();
        $book->genres()->attach(Genre::factory()->create(['name' => '小説']));

        $response = $this->getJson('/api/v1/books');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => [
                    'id', 'title', 'author', 'isbn', 'published_date', 'description', 'image_url',
                    'user_id', 'genres' => ['*' => ['id', 'name']], 'average_rating', 'reviews_count',
                    'created_at', 'updated_at',
                ]],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.id', $book->id)
            ->assertJsonPath('data.0.genres.0.name', '小説')
            ->assertJsonMissingPath('data.0.reviews');
    }

    public function test_index_returns_average_rating_and_reviews_count(): void
    {
        $reviewed = Book::factory()->create(['created_at' => now()]);
        foreach ([5, 4, 4] as $rating) {
            Review::factory()->for($reviewed)->create(['rating' => $rating]);
        }
        $notReviewed = Book::factory()->create(['created_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/books');

        // 平均 4.333… は小数第1位に丸める。レビューがない書籍は null
        $response->assertJsonPath('data.0.id', $reviewed->id)
            ->assertJsonPath('data.0.average_rating', 4.3)
            ->assertJsonPath('data.0.reviews_count', 3)
            ->assertJsonPath('data.1.id', $notReviewed->id)
            ->assertJsonPath('data.1.average_rating', null)
            ->assertJsonPath('data.1.reviews_count', 0);
    }

    public function test_index_formats_dates(): void
    {
        Book::factory()->create([
            'published_date' => '1905-01-01',
            'created_at' => '2026-10-26 09:00:00',
        ]);

        $this->getJson('/api/v1/books')
            ->assertJsonPath('data.0.published_date', '1905-01-01')
            ->assertJsonPath('data.0.created_at', '2026-10-26T09:00:00+09:00');
    }

    public function test_index_paginates_twenty_per_page_by_default(): void
    {
        Book::factory()->count(21)->create();

        $this->getJson('/api/v1/books')
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/v1/books?page=2')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_index_accepts_per_page_and_keeps_query_string_in_links(): void
    {
        Book::factory()->count(6)->create();

        $response = $this->getJson('/api/v1/books?per_page=5');

        $response->assertJsonCount(5, 'data')->assertJsonPath('meta.per_page', 5);
        $this->assertStringContainsString('per_page=5', $response->json('links.next'));
        $this->assertStringContainsString('page=2', $response->json('links.next'));
    }

    public function test_index_is_ordered_by_newest_first(): void
    {
        $old = Book::factory()->create(['created_at' => now()->subDays(2)]);
        $new = Book::factory()->create(['created_at' => now()]);
        $middle = Book::factory()->create(['created_at' => now()->subDay()]);

        $this->assertSame(
            [$new->id, $middle->id, $old->id],
            $this->getJson('/api/v1/books')->json('data.*.id')
        );
    }

    public function test_index_filters_by_keyword_in_title_or_author(): void
    {
        $byTitle = Book::factory()->create(['title' => '吾輩は猫である', 'author' => '著者A']);
        $byAuthor = Book::factory()->create(['title' => '坊っちゃん', 'author' => '夏目漱石']);
        Book::factory()->create(['title' => '火花', 'author' => '又吉直樹']);

        $this->assertEqualsCanonicalizing(
            [$byTitle->id],
            $this->getJson('/api/v1/books?keyword='.urlencode('猫'))->json('data.*.id')
        );
        $this->assertEqualsCanonicalizing(
            [$byAuthor->id],
            $this->getJson('/api/v1/books?keyword='.urlencode('夏目'))->json('data.*.id')
        );
        $this->getJson('/api/v1/books?keyword='.urlencode('該当なし'))->assertJsonCount(0, 'data');
    }

    public function test_index_treats_percent_in_keyword_as_literal(): void
    {
        Book::factory()->create(['title' => '普通の書籍']);

        $this->getJson('/api/v1/books?keyword='.urlencode('%'))->assertJsonCount(0, 'data');
    }

    public function test_index_filters_by_genre_id(): void
    {
        $genre = Genre::factory()->create();
        $inGenre = Book::factory()->create();
        $inGenre->genres()->attach($genre);
        $otherBook = Book::factory()->create();
        $otherBook->genres()->attach(Genre::factory()->create());

        $this->assertSame(
            [$inGenre->id],
            $this->getJson('/api/v1/books?genre_id='.$genre->id)->json('data.*.id')
        );
    }

    public function test_index_filters_by_keyword_and_genre_id_together(): void
    {
        $genre = Genre::factory()->create();
        $match = Book::factory()->create(['title' => 'Laravel入門', 'author' => '著者A']);
        $match->genres()->attach($genre);
        $sameGenreOnly = Book::factory()->create(['title' => 'PHP入門', 'author' => '著者B']);
        $sameGenreOnly->genres()->attach($genre);
        // キーワードは著者名に一致するが、ジャンルが違う書籍（orWhere のグループ化の確認）
        Book::factory()->create(['title' => '別の本', 'author' => 'Laravel太郎']);

        $this->assertSame(
            [$match->id],
            $this->getJson('/api/v1/books?keyword=Laravel&genre_id='.$genre->id)->json('data.*.id')
        );
    }

    public function test_index_returns_422_for_invalid_per_page(): void
    {
        foreach (['0', '101', 'abc'] as $perPage) {
            $this->getJson('/api/v1/books?per_page='.$perPage)
                ->assertStatus(422)
                ->assertJsonPath('message', '1ページあたりの件数は1〜100の整数で指定してください。')
                ->assertJsonPath('errors.per_page.0', '1ページあたりの件数は1〜100の整数で指定してください。');
        }
    }

    public function test_index_returns_422_for_other_invalid_parameters(): void
    {
        $this->getJson('/api/v1/books?page=0')
            ->assertStatus(422)
            ->assertJsonPath('errors.page.0', 'ページ番号は1以上の整数で指定してください。');

        $this->getJson('/api/v1/books?genre_id=999')
            ->assertStatus(422)
            ->assertJsonPath('errors.genre_id.0', '選択されたジャンルが存在しません。');

        $this->getJson('/api/v1/books?genre_id=abc')
            ->assertStatus(422)
            ->assertJsonPath('errors.genre_id.0', 'ジャンルの値が不正です。');

        $this->getJson('/api/v1/books?keyword='.str_repeat('a', 256))
            ->assertStatus(422)
            ->assertJsonPath('errors.keyword.0', 'キーワードは255文字以内で入力してください。');
    }

    // ---------- AP02: 書籍詳細 ----------

    public function test_show_returns_book_with_genres_and_reviews(): void
    {
        $book = Book::factory()->create(['title' => '詳細APIの書籍']);
        $book->genres()->attach(Genre::factory()->create(['name' => '技術書']));
        $reviewer = User::factory()->create(['name' => '山田太郎']);
        $review = Review::factory()->for($book)->for($reviewer)->create([
            'rating' => 5,
            'comment' => '素晴らしい本でした！',
            'created_at' => '2026-10-26 09:00:00',
        ]);

        $response = $this->getJson('/api/v1/books/'.$book->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', '詳細APIの書籍')
            ->assertJsonPath('data.user_id', $book->user_id)
            ->assertJsonPath('data.genres.0.name', '技術書')
            // 平均がちょうど 5.0 のとき、JSONでは小数点以下が省かれて 5 と出力される
            ->assertJsonPath('data.average_rating', 5)
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.reviews', [[
                'id' => $review->id,
                'user_name' => '山田太郎',
                'rating' => 5,
                'comment' => '素晴らしい本でした！',
                'created_at' => '2026-10-26T09:00:00+09:00',
            ]]);
    }

    public function test_show_returns_404_json_for_nonexistent_id(): void
    {
        $this->getJson('/api/v1/books/999')
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりません。']);
    }

    public function test_api_returns_json_errors_without_accept_header(): void
    {
        // Accept: application/json を付けないクライアントにも、JSONでエラーを返す
        $this->get('/api/v1/books/999')
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりません。']);

        $this->post('/api/v1/books', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.title.0', 'タイトルは必須です。');
    }

    // ---------- AP03: 書籍登録 ----------

    public function test_store_creates_book_and_returns_201(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $response = $this->postJson('/api/v1/books', $this->validPayload([
            'genre_ids' => $genres->pluck('id')->all(),
            'user_id' => $user->id,
        ]));

        $book = Book::first();
        $response->assertCreated()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', 'APIテストの書籍')
            ->assertJsonPath('data.isbn', '9784000000001')
            ->assertJsonPath('data.published_date', '2020-04-01')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.reviews_count', 0)
            ->assertJsonCount(2, 'data.genres')
            ->assertJsonMissingPath('data.reviews');
        $this->assertDatabaseHas('books', [
            'user_id' => $user->id,
            'title' => 'APIテストの書籍',
            'author' => 'APIテストの著者',
            'isbn' => '9784000000001',
        ]);
        $this->assertDatabaseCount('book_genre', 2);
    }

    public function test_store_returns_422_with_japanese_messages_when_required_fields_are_missing(): void
    {
        $response = $this->postJson('/api/v1/books', []);

        $response->assertStatus(422)
            ->assertJsonPath('errors.title.0', 'タイトルは必須です。')
            ->assertJsonPath('errors.author.0', '著者名は必須です。')
            ->assertJsonPath('errors.isbn.0', 'ISBNは必須です。')
            ->assertJsonPath('errors.published_date.0', '出版日は必須です。')
            ->assertJsonPath('errors.genre_ids.0', 'ジャンルを1つ以上選択してください。')
            ->assertJsonPath('errors.user_id.0', '登録者IDは必須です。');
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_returns_422_for_invalid_values(): void
    {
        Book::factory()->create(['isbn' => '9784000000009']);
        $user = User::factory()->create();

        $this->postJson('/api/v1/books', $this->validPayload(['user_id' => $user->id, 'isbn' => '12345']))
            ->assertStatus(422)
            ->assertJsonPath('errors.isbn.0', 'ISBNは13桁で入力してください。');

        $this->postJson('/api/v1/books', $this->validPayload(['user_id' => $user->id, 'isbn' => '9784000000009']))
            ->assertStatus(422)
            ->assertJsonPath('errors.isbn.0', 'そのISBNは既に使用されています。');

        $this->postJson('/api/v1/books', $this->validPayload(['user_id' => $user->id, 'image_url' => 'not-a-url']))
            ->assertStatus(422)
            ->assertJsonPath('errors.image_url.0', '画像URLは有効なURL形式で入力してください。');

        $this->postJson('/api/v1/books', $this->validPayload(['user_id' => $user->id, 'genre_ids' => [999]]))
            ->assertStatus(422)
            ->assertJsonPath('errors', ['genre_ids.0' => ['選択されたジャンルが存在しません。']]);

        $this->postJson('/api/v1/books', $this->validPayload(['user_id' => 999]))
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', '指定された登録者が存在しません。');

        $this->postJson('/api/v1/books', $this->validPayload(['user_id' => 'abc']))
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', '登録者IDの値が不正です。');

        $this->assertDatabaseCount('books', 1);
    }

    public function test_store_limits_image_url_to_255_characters_and_does_not_limit_description(): void
    {
        $user = User::factory()->create();

        // 20文字 + 236文字 = 256文字
        $this->postJson('/api/v1/books', $this->validPayload([
            'user_id' => $user->id,
            'image_url' => 'https://example.com/'.str_repeat('a', 236),
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.image_url.0', '画像URLは255文字以内で入力してください。');

        $this->postJson('/api/v1/books', $this->validPayload([
            'user_id' => $user->id,
            'description' => str_repeat('あ', 1001),
        ]))->assertCreated();
    }

    // ---------- AP04: 書籍更新 ----------

    public function test_update_changes_book_and_syncs_genres(): void
    {
        $book = Book::factory()->create(['isbn' => '9784000000001']);
        $oldGenre = Genre::factory()->create();
        $book->genres()->attach($oldGenre);
        $newGenre = Genre::factory()->create(['name' => '新しいジャンル']);

        // ISBNは変更しない（自身のISBNは重複エラーにならない）
        $response = $this->putJson('/api/v1/books/'.$book->id, $this->validPayload([
            'title' => '更新後のタイトル',
            'genre_ids' => [$newGenre->id],
        ]));

        $response->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', '更新後のタイトル')
            ->assertJsonPath('data.isbn', '9784000000001')
            ->assertJsonPath('data.genres', [['id' => $newGenre->id, 'name' => '新しいジャンル']])
            ->assertJsonMissingPath('data.reviews');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新後のタイトル']);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $oldGenre->id]);
    }

    public function test_update_does_not_change_user_id(): void
    {
        $book = Book::factory()->create();
        $anotherUser = User::factory()->create();

        $this->putJson('/api/v1/books/'.$book->id, $this->validPayload(['user_id' => $anotherUser->id]))
            ->assertOk()
            ->assertJsonPath('data.user_id', $book->user_id);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'user_id' => $book->user_id]);
    }

    public function test_update_returns_404_json_for_nonexistent_id(): void
    {
        $this->putJson('/api/v1/books/999', $this->validPayload())
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりません。']);
    }

    public function test_update_returns_422_for_invalid_values(): void
    {
        Book::factory()->create(['isbn' => '9784000000009']);
        $book = Book::factory()->create(['title' => '元のタイトル']);

        $this->putJson('/api/v1/books/'.$book->id, $this->validPayload(['isbn' => '9784000000009']))
            ->assertStatus(422)
            ->assertJsonPath('errors.isbn.0', 'そのISBNは既に使用されています。');

        $this->putJson('/api/v1/books/'.$book->id, $this->validPayload(['title' => '']))
            ->assertStatus(422)
            ->assertJsonPath('errors.title.0', 'タイトルは必須です。');

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元のタイトル']);
    }

    // ---------- AP05: 書籍削除 ----------

    public function test_destroy_deletes_book_and_related_data_and_returns_204(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre);
        $review = Review::factory()->for($book)->create();
        $user = User::factory()->create();
        $review->likedByUsers()->attach($user);
        $user->favoriteBooks()->attach($book);

        $response = $this->deleteJson('/api/v1/books/'.$book->id);

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_genre', 0);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('review_likes', 0);
        $this->assertDatabaseCount('favorites', 0);
        // ジャンル自体は残る
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    public function test_destroy_returns_404_json_for_nonexistent_id(): void
    {
        $this->deleteJson('/api/v1/books/999')
            ->assertNotFound()
            ->assertExactJson(['message' => '書籍が見つかりません。']);
    }

    // ---------- ルート名 ----------

    public function test_api_route_names_do_not_override_web_route_names(): void
    {
        $this->assertSame(url('/books'), route('books.index'));
        $this->assertSame(url('/api/v1/books'), route('api.v1.books.index'));
    }
}
