<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 書籍一覧（Web）の検索条件。キーワード・ジャンル・並び順・ページ番号を受け取る。
 */
class IndexBookRequest extends FormRequest
{
    /**
     * 書籍一覧はゲストも閲覧できるため、誰でも実行できる。
     *
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 書籍一覧の検索条件のバリデーションルール。
     *
     * sort は不正な値でもエラーにせず、コントローラ側で「新しい順」として扱うため、形式を検証しない。
     * genre は整数であることだけを検証する（存在しないIDは、該当なしの一覧になる）。
     *
     * @return array<string, array<int, mixed>> 「項目名 => ルールの配列」の形式
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'integer'],
            'sort' => ['nullable'],
            'page' => ['nullable', 'integer', 'min:1'],
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
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre.integer' => 'ジャンルの値が不正です。',
            'page.integer' => 'ページ番号は1以上の整数で指定してください。',
            'page.min' => 'ページ番号は1以上の整数で指定してください。',
        ];
    }

    /**
     * エラーメッセージで使う項目名（日本語）。
     *
     * @return array<string, string> 「項目名 => 日本語の表示名」の形式
     */
    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'genre' => 'ジャンル',
            'sort' => '並び順',
            'page' => 'ページ番号',
        ];
    }

    /**
     * バリデーション失敗時は、検索条件なしの書籍一覧に戻し、最初のエラーを表示する。
     * 一覧画面には項目ごとのエラー表示がないため、画面上部のエラー枠（session('error')）を使う。
     *
     * @param  Validator  $validator  失敗したバリデーター
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('books.index')->with('error', $validator->errors()->first())
        );
    }
}
