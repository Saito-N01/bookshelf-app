<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 指定した評価のレビューを付けた書籍を作る
     *
     * @param  array<int, int>  $ratings
     */
    private function createBookWithRatings(array $ratings, string $title = 'ランキング用の書籍'): Book
    {
        $book = Book::factory()->create(['title' => $title]);

        foreach ($ratings as $rating) {
            Review::factory()->for($book)->create(['rating' => $rating]);
        }

        return $book;
    }

    /**
     * ランキングに並んだ書籍のIDを、表示順の配列で返す
     *
     * @return array<int, int>
     */
    private function rankedBookIds(): array
    {
        return $this->get('/ranking')->viewData('rankedBooks')->pluck('id')->all();
    }

    public function test_guest_can_view_ranking(): void
    {
        $this->createBookWithRatings([5, 4], '平均4.5の書籍');

        $this->get('/ranking')
            ->assertOk()
            ->assertSee('平均4.5の書籍')
            ->assertSee('4.50')
            ->assertSee('(2件のレビュー)');
    }

    public function test_ranking_is_ordered_by_average_rating_descending(): void
    {
        $middle = $this->createBookWithRatings([4]);
        $lowest = $this->createBookWithRatings([3]);
        $highest = $this->createBookWithRatings([5]);

        $this->assertSame([$highest->id, $middle->id, $lowest->id], $this->rankedBookIds());
    }

    public function test_ranking_shows_at_most_ten_books(): void
    {
        // 平均3の書籍を1冊、平均5の書籍を10冊作る（合計11冊）
        $lowest = $this->createBookWithRatings([3]);
        for ($i = 0; $i < 10; $i++) {
            $this->createBookWithRatings([5]);
        }

        $rankedIds = $this->rankedBookIds();

        $this->assertCount(10, $rankedIds);
        $this->assertNotContains($lowest->id, $rankedIds);
    }

    public function test_books_without_reviews_are_not_included(): void
    {
        $reviewed = $this->createBookWithRatings([4]);
        $notReviewed = Book::factory()->create(['title' => 'レビューのない書籍']);

        $this->assertSame([$reviewed->id], $this->rankedBookIds());
        $this->get('/ranking')->assertDontSee($notReviewed->title);
    }

    public function test_books_with_same_average_are_ordered_by_review_count(): void
    {
        $fewReviews = $this->createBookWithRatings([4]);
        $manyReviews = $this->createBookWithRatings([4, 4, 4]);
        $someReviews = $this->createBookWithRatings([5, 3]);

        $this->assertSame([$manyReviews->id, $someReviews->id, $fewReviews->id], $this->rankedBookIds());
    }

    public function test_books_with_same_average_and_count_are_ordered_by_id(): void
    {
        $first = $this->createBookWithRatings([4]);
        $second = $this->createBookWithRatings([4]);
        $third = $this->createBookWithRatings([4]);

        $this->assertSame([$first->id, $second->id, $third->id], $this->rankedBookIds());
    }

    public function test_ranking_shows_message_when_no_book_has_reviews(): void
    {
        Book::factory()->create();

        $this->get('/ranking')
            ->assertOk()
            ->assertSee('まだレビューが投稿された書籍がありません。');
    }
}
