<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 会員登録フォームの正しい入力値（各テストで一部だけ上書きして使う）
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function registerInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    // ---------- 会員登録 ----------

    public function test_register_screen_is_displayed(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_user_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/register', $this->registerInput());

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['name' => 'テスト太郎', 'email' => 'test@example.com']);
        $this->assertTrue(Hash::check('password123', User::first()->password));
    }

    public function test_register_fails_when_name_is_empty(): void
    {
        $response = $this->from('/register')->post('/register', $this->registerInput(['name' => '']));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['name' => 'お名前を入力してください。']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_fails_when_email_is_empty(): void
    {
        $response = $this->post('/register', $this->registerInput(['email' => '']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください。']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_fails_when_email_is_not_email_format(): void
    {
        $response = $this->post('/register', $this->registerInput(['email' => 'not-an-email']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスはメール形式で入力してください。']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_fails_when_email_is_already_registered(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->post('/register', $this->registerInput());

        $response->assertSessionHasErrors(['email' => 'このメールアドレスは既に使用されています。']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_register_fails_when_password_is_empty(): void
    {
        $response = $this->post('/register', $this->registerInput([
            'password' => '',
            'password_confirmation' => '',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください。']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_fails_when_password_is_shorter_than_eight_characters(): void
    {
        $response = $this->post('/register', $this->registerInput([
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください。']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_fails_when_password_confirmation_does_not_match(): void
    {
        $response = $this->post('/register', $this->registerInput([
            'password_confirmation' => 'different123',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードと一致しません。']);
        $this->assertDatabaseCount('users', 0);
    }

    // ---------- ログイン ----------

    public function test_login_screen_is_displayed(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password123']);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->assertGuest();
    }

    public function test_login_fails_when_email_and_password_are_empty(): void
    {
        $response = $this->post('/login', ['email' => '', 'password' => '']);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください。',
            'password' => 'パスワードを入力してください。',
        ]);
        $this->assertGuest();
    }

    // ---------- ログアウト ----------

    public function test_user_can_logout_and_is_redirected_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    // ---------- ログイン済みユーザーのリダイレクト ----------

    public function test_authenticated_user_is_redirected_from_login_and_register(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect('/');
        $this->actingAs($user)->get('/register')->assertRedirect('/');
    }
}
