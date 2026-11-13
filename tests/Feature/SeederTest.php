<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\BookSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_expected_record_counts(): void
    {
        $this->seed();

        $this->assertSame(5, User::count());
        $this->assertSame(10, Genre::count());
        $this->assertSame(11, Book::count());
        $this->assertSame(32, Review::count());
    }

    public function test_user_seeder_creates_users_with_hashed_password(): void
    {
        $this->seed(UserSeeder::class);

        $user = User::where('email', 'yamada@example.com')->first();

        $this->assertSame('山田太郎', $user->name);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_first_three_seeders_do_not_create_duplicates_when_run_twice(): void
    {
        foreach ([1, 2] as $_) {
            $this->seed(UserSeeder::class);
            $this->seed(GenreSeeder::class);
            $this->seed(BookSeeder::class);
        }

        $this->assertSame(5, User::count());
        $this->assertSame(10, Genre::count());
        $this->assertSame(11, Book::count());
        $this->assertSame(16, DB::table('book_genre')->count());
    }

    public function test_books_are_registered_by_first_user_with_genres_and_image(): void
    {
        $this->seed();

        $book = Book::where('isbn', '9784422100524')->first();

        $this->assertSame('人を動かす', $book->title);
        $this->assertTrue($book->user->is(User::first()));
        $this->assertEqualsCanonicalizing(
            ['ビジネス', '自己啓発'],
            $book->genres->pluck('name')->all()
        );
        $this->assertSame('https://placehold.co/200x300/e2e8f0/475569?text=2', $book->image_url);
        $this->assertNotEmpty($book->description);
    }

    public function test_each_book_has_two_to_four_reviews_with_rating_three_to_five(): void
    {
        $this->seed();

        foreach (Book::withCount('reviews')->get() as $book) {
            $this->assertGreaterThanOrEqual(2, $book->reviews_count);
            $this->assertLessThanOrEqual(4, $book->reviews_count);
        }
        $this->assertSame(0, Review::where('rating', '<', 3)->orWhere('rating', '>', 5)->count());
    }

    public function test_each_user_has_three_to_five_favorites(): void
    {
        $this->seed();

        foreach (User::withCount('favoriteBooks')->get() as $user) {
            $this->assertGreaterThanOrEqual(3, $user->favorite_books_count);
            $this->assertLessThanOrEqual(5, $user->favorite_books_count);
        }
    }

    public function test_each_review_has_up_to_three_likes_and_never_from_its_author(): void
    {
        $this->seed();

        foreach (Review::withCount('likedByUsers')->get() as $review) {
            $this->assertLessThanOrEqual(3, $review->liked_by_users_count);
            $this->assertFalse($review->likedByUsers->contains('id', $review->user_id));
        }
        $this->assertGreaterThan(0, DB::table('review_likes')->count());
    }
}
