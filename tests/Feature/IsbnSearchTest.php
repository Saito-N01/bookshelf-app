<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    private const ISBN = '9784873115658';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // 実際の Google Books API には接続しない（モックしていないURLへの通信はエラーにする）
        Http::preventStrayRequests();
    }

    /**
     * Google Books API のレスポンス（書籍が1件見つかった場合）を作る。
     *
     * @param  array<string, mixed>  $volumeInfo  volumeInfo の上書き
     * @return array<string, mixed>
     */
    private function googleBooksResponse(array $volumeInfo = []): array
    {
        return [
            'totalItems' => 1,
            'items' => [
                [
                    'volumeInfo' => array_merge([
                        'title' => 'リーダブルコード',
                        'authors' => ['Dustin Boswell'],
                        'publishedDate' => '2012-06-23',
                        'description' => 'より良いコードを書くための本です。',
                        'imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id=abc'],
                    ], $volumeInfo),
                ],
            ],
        ];
    }

    public function test_returns_book_information_as_flat_json(): void
    {
        Http::fake(['*' => Http::response($this->googleBooksResponse())]);

        $response = $this->actingAs($this->user)->getJson('/books/isbn/'.self::ISBN);

        $response->assertOk()->assertExactJson([
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
            'published_date' => '2012-06-23',
            'description' => 'より良いコードを書くための本です。',
            'image_url' => 'http://books.google.com/books/content?id=abc',
        ]);
    }

    public function test_requests_google_books_api_with_isbn_query(): void
    {
        config(['services.google_books.url' => 'https://books.example.test/volumes']);
        Http::fake(['*' => Http::response($this->googleBooksResponse())]);

        $this->actingAs($this->user)->getJson('/books/isbn/'.self::ISBN)->assertOk();

        // 接続先は config（.env）の値が使われ、ISBN は q=isbn:... の形式で送られる
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://books.example.test/volumes')
            && $request['q'] === 'isbn:'.self::ISBN);
    }

    public function test_multiple_authors_are_joined(): void
    {
        Http::fake(['*' => Http::response($this->googleBooksResponse([
            'authors' => ['Dustin Boswell', 'Trevor Foucher'],
        ]))]);

        $this->actingAs($this->user)->getJson('/books/isbn/'.self::ISBN)
            ->assertOk()
            ->assertJsonPath('author', 'Dustin Boswell、Trevor Foucher');
    }

    public function test_incomplete_published_date_becomes_null(): void
    {
        foreach (['2012', '2012-06', '2012-02-31'] as $incompleteDate) {
            Http::fake(['*' => Http::response($this->googleBooksResponse(['publishedDate' => $incompleteDate]))]);

            $this->actingAs($this->user)->getJson('/books/isbn/'.self::ISBN)
                ->assertOk()
                ->assertJsonPath('published_date', null);
        }
    }

    public function test_missing_fields_become_null(): void
    {
        Http::fake(['*' => Http::response([
            'totalItems' => 1,
            'items' => [['volumeInfo' => ['title' => 'タイトルだけの書籍']]],
        ])]);

        $this->actingAs($this->user)->getJson('/books/isbn/'.self::ISBN)
            ->assertOk()
            ->assertExactJson([
                'title' => 'タイトルだけの書籍',
                'author' => null,
                'published_date' => null,
                'description' => null,
                'image_url' => null,
            ]);
    }

    public function test_returns_404_when_book_is_not_found(): void
    {
        Http::fake(['*' => Http::response(['kind' => 'books#volumes', 'totalItems' => 0])]);

        $this->actingAs($this->user)->getJson('/books/isbn/'.self::ISBN)
            ->assertNotFound()
            ->assertExactJson(['error' => '該当する書籍が見つかりませんでした。']);
    }

    public function test_returns_503_when_google_books_api_returns_error(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Backend Error']], 500)]);

        $this->actingAs($this->user)->getJson('/books/isbn/'.self::ISBN)
            ->assertStatus(503)
            ->assertExactJson(['error' => '書籍情報を取得できませんでした。時間をおいて再度お試しください。']);
    }

    public function test_returns_503_when_google_books_api_is_unreachable(): void
    {
        Http::fake(['*' => fn () => throw new ConnectionException('Connection timed out')]);

        $this->actingAs($this->user)->getJson('/books/isbn/'.self::ISBN)
            ->assertStatus(503)
            ->assertExactJson(['error' => '書籍情報を取得できませんでした。時間をおいて再度お試しください。']);
    }

    public function test_returns_422_when_isbn_is_not_13_digits(): void
    {
        Http::fake();

        foreach (['978487311565', '97848731156580', '978487311565X', '978-4873115658'] as $invalidIsbn) {
            $this->actingAs($this->user)->getJson('/books/isbn/'.$invalidIsbn)
                ->assertStatus(422)
                ->assertExactJson(['error' => 'ISBNは13桁の数字で入力してください。']);
        }

        // 桁数が不正なときは、外部APIを呼ばない
        Http::assertNothingSent();
    }

    public function test_guest_cannot_search_by_isbn(): void
    {
        Http::fake();

        $this->get('/books/isbn/'.self::ISBN)->assertRedirect('/login');
        $this->getJson('/books/isbn/'.self::ISBN)->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_create_and_edit_screens_show_isbn_search_form(): void
    {
        $book = Book::factory()->for($this->user)->create();

        $this->actingAs($this->user)->get('/books/create')->assertOk()->assertSee('id="isbn-search"', false);
        $this->actingAs($this->user)->get('/books/'.$book->id.'/edit')->assertOk()->assertSee('id="isbn-search"', false);
    }
}
