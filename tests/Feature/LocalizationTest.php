<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_and_timezone_are_japanese(): void
    {
        $this->assertSame('ja', config('app.locale'));
        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('Asia/Tokyo', now()->timezoneName);
    }

    public function test_required_message_is_japanese(): void
    {
        $validator = Validator::make(['name' => ''], ['name' => 'required']);

        $this->assertSame('お名前は必須です。', $validator->errors()->first('name'));
    }

    public function test_max_message_is_japanese(): void
    {
        $validator = Validator::make(
            ['name' => str_repeat('あ', 256)],
            ['name' => 'max:255']
        );

        $this->assertSame('お名前は255文字以内で入力してください。', $validator->errors()->first('name'));
    }

    public function test_min_message_is_japanese(): void
    {
        $validator = Validator::make(['password' => 'abc'], ['password' => 'min:8']);

        $this->assertSame('パスワードは8文字以上で入力してください。', $validator->errors()->first('password'));
    }

    public function test_email_message_is_japanese(): void
    {
        $validator = Validator::make(['email' => 'invalid'], ['email' => 'email']);

        $this->assertSame('メールアドレスはメール形式で入力してください。', $validator->errors()->first('email'));
    }

    public function test_unique_message_is_japanese(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $validator = Validator::make(
            ['email' => 'taken@example.com'],
            ['email' => 'unique:users,email']
        );

        $this->assertSame('このメールアドレスは既に登録されています。', $validator->errors()->first('email'));
    }

    public function test_confirmed_message_is_japanese(): void
    {
        $validator = Validator::make(
            ['password' => 'password123', 'password_confirmation' => 'different'],
            ['password' => 'confirmed']
        );

        $this->assertSame('パスワードが一致しません。', $validator->errors()->first('password'));
    }

    public function test_other_rules_used_by_the_app_are_japanese(): void
    {
        $validator = Validator::make(
            ['isbn' => 'abc', 'published_date' => 'xxx', 'image_url' => 'not-url', 'rating' => 9, 'genres' => []],
            [
                'isbn' => 'digits:13',
                'published_date' => 'date',
                'image_url' => 'url',
                'rating' => 'integer|between:1,5',
                'genres' => 'array|min:1',
            ]
        );

        $errors = $validator->errors();
        $this->assertSame('ISBNは13桁の数字で入力してください。', $errors->first('isbn'));
        $this->assertSame('出版日は正しい日付で入力してください。', $errors->first('published_date'));
        $this->assertSame('画像URLはURL形式で入力してください。', $errors->first('image_url'));
        $this->assertSame('評価は1〜5の範囲で入力してください。', $errors->first('rating'));
        $this->assertSame('ジャンルは1個以上選択してください。', $errors->first('genres'));
    }

    public function test_auth_failed_message_is_japanese(): void
    {
        $this->assertSame('メールアドレスまたはパスワードが正しくありません。', trans('auth.failed'));
    }
}
