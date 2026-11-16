<?php

namespace App\Http\Requests;

use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

/**
 * ログインのバリデーション。
 * Fortify標準の LoginRequest を継承し、ルールと日本語メッセージを差し替える。
 */
class LoginRequest extends FortifyLoginRequest
{
    /**
     * ログインのバリデーションルール。
     *
     * @return array<string, array<int, mixed>> 「項目名 => ルールの配列」の形式
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ];
    }

    /**
     * バリデーションエラー時の日本語メッセージ。
     *
     * @return array<string, string> 「項目名.ルール名 => メッセージ」の形式
     */
    public function messages(): array
    {
        return [
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスはメール形式で入力してください。',
            'password.required' => 'パスワードを入力してください。',
        ];
    }
}
