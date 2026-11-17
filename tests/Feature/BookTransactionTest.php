<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * 書籍の登録・更新が「書籍の保存 + ジャンルの紐付け」を1つのトランザクションで行い、
 * 途中で失敗したときに中途半端なデータが残らないことを確認する。
 */
class BookTransactionTest extends TestCase
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
     * 書籍の保存（INSERT / UPDATE）の直後、ジャンルの紐付けの前に例外を起こす。
     * saved イベントは、書籍のSQLが実行された後に呼ばれる。
     */
    private function failAfterBookIsSaved(): void
    {
        Book::saved(function (): void {
            throw new RuntimeException('ジャンルの紐付けの前に失敗');
        });
    }

    /**
     * 書籍フォーム（Web）の正しい入力値。
     *
     * @return array<string, mixed>
     */
    private function webInput(): array
    {
        return [
            'title' => '新しいタイトル',
            'author' => '新しい著者',
            'isbn' => '9784000000001',
            'published_date' => '2020-04-01',
            'genres' => [$this->genre->id],
        ];
    }

    /**
     * 公開APIの正しいリクエストボディ。
     *
     * @return array<string, mixed>
     */
    private function apiPayload(): array
    {
        return [
            'title' => '新しいタイトル',
            'author' => '新しい著者',
            'isbn' => '9784000000001',
            'published_date' => '2020-04-01',
            'genre_ids' => [$this->genre->id],
        ];
    }

    /**
     * 更新前の書籍（元のジャンル付き）を用意する。
     */
    private function createBookWithOldGenre(): Book
    {
        $book = Book::factory()->for($this->user)->create(['title' => '元のタイトル']);
        $book->genres()->attach(Genre::factory()->create());

        return $book;
    }

    // ---------- Web ----------

    public function test_web_store_rolls_back_book_when_failure_occurs_before_genre_sync(): void
    {
        $this->failAfterBookIsSaved();

        $this->actingAs($this->user)->post('/books', $this->webInput())->assertStatus(500);

        // 書籍の INSERT は実行されたが、トランザクションごと取り消されている
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_genre', 0);
    }

    public function test_web_update_rolls_back_book_when_failure_occurs_before_genre_sync(): void
    {
        $book = $this->createBookWithOldGenre();
        $oldGenreId = $book->genres()->first()->id;
        $this->failAfterBookIsSaved();

        $this->actingAs($this->user)->put('/books/'.$book->id, $this->webInput())->assertStatus(500);

        // タイトルもジャンルも、更新前のまま
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元のタイトル']);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $oldGenreId]);
        $this->assertDatabaseCount('book_genre', 1);
    }

    // ---------- 公開API ----------

    public function test_api_store_rolls_back_book_when_failure_occurs_before_genre_sync(): void
    {
        Sanctum::actingAs($this->user);
        $this->failAfterBookIsSaved();

        $this->postJson('/api/v1/books', $this->apiPayload())->assertStatus(500);

        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_genre', 0);
    }

    public function test_api_update_rolls_back_book_when_failure_occurs_before_genre_sync(): void
    {
        Sanctum::actingAs($this->user);
        $book = $this->createBookWithOldGenre();
        $oldGenreId = $book->genres()->first()->id;
        $this->failAfterBookIsSaved();

        $this->putJson('/api/v1/books/'.$book->id, $this->apiPayload())->assertStatus(500);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元のタイトル']);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $oldGenreId]);
        $this->assertDatabaseCount('book_genre', 1);
    }

    // ---------- 正常系 ----------

    public function test_store_and_update_still_save_book_and_genres_when_no_failure(): void
    {
        $this->actingAs($this->user)->post('/books', $this->webInput())->assertSessionHasNoErrors();

        $book = Book::firstOrFail();
        $this->assertSame([$this->genre->id], $book->genres()->pluck('genres.id')->all());

        $newGenre = Genre::factory()->create();
        $this->actingAs($this->user)
            ->put('/books/'.$book->id, [...$this->webInput(), 'title' => '更新後のタイトル', 'genres' => [$newGenre->id]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新後のタイトル']);
        $this->assertSame([$newGenre->id], $book->genres()->pluck('genres.id')->all());
    }
}
