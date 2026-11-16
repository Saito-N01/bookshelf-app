<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class BookSearchSortTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一覧に表示された書籍のタイトルを、表示順の配列で取り出す。
     *
     * @return array<int, string>
     */
    private function titles(string $url): array
    {
        /** @var LengthAwarePaginator $books */
        $books = $this->get($url)->assertOk()->viewData('books');

        return $books->pluck('title')->all();
    }

    // ---------- キーワード検索 ----------

    public function test_keyword_matches_title_partially(): void
    {
        Book::factory()->create(['title' => 'Laravel入門', 'author' => '山田太郎']);
        Book::factory()->create(['title' => 'PHPの教科書', 'author' => '佐藤花子']);

        $this->assertSame(['Laravel入門'], $this->titles('/books?keyword=ravel'));
    }

    public function test_keyword_matches_author_partially(): void
    {
        Book::factory()->create(['title' => 'Laravel入門', 'author' => '山田太郎']);
        Book::factory()->create(['title' => 'PHPの教科書', 'author' => '佐藤花子']);

        $this->assertSame(['PHPの教科書'], $this->titles('/books?keyword='.urlencode('佐藤')));
    }

    public function test_keyword_with_space_is_searched_as_one_string(): void
    {
        Book::factory()->create(['title' => 'Clean Code', 'author' => 'A']);
        Book::factory()->create(['title' => 'Code Clean', 'author' => 'B']);

        $this->assertSame(['Clean Code'], $this->titles('/books?keyword='.urlencode('Clean Code')));
    }

    public function test_empty_keyword_shows_all_books(): void
    {
        Book::factory()->count(3)->create();

        $this->assertCount(3, $this->titles('/books?keyword=&genre=&sort='));
    }

    // ---------- ジャンル絞り込み ----------

    public function test_genre_filters_books(): void
    {
        $novel = Genre::factory()->create();
        $tech = Genre::factory()->create();
        Book::factory()->hasAttached($novel)->create(['title' => '小説の本']);
        Book::factory()->hasAttached($tech)->create(['title' => '技術の本']);

        $this->assertSame(['技術の本'], $this->titles('/books?genre='.$tech->id));
    }

    public function test_keyword_and_genre_are_combined_with_and(): void
    {
        $novel = Genre::factory()->create();
        $tech = Genre::factory()->create();
        Book::factory()->hasAttached($tech)->create(['title' => 'PHP入門', 'author' => 'A']);
        Book::factory()->hasAttached($novel)->create(['title' => 'PHP物語', 'author' => 'B']);
        // author が一致しても、ジャンルが違えば表示されない（orWhere が括弧でまとまっていること）
        Book::factory()->hasAttached($novel)->create(['title' => '別の小説', 'author' => 'PHP太郎']);
        Book::factory()->hasAttached($tech)->create(['title' => 'Java入門', 'author' => 'C']);

        $this->assertSame(['PHP入門'], $this->titles('/books?keyword=PHP&genre='.$tech->id));
    }

    public function test_no_match_shows_empty_list_message(): void
    {
        Book::factory()->create(['title' => 'Laravel入門']);

        $response = $this->get('/books?keyword='.urlencode('該当しない言葉'));

        $response->assertOk()
            ->assertSee('書籍が見つかりませんでした。')
            ->assertSee('検索結果: 0件')
            ->assertDontSee('Laravel入門');
    }

    public function test_unknown_genre_id_shows_empty_list(): void
    {
        Book::factory()->hasAttached(Genre::factory())->create();

        $this->assertSame([], $this->titles('/books?genre=9999'));
    }

    public function test_search_form_shows_genres_and_keeps_input(): void
    {
        $genre = Genre::factory()->create(['name' => 'テストジャンル']);

        $response = $this->get('/books?keyword=PHP&genre='.$genre->id.'&sort=title');

        $response->assertOk()
            ->assertViewHas('genres', fn ($genres) => $genres->pluck('id')->all() === [$genre->id])
            ->assertSee('value="PHP"', false)
            ->assertSee('<option value="'.$genre->id.'" selected>', false)
            ->assertSee('<option value="title" selected>', false);
    }

    // ---------- ソート ----------

    /**
     * 登録日の異なる3冊を用意する（タイトルの昇順と登録順は、あえて別の並びにしている）。
     */
    private function createBooksForSort(): void
    {
        Book::factory()->create(['title' => 'B 中間の書籍', 'created_at' => now()->subDay()]);
        Book::factory()->create(['title' => 'C 古い書籍', 'created_at' => now()->subDays(2)]);
        Book::factory()->create(['title' => 'A 新しい書籍', 'created_at' => now()]);
    }

    public function test_default_sort_is_newest_first(): void
    {
        $this->createBooksForSort();
        $expected = ['A 新しい書籍', 'B 中間の書籍', 'C 古い書籍'];

        $this->assertSame($expected, $this->titles('/books'));
        $this->assertSame($expected, $this->titles('/books?sort=latest'));
        // 提供Bladeの「新しい順」は newest を送る
        $this->assertSame($expected, $this->titles('/books?sort=newest'));
    }

    public function test_sort_oldest(): void
    {
        $this->createBooksForSort();

        $this->assertSame(['C 古い書籍', 'B 中間の書籍', 'A 新しい書籍'], $this->titles('/books?sort=oldest'));
    }

    public function test_sort_title_ascending(): void
    {
        Book::factory()->create(['title' => 'B 中間の書籍', 'created_at' => now()]);
        Book::factory()->create(['title' => 'C 古い書籍', 'created_at' => now()->subDay()]);
        Book::factory()->create(['title' => 'A 新しい書籍', 'created_at' => now()->subDays(2)]);

        $this->assertSame(['A 新しい書籍', 'B 中間の書籍', 'C 古い書籍'], $this->titles('/books?sort=title'));
    }

    public function test_sort_rating_orders_by_average_and_puts_unreviewed_last(): void
    {
        // レビューなし（いちばん新しいが、末尾になる）
        Book::factory()->create(['title' => 'レビューなし', 'created_at' => now()]);

        $low = Book::factory()->create(['title' => '平均3.0', 'created_at' => now()->subDay()]);
        Review::factory()->for($low)->create(['rating' => 3]);

        // 同点（平均4.0）の2冊は、新しいほうが先
        $oldTie = Book::factory()->create(['title' => '平均4.0（古い）', 'created_at' => now()->subDays(3)]);
        Review::factory()->for($oldTie)->create(['rating' => 4]);
        $newTie = Book::factory()->create(['title' => '平均4.0（新しい）', 'created_at' => now()->subDays(2)]);
        Review::factory()->for($newTie)->create(['rating' => 3]);
        Review::factory()->for($newTie)->create(['rating' => 5]);

        $high = Book::factory()->create(['title' => '平均5.0', 'created_at' => now()->subDays(4)]);
        Review::factory()->for($high)->create(['rating' => 5]);

        $this->assertSame(
            ['平均5.0', '平均4.0（新しい）', '平均4.0（古い）', '平均3.0', 'レビューなし'],
            $this->titles('/books?sort=rating')
        );
    }

    public function test_invalid_sort_falls_back_to_newest_first(): void
    {
        $this->createBooksForSort();
        $expected = ['A 新しい書籍', 'B 中間の書籍', 'C 古い書籍'];

        $this->assertSame($expected, $this->titles('/books?sort=invalid'));
        $this->assertSame($expected, $this->titles('/books?sort[]=title'));
    }

    // ---------- ページネーション ----------

    public function test_pagination_links_keep_search_conditions(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->count(11)->hasAttached($genre)->create(['author' => 'PHP太郎']);
        Book::factory()->create(['author' => '別の著者']);

        $url = '/books?keyword=PHP&genre='.$genre->id.'&sort=oldest';
        /** @var LengthAwarePaginator $books */
        $books = $this->get($url)->assertOk()->viewData('books');

        $this->assertSame(10, $books->count());
        $this->assertSame(11, $books->total());

        $nextPageUrl = $books->nextPageUrl();
        $this->assertStringContainsString('keyword=PHP', $nextPageUrl);
        $this->assertStringContainsString('genre='.$genre->id, $nextPageUrl);
        $this->assertStringContainsString('sort=oldest', $nextPageUrl);
        $this->assertStringContainsString('page=2', $nextPageUrl);

        // 2ページ目でも条件が効いていること（残りの1件だけが表示される）
        $this->assertCount(1, $this->titles($url.'&page=2'));
    }

    // ---------- バリデーション ----------

    public function test_too_long_keyword_redirects_to_list_with_error(): void
    {
        $response = $this->get('/books?keyword='.str_repeat('a', 256));

        $response->assertRedirect(route('books.index'))
            ->assertSessionHas('error', 'キーワードは255文字以内で入力してください。');
    }

    public function test_non_integer_genre_redirects_to_list_with_error(): void
    {
        $this->get('/books?genre=abc')
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('error', 'ジャンルの値が不正です。');
    }

    public function test_invalid_page_redirects_to_list_with_error(): void
    {
        $this->get('/books?page=0')
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('error', 'ページ番号は1以上の整数で指定してください。');
    }
}
