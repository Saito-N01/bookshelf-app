<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * $user のレビューを1件作る（書籍は毎回新しく作る）。
     *
     * @param  array<int, Genre>  $genres  書籍に付けるジャンル
     * @param  array<string, mixed>  $bookAttributes  書籍の属性
     */
    private function review(int $rating, array $genres = [], array $bookAttributes = [], ?Carbon $createdAt = null): Review
    {
        $book = Book::factory()->create($bookAttributes);
        $book->genres()->attach(collect($genres)->pluck('id'));

        return Review::factory()->for($this->user)->for($book)->create([
            'rating' => $rating,
            'created_at' => $createdAt ?? now(),
        ]);
    }

    /**
     * レポート画面を開き、画面に渡された $stats を返す。
     *
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        return $this->actingAs($this->user)->get('/reports')->assertOk()->viewData('stats');
    }

    // ---------- 基本サマリー ----------

    public function test_summary_counts_reviews_books_and_average_rating(): void
    {
        $this->review(5);
        $this->review(4);
        $this->review(4);

        $summary = $this->stats()['summary'];

        $this->assertSame(3, $summary['total_reviews']);
        $this->assertSame(3, $summary['books_read']);
        // (5 + 4 + 4) / 3 = 4.333... → 小数第1位に丸めて 4.3
        $this->assertSame(4.3, $summary['average_rating']);
    }

    public function test_summary_is_displayed_on_screen(): void
    {
        $this->review(5);
        $this->review(2);

        $this->actingAs($this->user)->get('/reports')
            ->assertSeeInOrder(['2', '総レビュー数', '2', '読了冊数', '3.5', '平均評価']);
    }

    public function test_average_rating_is_rounded_to_one_decimal_place(): void
    {
        // (5 + 5 + 4) / 3 = 4.666... → 4.7
        $this->review(5);
        $this->review(5);
        $this->review(4);

        $this->assertSame(4.7, $this->stats()['summary']['average_rating']);
    }

    // ---------- 評価分布 ----------

    public function test_rating_distribution_counts_each_rating_from_one_to_five(): void
    {
        $this->review(5);
        $this->review(5);
        $this->review(3);
        $this->review(1);

        $distribution = $this->stats()['rating_distribution'];

        // 添字 0〜4 が 1星〜5星。レビューがない評価（2星・4星）は 0 件
        $this->assertSame([1, 0, 1, 0, 2], $distribution->all());
    }

    public function test_rating_distribution_is_displayed_with_counts(): void
    {
        $this->review(4);

        $this->actingAs($this->user)->get('/reports')
            ->assertSeeInOrder(['★★★★', '1件', '★★★★★', '0件']);
    }

    // ---------- 高評価書籍TOP5 ----------

    public function test_top_rated_books_include_only_rating_four_or_more_ordered_by_rating(): void
    {
        $this->review(3, [], ['title' => '評価3の書籍']);
        $this->review(4, [], ['title' => '評価4の書籍', 'author' => '著者B']);
        $this->review(5, [], ['title' => '評価5の書籍', 'author' => '著者A']);

        $books = $this->stats()['top_rated_books'];

        $this->assertSame([
            ['id' => Book::where('title', '評価5の書籍')->value('id'), 'title' => '評価5の書籍', 'author' => '著者A', 'rating' => 5],
            ['id' => Book::where('title', '評価4の書籍')->value('id'), 'title' => '評価4の書籍', 'author' => '著者B', 'rating' => 4],
        ], $books->all());
    }

    public function test_top_rated_books_are_limited_to_five_and_newer_review_comes_first_on_tie(): void
    {
        $this->review(4, [], ['title' => '評価4・新'], now());
        $this->review(4, [], ['title' => '評価4・古'], now()->subDays(2));
        foreach (range(1, 4) as $i) {
            $this->review(5, [], ['title' => "評価5の書籍{$i}"], now()->subDays($i));
        }

        $titles = $this->stats()['top_rated_books']->pluck('title')->all();

        // 評価5の4冊（新しい順）→ 評価4の新しいほう。評価4の古いほうは6位なので入らない
        $this->assertSame(['評価5の書籍1', '評価5の書籍2', '評価5の書籍3', '評価5の書籍4', '評価4・新'], $titles);
    }

    public function test_top_rated_books_link_to_book_detail(): void
    {
        $review = $this->review(5, [], ['title' => 'リンク確認の書籍']);

        $this->actingAs($this->user)->get('/reports')
            ->assertSee(route('books.show', $review->book_id))
            ->assertSee('リンク確認の書籍');
    }

    // ---------- ジャンル別評価傾向TOP5 ----------

    public function test_genre_ratings_are_ordered_by_average_rating_then_count(): void
    {
        [$novel, $business, $tech] = Genre::factory()->count(3)->create()->all();

        // 小説: (5 + 3) / 2 = 4.0（2件）、ビジネス: 4.0（1件）、技術書: 5.0（1件）
        $this->review(5, [$novel]);
        $this->review(3, [$novel]);
        $this->review(4, [$business]);
        $this->review(5, [$tech]);

        $genres = $this->stats()['genre_ratings'];

        $this->assertSame([
            ['id' => $tech->id, 'name' => $tech->name, 'average_rating' => 5.0, 'count' => 1],
            // 平均が同じ（4.0）なら件数の多い順
            ['id' => $novel->id, 'name' => $novel->name, 'average_rating' => 4.0, 'count' => 2],
            ['id' => $business->id, 'name' => $business->name, 'average_rating' => 4.0, 'count' => 1],
        ], $genres->all());
    }

    public function test_review_of_book_with_multiple_genres_counts_for_each_genre(): void
    {
        [$business, $selfHelp] = Genre::factory()->count(2)->create()->all();
        $this->review(4, [$business, $selfHelp]);
        $this->review(2, [$business]);

        $genres = $this->stats()['genre_ratings']->keyBy('id');

        // ビジネス: (4 + 2) / 2 = 3.0（2件）、自己啓発: 4.0（1件）
        $this->assertSame(3.0, $genres[$business->id]['average_rating']);
        $this->assertSame(2, $genres[$business->id]['count']);
        $this->assertSame(4.0, $genres[$selfHelp->id]['average_rating']);
        $this->assertSame(1, $genres[$selfHelp->id]['count']);
    }

    public function test_genre_ratings_are_limited_to_five_and_link_to_genre_detail(): void
    {
        $genres = Genre::factory()->count(6)->create();
        // ジャンルごとに評価を変える（1つ目が最も低い 1、6つ目が 5）
        $genres->values()->each(fn (Genre $genre, int $i) => $this->review(min($i, 4) + 1, [$genre]));

        $ratings = $this->stats()['genre_ratings'];

        $this->assertCount(5, $ratings);
        // 最も評価の低いジャンル（評価1）は入らない
        $this->assertNotContains($genres->first()->id, $ratings->pluck('id')->all());
        $this->actingAs($this->user)->get('/reports')->assertSee(route('genres.show', $ratings->first()['id']));
    }

    // ---------- 対象は本人のレビューだけ ----------

    public function test_other_users_reviews_are_not_included(): void
    {
        $genre = Genre::factory()->create();
        $this->review(5, [$genre], ['title' => '自分がレビューした書籍']);

        $otherBook = Book::factory()->create(['title' => '他人だけがレビューした書籍']);
        $otherBook->genres()->attach($genre);
        Review::factory()->for($otherBook)->count(2)->create(['rating' => 1]);

        $stats = $this->stats();

        $this->assertSame(1, $stats['summary']['total_reviews']);
        $this->assertSame(5.0, $stats['summary']['average_rating']);
        $this->assertSame([0, 0, 0, 0, 1], $stats['rating_distribution']->all());
        $this->assertSame(['自分がレビューした書籍'], $stats['top_rated_books']->pluck('title')->all());
        $this->assertSame(1, $stats['genre_ratings']->first()['count']);
        $this->actingAs($this->user)->get('/reports')->assertDontSee('他人だけがレビューした書籍');
    }

    // ---------- レビュー0件 ----------

    public function test_report_with_no_reviews_shows_dash_and_empty_messages(): void
    {
        $stats = $this->stats();

        $this->assertSame(['total_reviews' => 0, 'books_read' => 0, 'average_rating' => null], $stats['summary']);
        $this->assertSame([0, 0, 0, 0, 0], $stats['rating_distribution']->all());
        $this->assertTrue($stats['top_rated_books']->isEmpty());
        $this->assertTrue($stats['genre_ratings']->isEmpty());

        $this->actingAs($this->user)->get('/reports')
            ->assertSeeInOrder(['-', '平均評価'])
            ->assertSee('4星以上の書籍がありません')
            ->assertSee('ジャンルが設定された書籍のレビューがありません');
    }

    // ---------- 認証・ナビゲーション ----------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_navigation_has_link_to_report(): void
    {
        $this->actingAs($this->user)->get('/books')->assertSee(route('reports.index'));
    }
}
