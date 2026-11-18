<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
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
        // レビューは各書籍に2〜4件（ランダム）のため、11冊で22〜44件
        $this->assertGreaterThanOrEqual(22, Review::count());
        $this->assertLessThanOrEqual(44, Review::count());
        $this->assertSame(6, ReadingPlan::count());
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

    public function test_books_are_registered_by_seeded_users_with_genres_and_image(): void
    {
        $this->seed();

        // 登録者はランダムだが、必ずシーダーで作った5ユーザーのいずれか
        $userIds = User::pluck('id')->all();
        foreach (Book::all() as $book) {
            $this->assertContains($book->user_id, $userIds);
        }

        $book = Book::where('isbn', '9784422100524')->first();

        $this->assertSame('人を動かす', $book->title);
        $this->assertEqualsCanonicalizing(
            ['ビジネス', '自己啓発'],
            $book->genres->pluck('name')->all()
        );
        $this->assertSame('https://placehold.co/200x300/e2e8f0/475569?text=2', $book->image_url);
        $this->assertNotEmpty($book->description);
    }

    public function test_each_book_has_two_to_four_reviews_from_different_users(): void
    {
        $this->seed();

        foreach (Book::with('reviews')->get() as $book) {
            $this->assertGreaterThanOrEqual(2, $book->reviews->count());
            $this->assertLessThanOrEqual(4, $book->reviews->count());
            // 同じユーザーが同じ書籍に2件投稿していない
            $this->assertSame($book->reviews->count(), $book->reviews->unique('user_id')->count());
        }
    }

    public function test_reviews_have_rating_one_to_five_and_comment_matching_rating(): void
    {
        $this->seed();

        $comments = [
            5 => ['素晴らしい本でした！', '人生が変わりました。', '何度も読み返しています。'],
            4 => ['とても参考になりました。', '読みやすくておすすめです。', '期待通りの内容でした。'],
            3 => ['普通でした。', '可もなく不可もなく。', '期待したほどではなかった。'],
            2 => ['少し期待外れでした。', '内容が薄い印象。', 'もう少し深掘りしてほしかった。'],
            1 => ['残念ながら合いませんでした。', '期待と違いました。'],
        ];

        foreach (Review::all() as $review) {
            $this->assertContains($review->rating, [1, 2, 3, 4, 5]);
            $this->assertContains($review->comment, $comments[$review->rating]);
        }
    }

    public function test_seeding_repeatedly_always_creates_valid_data(): void
    {
        // ランダムな値でも、ユニーク制約（同じユーザー×同じ書籍）に違反せず毎回成功すること
        foreach (range(1, 5) as $i) {
            $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
            $this->assertSame(11, Book::count());
        }
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
