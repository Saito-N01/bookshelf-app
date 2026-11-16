<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 登録・更新APIの正しいリクエストボディ。
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
            'genre_ids' => [Genre::factory()->create()->id],
        ], $overrides);
    }

    // ---------- AP06: ログインAPI ----------

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'yamada@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user', ['id' => $user->id, 'name' => '山田太郎', 'email' => 'yamada@example.com']);
        $this->assertIsString($response->json('token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_returns_401_for_wrong_password_or_unknown_email(): void
    {
        User::factory()->create(['email' => 'yamada@example.com', 'password' => Hash::make('password')]);

        $this->postJson('/api/v1/login', ['email' => 'yamada@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => '認証が必要です。']);

        $this->postJson('/api/v1/login', ['email' => 'unknown@example.com', 'password' => 'password'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => '認証が必要です。']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_returns_422_when_input_is_invalid(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'メールアドレスを入力してください。')
            ->assertJsonPath('errors.password.0', 'パスワードを入力してください。');

        $this->postJson('/api/v1/login', ['email' => 'not-an-email', 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'メールアドレスはメール形式で入力してください。');
    }

    public function test_token_from_login_can_be_used_as_bearer_token(): void
    {
        $user = User::factory()->create(['email' => 'yamada@example.com', 'password' => Hash::make('password')]);

        $token = $this->postJson('/api/v1/login', ['email' => 'yamada@example.com', 'password' => 'password'])
            ->json('token');

        // ログインAPIで受け取ったトークンを Authorization: Bearer で送ると、書き込みができる
        $this->withToken($token)
            ->postJson('/api/v1/books', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id);
    }

    // ---------- 未認証（401） ----------

    public function test_write_endpoints_return_401_without_token(): void
    {
        $book = Book::factory()->create(['title' => '元のタイトル']);

        $this->postJson('/api/v1/books', $this->validPayload())
            ->assertUnauthorized()
            ->assertExactJson(['message' => '認証が必要です。']);

        $this->putJson('/api/v1/books/'.$book->id, $this->validPayload())
            ->assertUnauthorized()
            ->assertExactJson(['message' => '認証が必要です。']);

        $this->deleteJson('/api/v1/books/'.$book->id)
            ->assertUnauthorized()
            ->assertExactJson(['message' => '認証が必要です。']);

        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元のタイトル']);
    }

    public function test_write_endpoints_return_401_json_without_accept_header(): void
    {
        // Accept: application/json を付けないクライアントにも、リダイレクトではなくJSONで返す
        $this->post('/api/v1/books', [])
            ->assertUnauthorized()
            ->assertExactJson(['message' => '認証が必要です。']);
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->withToken('invalid-token')
            ->postJson('/api/v1/books', $this->validPayload())
            ->assertUnauthorized()
            ->assertExactJson(['message' => '認証が必要です。']);
    }

    public function test_read_endpoints_do_not_require_token(): void
    {
        $book = Book::factory()->create();

        $this->getJson('/api/v1/books')->assertOk();
        $this->getJson('/api/v1/books/'.$book->id)->assertOk();
    }

    // ---------- 登録者は認証ユーザー ----------

    public function test_store_sets_user_id_from_authenticated_user_and_ignores_request_user_id(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $anotherUser = User::factory()->create();

        // user_id を送っても無視され、認証ユーザーが登録者になる
        $this->postJson('/api/v1/books', $this->validPayload(['user_id' => $anotherUser->id]))
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('books', ['isbn' => '9784000000001', 'user_id' => $user->id]);
    }

    // ---------- 認可（403） ----------

    public function test_owner_can_update_and_delete_own_book(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $book = Book::factory()->for($user)->create();

        $this->putJson('/api/v1/books/'.$book->id, $this->validPayload(['title' => '更新後のタイトル']))
            ->assertOk()
            ->assertJsonPath('data.title', '更新後のタイトル');

        $this->deleteJson('/api/v1/books/'.$book->id)->assertNoContent();
        $this->assertDatabaseCount('books', 0);
    }

    public function test_update_returns_403_for_another_users_book(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $book = Book::factory()->create(['title' => '元のタイトル']);

        $this->putJson('/api/v1/books/'.$book->id, $this->validPayload(['title' => '更新後のタイトル']))
            ->assertForbidden()
            ->assertExactJson(['message' => 'この操作を行う権限がありません。']);

        // 入力内容が不正でも、バリデーション（422）より先に403になる
        $this->putJson('/api/v1/books/'.$book->id, [])
            ->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元のタイトル']);
    }

    public function test_destroy_returns_403_for_another_users_book(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $book = Book::factory()->create();

        $this->deleteJson('/api/v1/books/'.$book->id)
            ->assertForbidden()
            ->assertExactJson(['message' => 'この操作を行う権限がありません。']);

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_web_forbidden_page_is_not_changed_to_json(): void
    {
        // Web画面の403は、これまでどおりHTMLのエラーページを返す（JSONにしない）
        $book = Book::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get('/books/'.$book->id.'/edit');

        $response->assertForbidden();
        $this->assertStringNotContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }
}
