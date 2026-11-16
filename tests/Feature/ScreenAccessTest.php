<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 画面アクセスのテスト。
 * 各画面が「誰に表示されるか」（ゲスト可・認証必須・認可必須）を、URLの一覧でまとめて確認する。
 */
class ScreenAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Book $book;

    private Genre $genre;

    private Review $review;

    protected function setUp(): void
    {
        parent::setUp();

        // $owner が登録した書籍と、$owner が投稿したレビュー
        $this->owner = User::factory()->create();
        $this->book = Book::factory()->for($this->owner)->create();
        $this->genre = Genre::factory()->create();
        $this->review = Review::factory()->for($this->owner)->create();
    }

    /**
     * ゲストも表示できる画面
     *
     * @return array<int, string>
     */
    private function publicUrls(): array
    {
        return [
            '/',
            '/books',
            '/books/'.$this->book->id,
            '/ranking',
            '/login',
            '/register',
        ];
    }

    /**
     * ログインしていれば誰でも表示できる画面
     *
     * @return array<int, string>
     */
    private function authRequiredUrls(): array
    {
        return [
            '/books/create',
            '/favorites',
            '/genres',
            '/genres/'.$this->genre->id,
            '/genres/create',
            '/genres/'.$this->genre->id.'/edit',
        ];
    }

    /**
     * 作成者・投稿者本人だけが表示できる画面
     *
     * @return array<int, string>
     */
    private function ownerOnlyUrls(): array
    {
        return [
            '/books/'.$this->book->id.'/edit',
            '/reviews/'.$this->review->id.'/edit',
        ];
    }

    public function test_guest_can_view_public_screens(): void
    {
        foreach ($this->publicUrls() as $url) {
            $this->assertSame(200, $this->get($url)->status(), $url.' がゲストで表示できること');
        }
    }

    public function test_guest_is_redirected_to_login_from_auth_required_screens(): void
    {
        foreach ([...$this->authRequiredUrls(), ...$this->ownerOnlyUrls()] as $url) {
            $response = $this->get($url);

            $this->assertSame(302, $response->status(), $url.' がゲストではリダイレクトされること');
            $response->assertRedirect('/login');
        }
    }

    public function test_authenticated_user_can_view_auth_required_screens(): void
    {
        $user = User::factory()->create();

        foreach ($this->authRequiredUrls() as $url) {
            $this->assertSame(
                200,
                $this->actingAs($user)->get($url)->status(),
                $url.' がログイン済みで表示できること'
            );
        }
    }

    public function test_authenticated_user_can_view_public_screens(): void
    {
        $user = User::factory()->create();

        foreach (['/', '/books', '/books/'.$this->book->id, '/ranking'] as $url) {
            $this->assertSame(
                200,
                $this->actingAs($user)->get($url)->status(),
                $url.' がログイン済みで表示できること'
            );
        }
    }

    public function test_owner_can_view_owner_only_screens(): void
    {
        foreach ($this->ownerOnlyUrls() as $url) {
            $this->assertSame(
                200,
                $this->actingAs($this->owner)->get($url)->status(),
                $url.' が本人には表示されること'
            );
        }
    }

    public function test_other_user_gets_403_on_owner_only_screens(): void
    {
        $otherUser = User::factory()->create();

        foreach ($this->ownerOnlyUrls() as $url) {
            $this->assertSame(
                403,
                $this->actingAs($otherUser)->get($url)->status(),
                $url.' が他人には403になること'
            );
        }
    }

    public function test_authenticated_user_is_redirected_to_top_from_login_and_register(): void
    {
        foreach (['/login', '/register'] as $url) {
            $this->actingAs($this->owner)->get($url)->assertRedirect('/');
        }
    }

    public function test_nonexistent_ids_return_404(): void
    {
        $this->get('/books/999')->assertNotFound();

        foreach (['/books/999/edit', '/genres/999', '/genres/999/edit', '/reviews/999/edit'] as $url) {
            $this->assertSame(
                404,
                $this->actingAs($this->owner)->get($url)->status(),
                $url.' が存在しないIDでは404になること'
            );
        }
    }
}
