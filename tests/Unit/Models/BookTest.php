<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->assertTrue($book->user->is($user));
    }

    public function test_book_has_genres(): void
    {
        $book = Book::factory()->create();
        $genres = Genre::factory()->count(2)->create();
        $book->genres()->attach($genres);

        $this->assertCount(2, $book->genres);
        $this->assertEqualsCanonicalizing(
            $genres->pluck('id')->all(),
            $book->genres->pluck('id')->all()
        );
    }

    public function test_book_has_reviews(): void
    {
        $book = Book::factory()->create();
        Review::factory()->count(3)->for($book)->create();

        $this->assertCount(3, $book->reviews);
    }

    public function test_published_date_is_cast_to_date(): void
    {
        $book = Book::factory()->create(['published_date' => '2020-04-01']);

        $this->assertInstanceOf(Carbon::class, $book->fresh()->published_date);
        $this->assertSame('2020-04-01', $book->fresh()->published_date->toDateString());
    }

    public function test_with_avg_returns_average_rating_of_reviews(): void
    {
        $book = Book::factory()->create();
        foreach ([3, 4, 5] as $rating) {
            Review::factory()->for($book)->create(['rating' => $rating]);
        }

        $result = Book::withAvg('reviews', 'rating')->find($book->id);

        $this->assertEquals(4.0, $result->reviews_avg_rating);
    }

    public function test_with_avg_is_null_when_book_has_no_reviews(): void
    {
        $book = Book::factory()->create();

        $result = Book::withAvg('reviews', 'rating')->find($book->id);

        $this->assertNull($result->reviews_avg_rating);
    }

    public function test_factory_creates_book_with_thirteen_digit_isbn(): void
    {
        $book = Book::factory()->create();

        $this->assertMatchesRegularExpression('/^\d{13}$/', $book->isbn);
    }
}
